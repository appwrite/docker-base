<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final readonly class Orchestrator
{
    private const string BODY = '<!-- dependency-automation:v1 -->'
        . "\n<!-- dependency-target:%s -->"
        . "\n<!-- dependency-pull:%d -->"
        . "\n\nAutomated weekly dependency release.";

    private const int TIMEOUT = 7200;

    private const int INTERVAL = 20;

    public function __construct(
        private Repository $repository,
        private Clock $clock,
        private Sleeper $sleeper,
    ) {
    }

    public function recover(): ?Candidate
    {
        return RecoverySelector::select(
            $this->repository->tags(),
            $this->repository->releases(),
            $this->repository->mergedPullRequests(),
        );
    }

    public function merge(int $pull, string $head, string $base): string
    {
        $request = $this->repository->pullRequest($pull);
        if ($request->baseBranch !== 'main') {
            throw new PullRequestUnavailableException(
                'Pull request does not target main',
            );
        }
        PullRequestValidator::validate($request, $head, $base);
        if (! $request->mergeable) {
            throw new PullRequestUnavailableException(
                'Pull request is not currently mergeable',
            );
        }

        return MergeValidator::validateResult(
            $this->repository->squashMerge($pull, $head, $base),
            $head,
            $base,
        );
    }

    public function prepare(
        ?string $tag,
        string $target,
        int $pull,
        ?int $draft,
    ): Preparation {
        $name = $tag ?? $this->createTag($target);
        $this->validateTag($name, $target, 'does not exist');
        $body = $this->body($target, $pull);
        $release = $draft === null
            ? $this->repository->createDraft(
                $name,
                $target,
                $pull,
                $body,
            )
            : $this->repository->draft($draft);
        $this->validateDraft($release, $name, $target, $pull, $body);
        $this->validateTag(
            $name,
            $target,
            'disappeared during preparation',
        );

        return new Preparation(
            tag: $name,
            target: $target,
            pull: $pull,
            draft: $release->identifier,
        );
    }

    public function wait(string $tag, string $target): void
    {
        $boundary = new DateTimeImmutable(
            '1970-01-01T00:00:00+00:00',
            new DateTimeZone('UTC'),
        );
        $deadline = Deadline::after($this->clock->now(), self::TIMEOUT);
        while (true) {
            $this->validateTag(
                $tag,
                $target,
                'disappeared during its build',
            );
            $now = $this->clock->now();
            $state = RunEvaluator::state(
                runs: $this->repository->runs(
                    'build-and-push.yml',
                    'push',
                    $target,
                    $tag,
                ),
                workflow: 'Build and Push',
                event: 'push',
                head: $target,
                branch: $tag,
                created: $boundary,
                deadline: $deadline,
                now: $now,
            );
            if ($state === WorkflowState::Succeeded) {
                return;
            }
            if (
                in_array(
                    $state,
                    [
                        WorkflowState::Cancelled,
                        WorkflowState::Failed,
                        WorkflowState::TimedOut,
                    ],
                    true,
                )
            ) {
                throw new RuntimeException(
                    "Tag Build and Push did not succeed: {$state->value}",
                );
            }

            $this->sleeper->sleep(
                min(self::INTERVAL, max($deadline->remaining($this->clock->now()), 0)),
            );
        }
    }

    public function publish(
        string $tag,
        string $target,
        int $pull,
        int $draft,
    ): void {
        $this->validateTag(
            $tag,
            $target,
            'disappeared before publication',
        );
        $body = $this->body($target, $pull);
        $release = $this->repository->draft($draft);
        $this->validateDraft($release, $tag, $target, $pull, $body);
        $preparation = new Preparation($tag, $target, $pull, $draft);
        $published = $this->repository->publish($preparation);
        $final = $this->repository->tag($tag);
        $valid = $final !== null
            && $final->name === $tag
            && $final->target === $target
            && $published->tag === $tag
            && ! $published->draft
            && ! $published->prerelease;
        if (! $valid) {
            $rolledBack = $this->repository->redraft($preparation);
            if (! $rolledBack->draft) {
                throw new RuntimeException(
                    "Release {$tag} has an unsafe public target and "
                    . 'could not be returned to draft',
                );
            }
            throw new RuntimeException(
                "Release {$tag} target changed during publication; "
                . 'the release was returned to draft',
            );
        }

        TargetValidator::validateTag($final, $tag, $target);
        TargetValidator::validateRelease(
            new Release($published->tag, $final->target),
            $tag,
            $target,
        );
    }

    private function createTag(string $target): string
    {
        $available = array_map(
            static fn (Tag $tag): string => $tag->name,
            $this->repository->tags(),
        );
        $candidate = (string) Version::next($available);
        while (true) {
            $tag = $this->repository->createTag($candidate, $target);
            if ($tag->name === $candidate && $tag->target === $target) {
                TargetValidator::validateTag(
                    $tag,
                    $candidate,
                    $target,
                );

                return $candidate;
            }
            if ($tag->name !== $candidate) {
                throw new RuntimeException(
                    "Tag creation returned {$tag->name} for {$candidate}",
                );
            }

            $available = array_map(
                static fn (Tag $availableTag): string => $availableTag->name,
                $this->repository->tags(),
            );
            $candidate = (string) Version::afterCollision(
                $available,
                $candidate,
            );
        }
    }

    private function validateTag(
        string $name,
        string $target,
        string $missing,
    ): void {
        $tag = $this->repository->tag($name);
        if ($tag === null) {
            throw new RuntimeException("Tag {$name} {$missing}");
        }
        TargetValidator::validateTag($tag, $name, $target);
    }

    private function validateDraft(
        Recovery $draft,
        string $tag,
        string $target,
        int $pull,
        string $body,
    ): void {
        $merge = new Merge(
            number: $pull,
            target: $target,
            head: '',
            parents: [],
            base: 'main',
            branch: 'automation/dependencies-recovery',
            body: Merge::MARKER,
            files: ['Dockerfile'],
            state: 'merged',
        );
        if (
            $draft->tag !== $tag
            || $draft->body !== $body
            || ! RecoverySelector::matches($draft, $merge)
        ) {
            throw new RuntimeException(
                "Draft release {$draft->identifier} is unsafe",
            );
        }
    }

    private function body(string $target, int $pull): string
    {
        return sprintf(self::BODY, $target, $pull);
    }
}
