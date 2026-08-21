<?php

declare(strict_types=1);

namespace DockerBase\Downstream\Repository;

use DockerBase\Command\Runner;
use DockerBase\Downstream\Exception;
use DockerBase\Downstream\Pull;
use DockerBase\Downstream\Repository;
use DockerBase\Downstream\Tag;
use JsonException;
use Override;

final readonly class GitHub implements Repository
{
    public function __construct(
        private Runner $runner,
        private string $repository,
        private string $version,
    ) {
        if (preg_match('/\A[^\/]+\/[^\/]+\z/', $this->repository) !== 1) {
            throw new Exception(
                "Invalid GitHub repository '{$this->repository}'",
            );
        }
    }

    #[Override]
    public function file(string $path, string $ref): string
    {
        $encoded = $this->text([
            'gh', 'api', '-X', 'GET',
            "repos/{$this->repository}/contents/{$path}",
            '-H', "X-GitHub-Api-Version: {$this->version}",
            '-f', "ref={$ref}",
            '--jq', '.content',
        ]);
        $content = base64_decode(str_replace("\n", '', $encoded), true);
        if ($content === false) {
            throw new Exception("Unable to decode {$path} at {$ref}");
        }

        return $content;
    }

    #[Override]
    public function head(string $branch): string
    {
        return $this->sha(
            $this->text([
                'gh', 'api', '-X', 'GET',
                "repos/{$this->repository}/commits/{$branch}",
                '-H', "X-GitHub-Api-Version: {$this->version}",
                '--jq', '.sha',
            ]),
            "head of {$branch}",
        );
    }

    /**
     * @return list<Tag>
     */
    #[Override]
    public function tags(string $prefix): array
    {
        $output = $this->text([
            'gh', 'api', '--paginate',
            "repos/{$this->repository}/git/matching-refs/tags/{$prefix}",
            '-H', "X-GitHub-Api-Version: {$this->version}",
            '--jq', '.[] | "\(.ref)\t\(.object.sha)"',
        ]);

        $tags = [];
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $fields = explode("\t", trim($line));
            if (
                count($fields) !== 2
                || ! str_starts_with($fields[0], 'refs/tags/')
            ) {
                continue;
            }

            $tags[] = new Tag(
                substr($fields[0], strlen('refs/tags/')),
                $fields[1],
            );
        }

        return $tags;
    }

    #[Override]
    public function mergeCommit(string $branch): ?string
    {
        $output = $this->text([
            'gh', 'pr', 'list',
            '--repo', $this->repository,
            '--head', $branch,
            '--state', 'merged',
            '--json', 'mergeCommit',
            '--jq', '.[0].mergeCommit.oid // ""',
        ]);
        if (trim($output) === '') {
            return null;
        }

        return $this->sha($output, "merge commit for {$branch}");
    }

    #[Override]
    public function commit(
        string $branch,
        string $base,
        string $path,
        string $content,
        string $message,
    ): string {
        $this->runner->run([
            'gh', 'api', '-X', 'POST',
            "repos/{$this->repository}/git/refs",
            '-H', "X-GitHub-Api-Version: {$this->version}",
            '-f', "ref=refs/heads/{$branch}",
            '-f', "sha={$base}",
        ]);

        $existing = $this->text([
            'gh', 'api', '-X', 'GET',
            "repos/{$this->repository}/contents/{$path}",
            '-H', "X-GitHub-Api-Version: {$this->version}",
            '-f', "ref={$branch}",
            '--jq', '.sha',
        ]);

        return $this->sha(
            $this->text([
                'gh', 'api', '-X', 'PUT',
                "repos/{$this->repository}/contents/{$path}",
                '-H', "X-GitHub-Api-Version: {$this->version}",
                '-f', "branch={$branch}",
                '-f', "message={$message}",
                '-f', 'content=' . base64_encode($content),
                '-f', 'sha=' . trim($existing),
                '--jq', '.commit.sha',
            ]),
            'update commit',
        );
    }

    #[Override]
    public function open(
        string $branch,
        string $base,
        string $title,
        string $body,
    ): Pull {
        $payload = $this->json([
            'gh', 'api', '-X', 'POST',
            "repos/{$this->repository}/pulls",
            '-H', "X-GitHub-Api-Version: {$this->version}",
            '-f', "title={$title}",
            '-f', "head={$branch}",
            '-f', "base={$base}",
            '-f', "body={$body}",
        ]);

        $number = $payload['number'] ?? null;
        $head = $payload['head'] ?? null;
        if (! is_int($number) || ! is_array($head)) {
            throw new Exception('Pull request creation returned no number');
        }

        return new Pull(
            $number,
            $this->sha(
                is_string($head['sha'] ?? null) ? $head['sha'] : '',
                "head of pull request #{$number}",
            ),
            $base,
        );
    }

    /**
     * @return list<array{name: string, status: string, conclusion: string}>
     */
    #[Override]
    public function checks(int $pull): array
    {
        $payload = $this->json([
            'gh', 'pr', 'view', (string) $pull,
            '--repo', $this->repository,
            '--json', 'statusCheckRollup',
        ]);

        $rollup = $payload['statusCheckRollup'] ?? null;
        if (! is_array($rollup)) {
            throw new Exception(
                "Unable to read checks for pull request #{$pull}",
            );
        }

        $checks = [];
        foreach ($rollup as $check) {
            if (! is_array($check)) {
                continue;
            }

            $name = $check['name'] ?? $check['context'] ?? '';
            $status = $check['status'] ?? $check['state'] ?? '';
            $conclusion = $check['conclusion'] ?? '';
            $checks[] = [
                'name' => is_string($name) ? $name : '',
                'status' => is_string($status) ? strtoupper($status) : '',
                'conclusion' => is_string($conclusion)
                    ? strtoupper($conclusion)
                    : '',
            ];
        }

        return $checks;
    }

    #[Override]
    public function merge(int $pull, string $head): string
    {
        $this->runner->run([
            'gh', 'pr', 'merge', (string) $pull,
            '--repo', $this->repository,
            '--squash',
            '--admin',
            '--match-head-commit', $head,
        ]);

        $target = $this->text([
            'gh', 'pr', 'view', (string) $pull,
            '--repo', $this->repository,
            '--json', 'mergeCommit',
            '--jq', '.mergeCommit.oid',
        ]);

        return $this->sha($target, "merge commit for #{$pull}");
    }

    #[Override]
    public function tag(string $name, string $target): void
    {
        $this->runner->run([
            'gh', 'api', '-X', 'POST',
            "repos/{$this->repository}/git/refs",
            '-H', "X-GitHub-Api-Version: {$this->version}",
            '-f', "ref=refs/tags/{$name}",
            '-f', "sha={$target}",
        ]);

        $created = $this->text([
            'gh', 'api', '-X', 'GET',
            "repos/{$this->repository}/git/ref/tags/{$name}",
            '-H', "X-GitHub-Api-Version: {$this->version}",
            '--jq', '.object.sha',
        ]);
        if (trim($created) !== $target) {
            throw new Exception(
                "Tag {$name} does not point at {$target}",
            );
        }
    }

    /**
     * @param list<string> $command
     */
    private function text(array $command): string
    {
        return trim($this->runner->run($command)->output);
    }

    /**
     * @param list<string> $command
     *
     * @return array<string, mixed>
     */
    private function json(array $command): array
    {
        $output = $this->runner->run($command)->output;

        try {
            $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new Exception(
                'Unable to decode GitHub response: '
                . $exception->getMessage(),
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new Exception('GitHub returned an unexpected response');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    private function sha(string $value, string $subject): string
    {
        $value = trim($value);
        if (preg_match('/\A[0-9a-f]{40}\z/', $value) !== 1) {
            throw new Exception("Unable to read the {$subject}");
        }

        return $value;
    }
}
