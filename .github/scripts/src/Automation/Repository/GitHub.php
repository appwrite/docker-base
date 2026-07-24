<?php

declare(strict_types=1);

namespace DockerBase\Automation\Repository;

use DateTimeImmutable;
use DockerBase\Automation\Merge;
use DockerBase\Automation\MergeResult;
use DockerBase\Automation\Preparation;
use DockerBase\Automation\PullRequest;
use DockerBase\Automation\Recovery;
use DockerBase\Automation\Repository;
use DockerBase\Automation\ReviewDecision;
use DockerBase\Automation\Run;
use DockerBase\Automation\Tag;
use DockerBase\Automation\Version;
use DockerBase\Command\Result;
use DockerBase\Command\Runner;
use JsonException;
use Override;
use RuntimeException;

final readonly class GitHub implements Repository
{
    private const string MARKER = '<!-- dependency-automation:v1 -->';

    private const string PREFIX = 'automation/dependencies-';

    public function __construct(
        private Runner $runner,
        private string $repository,
        private string $version,
    ) {
        if (! preg_match('/^[^\/]+\/[^\/]+$/', $this->repository)) {
            throw new RuntimeException(
                "Invalid GitHub repository '{$this->repository}'",
            );
        }
    }

    /**
     * @return list<Tag>
     */
    #[Override]
    public function tags(): array
    {
        $tags = [];
        foreach (
            $this->pages(
                "repos/{$this->repository}/git/matching-refs/tags/",
            ) as $item
        ) {
            $reference = $this->optionalString($item, 'ref');
            $object = $item['object'] ?? null;
            if (
                ! str_starts_with($reference, 'refs/tags/')
                || ! is_array($object)
                || ($object['type'] ?? null) !== 'commit'
            ) {
                continue;
            }

            $tags[] = new Tag(
                substr($reference, strlen('refs/tags/')),
                $this->requiredString($object, 'sha', 'tag target'),
            );
        }

        return $tags;
    }

    #[Override]
    public function releaseByTag(string $tag): ?Recovery
    {
        $result = $this->api(
            'GET',
            sprintf(
                'repos/%s/releases/tags/%s',
                $this->repository,
                rawurlencode($tag),
            ),
            check: false,
        );
        if ($result->succeeded()) {
            return $this->release(
                $this->object($result->output, "release lookup for {$tag}"),
            );
        }

        $error = $this->object($result->output, "release lookup for {$tag}");
        $status = $error['status'] ?? null;
        if (
            (! is_int($status) && ! is_string($status))
            || (string) $status !== '404'
        ) {
            throw new RuntimeException("Release lookup failed for {$tag}");
        }

        return $this->graphQLRelease($tag);
    }

    /**
     * @param list<Tag> $tags
     *
     * @return list<Recovery>
     */
    #[Override]
    public function releases(array $tags): array
    {
        $stable = [];
        $versions = [];
        foreach ($tags as $tag) {
            $version = Version::parse($tag->name);
            if ($version === null) {
                continue;
            }

            $stable[$tag->name] = $tag;
            $versions[$tag->name] = $version;
        }

        $listed = array_values(
            array_filter(
                $this->listedReleases(),
                static fn (Recovery $release): bool => (
                    ! $release->draft
                    && ! $release->prerelease
                    && isset($versions[$release->tag])
                ),
            ),
        );
        usort(
            $listed,
            static fn (Recovery $left, Recovery $right): int => (
                $versions[$right->tag]->compare($versions[$left->tag])
            ),
        );

        $releases = [];
        $threshold = null;
        foreach ($listed as $hint) {
            $release = $this->releaseByTag($hint->tag);
            if ($release === null) {
                continue;
            }
            $this->assertTag($release, $hint->tag);
            $releases[$release->tag] = $release;
            if (! $release->draft && ! $release->prerelease) {
                $threshold = $versions[$release->tag];
                break;
            }
        }

        $candidates = array_keys($stable);
        usort(
            $candidates,
            static fn (string $left, string $right): int => (
                $versions[$right]->compare($versions[$left])
            ),
        );
        foreach ($candidates as $tag) {
            if (
                $threshold !== null
                && $versions[$tag]->compare($threshold) <= 0
            ) {
                continue;
            }

            $release = $this->releaseByTag($tag);
            if ($release === null) {
                continue;
            }
            $this->assertTag($release, $tag);
            $releases[$release->tag] = $release;
        }

        return array_values($releases);
    }

    /**
     * @return list<Merge>
     */
    #[Override]
    public function mergedPullRequests(): array
    {
        $merges = [];
        $pulls = $this->pages(
            "repos/{$this->repository}/pulls"
            . '?state=closed&base=main&sort=updated&direction=desc',
        );
        foreach ($pulls as $pull) {
            $head = $pull['head'] ?? null;
            $branch = is_array($head)
                ? $this->optionalString($head, 'ref')
                : '';
            $body = $this->optionalString($pull, 'body');
            if (
                ($pull['merged_at'] ?? null) === null
                || ! str_contains($body, self::MARKER)
                || ! str_starts_with($branch, self::PREFIX)
            ) {
                continue;
            }

            $number = $this->requiredInteger($pull, 'number', 'pull request');
            $details = $this->pullPayload($number);
            $commit = $details['mergeCommit'] ?? null;
            $target = is_array($commit)
                ? $this->optionalString($commit, 'oid')
                : '';
            if (
                strtolower($this->optionalString($details, 'state')) !== 'merged'
                || $target === ''
            ) {
                continue;
            }

            $files = [];
            foreach (
                $this->pages(
                    "repos/{$this->repository}/pulls/{$number}/files",
                ) as $file
            ) {
                $files[] = $this->requiredString(
                    $file,
                    'filename',
                    "pull request #{$number} file",
                );
            }

            $merges[] = new Merge(
                number: $number,
                target: $target,
                head: $this->optionalString($details, 'headRefOid'),
                parents: $this->commitParents($target),
                base: $this->optionalString($details, 'baseRefName'),
                branch: $branch,
                body: $body,
                files: $files,
                state: 'merged',
            );
        }

        return $merges;
    }

    #[Override]
    public function pullRequest(int $number): PullRequest
    {
        $pull = $this->pullPayload($number);
        $review = ReviewDecision::tryFrom(
            strtolower($this->optionalString($pull, 'reviewDecision')),
        ) ?? ReviewDecision::ReviewRequired;

        return new PullRequest(
            number: $this->requiredInteger($pull, 'number', 'pull request'),
            head: $this->requiredString($pull, 'headRefOid', 'pull request'),
            base: $this->requiredString($pull, 'baseRefOid', 'pull request'),
            baseBranch: $this->requiredString(
                $pull,
                'baseRefName',
                'pull request',
            ),
            state: strtolower(
                $this->requiredString($pull, 'state', 'pull request'),
            ),
            review: $review,
            mergeable: ($pull['mergeable'] ?? null) === 'MERGEABLE',
        );
    }

    #[Override]
    public function squashMerge(
        int $number,
        string $head,
        string $base,
    ): MergeResult {
        $this->runner->run(
            [
                'gh',
                'pr',
                'merge',
                (string) $number,
                '--repo',
                $this->repository,
                '--squash',
                '--match-head-commit',
                $head,
            ],
            check: false,
        );

        $pull = $this->pullPayload($number);
        $merge = $pull['mergeCommit'] ?? null;
        $commit = is_array($merge)
            ? $this->optionalString($merge, 'oid')
            : '';
        $commit = $commit === '' ? null : $commit;

        return new MergeResult(
            head: $this->optionalString($pull, 'headRefOid'),
            state: strtolower($this->optionalString($pull, 'state')),
            commit: $commit,
            parents: $commit === null ? [] : $this->commitParents($commit),
        );
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function commitParents(string $commit): array
    {
        $result = $this->api(
            'GET',
            "repos/{$this->repository}/commits/{$commit}",
        );
        $payload = $this->object(
            $result->output,
            "commit {$commit}",
        );
        $parents = $payload['parents'] ?? null;
        if (! is_array($parents) || ! array_is_list($parents)) {
            throw new RuntimeException("Commit {$commit} has invalid parents");
        }

        $decoded = [];
        foreach ($parents as $parent) {
            if (! is_array($parent)) {
                throw new RuntimeException(
                    "Commit {$commit} has invalid parents",
                );
            }
            $decoded[] = $this->requiredString(
                $parent,
                'sha',
                "commit {$commit} parent",
            );
        }

        return $decoded;
    }

    #[Override]
    public function tag(string $name): ?Tag
    {
        $result = $this->api(
            'GET',
            sprintf(
                'repos/%s/git/ref/tags/%s',
                $this->repository,
                rawurlencode($name),
            ),
            check: false,
        );
        if (! $result->succeeded()) {
            return null;
        }

        $payload = $this->object($result->output, "tag {$name}");
        $target = $payload['object'] ?? null;
        if (! is_array($target) || ($target['type'] ?? null) !== 'commit') {
            throw new RuntimeException("Tag {$name} is not lightweight");
        }

        return new Tag(
            $this->removePrefix(
                $this->requiredString($payload, 'ref', "tag {$name}"),
                'refs/tags/',
            ),
            $this->requiredString($target, 'sha', "tag {$name} target"),
        );
    }

    #[Override]
    public function createTag(string $name, string $target): Tag
    {
        $result = $this->api(
            'POST',
            "repos/{$this->repository}/git/refs",
            [
                ['-f', "ref=refs/tags/{$name}"],
                ['-f', "sha={$target}"],
            ],
            check: false,
        );
        $tag = $this->tag($name);
        if ($tag !== null) {
            return $tag;
        }
        if (! $result->succeeded()) {
            throw new RuntimeException("Failed to create tag {$name}");
        }

        throw new RuntimeException("Tag {$name} is missing after creation");
    }

    #[Override]
    public function draft(int $identifier): Recovery
    {
        $result = $this->api(
            'GET',
            "repos/{$this->repository}/releases/{$identifier}",
        );

        return $this->release(
            $this->object($result->output, "release {$identifier}"),
        );
    }

    #[Override]
    public function createDraft(
        string $tag,
        string $target,
        int $pull,
        string $body,
    ): Recovery {
        $existing = $this->releaseByTag($tag);
        if ($existing !== null) {
            if (! $existing->draft) {
                throw new RuntimeException("Release {$tag} is already published");
            }

            return $this->assertDraft(
                $existing,
                $tag,
                $target,
                $pull,
                $body,
            );
        }

        $result = $this->api(
            'POST',
            "repos/{$this->repository}/releases",
            [
                ['-f', "tag_name={$tag}"],
                ['-f', "target_commitish={$target}"],
                ['-f', "name={$tag}"],
                ['-f', "body={$body}"],
                ['-F', 'draft=true'],
                ['-F', 'prerelease=false'],
                ['-F', 'generate_release_notes=true'],
            ],
            check: false,
        );
        if ($result->succeeded()) {
            return $this->assertDraft(
                $this->release(
                    $this->object(
                        $result->output,
                        "draft release {$tag}",
                    ),
                    pull: $pull,
                ),
                $tag,
                $target,
                $pull,
                $body,
            );
        }

        $concurrent = $this->releaseByTag($tag);
        if ($concurrent === null) {
            throw new RuntimeException(
                "Failed to create draft release {$tag}",
            );
        }
        if (! $concurrent->draft) {
            throw new RuntimeException(
                "Release {$tag} was published concurrently",
            );
        }

        return $this->assertDraft(
            $concurrent,
            $tag,
            $target,
            $pull,
            $body,
        );
    }

    #[Override]
    public function publish(Preparation $preparation): Recovery
    {
        $result = $this->api(
            'PATCH',
            "repos/{$this->repository}/releases/{$preparation->draft}",
            [
                ['-F', 'draft=false'],
                ['-F', 'prerelease=false'],
                ['-f', 'make_latest=true'],
            ],
            check: false,
        );
        if (! $result->succeeded() || trim($result->output) === '') {
            return $this->draft($preparation->draft);
        }

        return $this->release(
            $this->object(
                $result->output,
                "published release {$preparation->tag}",
            ),
            pull: $preparation->pull,
        );
    }

    #[Override]
    public function redraft(Preparation $preparation): Recovery
    {
        $this->api(
            'PATCH',
            "repos/{$this->repository}/releases/{$preparation->draft}",
            [['-F', 'draft=true']],
            check: false,
        );

        return $this->draft($preparation->draft);
    }

    /**
     * @return list<Run>
     */
    #[Override]
    public function runs(
        string $workflow,
        string $event,
        string $head,
        string $branch,
    ): array {
        $result = $this->api(
            'GET',
            "repos/{$this->repository}/actions/workflows/{$workflow}/runs",
            [
                ['-f', "branch={$branch}"],
                ['-f', "head_sha={$head}"],
                ['-f', "event={$event}"],
                ['-f', 'per_page=100'],
            ],
        );
        $payload = $this->object(
            $result->output,
            "workflow {$workflow} runs",
        );
        $runs = $payload['workflow_runs'] ?? null;
        if (! is_array($runs) || ! array_is_list($runs)) {
            throw new RuntimeException(
                "Workflow {$workflow} runs are invalid",
            );
        }

        $decoded = [];
        foreach ($runs as $run) {
            if (! is_array($run)) {
                throw new RuntimeException(
                    "Workflow {$workflow} run is invalid",
                );
            }
            $conclusion = $run['conclusion'] ?? null;
            if ($conclusion !== null && ! is_string($conclusion)) {
                throw new RuntimeException(
                    "Workflow {$workflow} run conclusion is invalid",
                );
            }
            $decoded[] = new Run(
                identifier: $this->requiredInteger(
                    $run,
                    'id',
                    "workflow {$workflow} run",
                ),
                workflow: $this->requiredString(
                    $run,
                    'name',
                    "workflow {$workflow} run",
                ),
                event: $this->requiredString(
                    $run,
                    'event',
                    "workflow {$workflow} run",
                ),
                head: $this->requiredString(
                    $run,
                    'head_sha',
                    "workflow {$workflow} run",
                ),
                branch: $this->requiredString(
                    $run,
                    'head_branch',
                    "workflow {$workflow} run",
                ),
                created: new DateTimeImmutable(
                    $this->requiredString(
                        $run,
                        'created_at',
                        "workflow {$workflow} run",
                    ),
                ),
                attempt: $this->requiredInteger(
                    $run,
                    'run_attempt',
                    "workflow {$workflow} run",
                ),
                status: $this->requiredString(
                    $run,
                    'status',
                    "workflow {$workflow} run",
                ),
                conclusion: $conclusion,
            );
        }

        return $decoded;
    }

    /**
     * @param list<array{string, string}> $fields
     */
    private function api(
        string $method,
        string $endpoint,
        array $fields = [],
        bool $check = true,
    ): Result {
        $command = [
            'gh',
            'api',
            '-X',
            $method,
            $endpoint,
            '-H',
            "X-GitHub-Api-Version: {$this->version}",
        ];
        foreach ($fields as [$kind, $value]) {
            array_push($command, $kind, $value);
        }

        return $this->runner->run($command, $check);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pages(string $endpoint): array
    {
        $result = $this->runner->run([
            'gh',
            'api',
            '--paginate',
            '--slurp',
            '-X',
            'GET',
            $endpoint,
            '-H',
            "X-GitHub-Api-Version: {$this->version}",
            '-f',
            'per_page=100',
        ]);
        try {
            $pages = json_decode(
                $result->output,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Invalid paginated GitHub response for {$endpoint}",
                previous: $exception,
            );
        }
        if (! is_array($pages) || ! array_is_list($pages)) {
            throw new RuntimeException(
                "Invalid paginated GitHub response for {$endpoint}",
            );
        }

        $items = [];
        foreach ($pages as $page) {
            if (! is_array($page) || ! array_is_list($page)) {
                throw new RuntimeException(
                    "Invalid paginated GitHub response for {$endpoint}",
                );
            }
            foreach ($page as $item) {
                if (is_array($item) && ! array_is_list($item)) {
                    $items[] = $this->dictionary(
                        $item,
                        "paginated GitHub item for {$endpoint}",
                    );
                }
            }
        }

        return $items;
    }

    /**
     * @return list<Recovery>
     */
    private function listedReleases(): array
    {
        $releases = [];
        foreach (
            $this->pages("repos/{$this->repository}/releases") as $item
        ) {
            $releases[] = $this->release($item);
        }

        return $releases;
    }

    private function graphQLRelease(string $tag): ?Recovery
    {
        [$owner, $name] = explode('/', $this->repository, 2);
        $query = 'query($owner:String!,$name:String!,$tag:String!){'
            . 'repository(owner:$owner,name:$name){'
            . 'release(tagName:$tag){'
            . 'databaseId tagName isDraft isPrerelease description '
            . 'tagCommit{oid}'
            . '}}}';
        $result = $this->api(
            'POST',
            'graphql',
            [
                ['-f', "owner={$owner}"],
                ['-f', "name={$name}"],
                ['-f', "tag={$tag}"],
                ['-f', "query={$query}"],
            ],
        );
        $payload = $this->object(
            $result->output,
            "GraphQL release lookup for {$tag}",
        );
        $errors = $payload['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            throw new RuntimeException(
                "GitHub GraphQL release lookup failed for {$tag}",
            );
        }
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw new RuntimeException(
                "Release lookup for {$tag} is invalid",
            );
        }
        $repository = $data['repository'] ?? null;
        if (! is_array($repository)) {
            throw new RuntimeException(
                "Release lookup for {$tag} is invalid",
            );
        }
        $release = $repository['release'] ?? null;
        if ($release === null) {
            return null;
        }
        if (! is_array($release)) {
            throw new RuntimeException(
                "Release lookup for {$tag} is invalid",
            );
        }

        $commit = $release['tagCommit'] ?? null;
        $target = is_array($commit)
            ? $this->optionalString($commit, 'oid')
            : '';

        return new Recovery(
            identifier: $this->requiredInteger(
                $release,
                'databaseId',
                "release {$tag}",
            ),
            tag: $this->requiredString($release, 'tagName', "release {$tag}"),
            target: $target,
            pull: $this->pullFromBody(
                $this->optionalString($release, 'description'),
            ),
            draft: $this->requiredBoolean(
                $release,
                'isDraft',
                "release {$tag}",
            ),
            prerelease: $this->requiredBoolean(
                $release,
                'isPrerelease',
                "release {$tag}",
            ),
            body: $this->optionalString($release, 'description'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pullPayload(int $number): array
    {
        $result = $this->runner->run([
            'gh',
            'pr',
            'view',
            (string) $number,
            '--repo',
            $this->repository,
            '--json',
            (
                'baseRefName,baseRefOid,headRefOid,mergeCommit,'
                . 'mergeable,number,reviewDecision,state'
            ),
            '--jq',
            '.',
        ]);

        return $this->object(
            $result->output,
            "pull request #{$number}",
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function release(array $payload, ?int $pull = null): Recovery
    {
        $body = $this->optionalString($payload, 'body');

        return new Recovery(
            identifier: $this->requiredInteger($payload, 'id', 'release'),
            tag: $this->requiredString($payload, 'tag_name', 'release'),
            target: $this->requiredString(
                $payload,
                'target_commitish',
                'release',
            ),
            pull: $pull ?? $this->pullFromBody($body),
            draft: $this->requiredBoolean($payload, 'draft', 'release'),
            prerelease: $this->requiredBoolean(
                $payload,
                'prerelease',
                'release',
            ),
            body: $body,
        );
    }

    private function assertDraft(
        Recovery $release,
        string $tag,
        string $target,
        int $pull,
        string $body,
    ): Recovery {
        if (
            $release->tag !== $tag
            || $release->target !== $target
            || $release->pull !== $pull
            || ! $release->draft
            || $release->prerelease
            || $release->body !== $body
        ) {
            throw new RuntimeException(
                "Draft release {$release->identifier} is unsafe",
            );
        }

        return $release;
    }

    private function assertTag(Recovery $release, string $expected): void
    {
        if ($release->tag !== $expected) {
            throw new RuntimeException(
                "Release lookup for {$expected} returned {$release->tag}",
            );
        }
    }

    private function pullFromBody(string $body): int
    {
        if (
            preg_match(
                '/<!-- dependency-pull:(0|[1-9]\d*) -->/',
                $body,
                $matches,
            ) !== 1
        ) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function object(string $json, string $label): array
    {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                ucfirst($label) . ' is invalid',
                previous: $exception,
            );
        }
        if (! is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException(ucfirst($label) . ' is invalid');
        }

        return $this->dictionary($payload, $label);
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function dictionary(array $payload, string $label): array
    {
        $decoded = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException(ucfirst($label) . ' is invalid');
            }
            $decoded[$key] = $value;
        }

        return $decoded;
    }

    /**
     * @param array<mixed> $payload
     */
    private function optionalString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        if (! is_string($value)) {
            throw new RuntimeException("GitHub field {$key} is invalid");
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     */
    private function requiredString(
        array $payload,
        string $key,
        string $label,
    ): string {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new RuntimeException(ucfirst($label) . " {$key} is invalid");
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     */
    private function requiredInteger(
        array $payload,
        string $key,
        string $label,
    ): int {
        $value = $payload[$key] ?? null;
        if (! is_int($value)) {
            throw new RuntimeException(ucfirst($label) . " {$key} is invalid");
        }

        return $value;
    }

    /**
     * @param array<mixed> $payload
     */
    private function requiredBoolean(
        array $payload,
        string $key,
        string $label,
    ): bool {
        $value = $payload[$key] ?? null;
        if (! is_bool($value)) {
            throw new RuntimeException(ucfirst($label) . " {$key} is invalid");
        }

        return $value;
    }

    private function removePrefix(string $value, string $prefix): string
    {
        return str_starts_with($value, $prefix)
            ? substr($value, strlen($prefix))
            : $value;
    }
}
