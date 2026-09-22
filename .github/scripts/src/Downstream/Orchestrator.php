<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

use DockerBase\Automation\Clock;
use DockerBase\Automation\Deadline;
use DockerBase\Automation\Sleeper;

final readonly class Orchestrator
{
    public const string MARKER = '<!-- base-automation:v1 -->';

    private const string DOCKERFILE = 'Dockerfile';

    private const string CONSTANTS = 'app/init/constants.php';

    private const string BRANCH = 'automation/base-';

    private const int TIMEOUT = 7200;

    private const int INTERVAL = 30;

    public function __construct(
        private Repository $repository,
        private Dockerfile $dockerfile,
        private Constants $constants,
        private Clock $clock,
        private Sleeper $sleeper,
        private string $base = 'main',
    ) {
    }

    public function propose(string $version): ?Pull
    {
        $head = $this->repository->head($this->base);
        $bump = $this->dockerfile->bump(
            $this->repository->file(self::DOCKERFILE, $head),
            $version,
        );
        if (! $bump->changed()) {
            return null;
        }

        $branch = self::BRANCH . $version;
        $this->repository->commit(
            $branch,
            $head,
            self::DOCKERFILE,
            $bump->content,
            "chore: update base image to {$version}",
        );

        return $this->repository->open(
            $branch,
            $this->base,
            "chore: update base image to {$version}",
            self::MARKER
                . "\n<!-- base-version:{$version} -->"
                . "\n\nAutomated base image update from `{$bump->current}`"
                . " to `{$version}`.",
        );
    }

    public function wait(int $pull): void
    {
        $this->settle($pull);
    }

    public function recover(string $version): ?Release
    {
        $target = $this->repository->mergeCommit(self::BRANCH . $version);
        if ($target === null) {
            return null;
        }

        if (! $this->repository->contains($this->base, $target)) {
            return null;
        }

        foreach ($this->repository->tags(Release::PREFIX) as $tag) {
            if ($tag->target === $target) {
                return null;
            }
        }

        return $this->tag($target);
    }

    public function release(int $pull, string $head): Release
    {
        $required = $this->settle($pull);

        return $this->tag($this->mergeUnderProtection($pull, $head, $required));
    }

    /**
     * Merge through branch protection so GitHub evaluates the required checks
     * at merge time, which is the only place that evaluation is atomic.
     *
     * A refusal is bypassed only when GitHub says it was the review
     * requirement - the one protection a single automation identity cannot
     * satisfy, because it opens the pull request and GitHub forbids
     * self-approval - and only when the required checks are re-read and still
     * green. Every other refusal, including one whose reason is not
     * recognised, is rethrown: a required deployment, signed commits,
     * unresolved conversations or a branch restriction must stop the release.
     *
     * @param list<string> $required
     */
    private function mergeUnderProtection(
        int $pull,
        string $head,
        array $required,
    ): string {
        try {
            return $this->repository->merge($pull, $head, bypass: false);
        } catch (Exception $refused) {
            if (! new Refusal($refused->getMessage())->isReviewRequirement()) {
                throw $refused;
            }

            $status = $this->repository->status($pull);
            if (Checks::pending($status->checks, $required) !== []) {
                throw $refused;
            }
            $this->assertPassed($status->checks, $required);

            return $this->repository->merge($pull, $head, bypass: true);
        }
    }

    /**
     * Wait until every required check has concluded green. BLOCKED is not
     * waited out: the review requirement holds a pull request there forever,
     * and the merge itself is what resolves it.
     *
     * @return list<string>
     */
    private function settle(int $pull): array
    {
        $required = $this->required();
        $deadline = Deadline::after($this->clock->now(), self::TIMEOUT);

        while (true) {
            $status = $this->repository->status($pull);
            $blocking = Checks::pending($status->checks, $required);
            if ($blocking === []) {
                $this->assertPassed($status->checks, $required);
                if ($status->stuck()) {
                    throw new Exception(
                        "Pull request #{$pull} cannot be merged: "
                        . "merge state {$status->state}",
                    );
                }
                if (! $status->computing()) {
                    return $required;
                }

                $blocking = ['merge state UNKNOWN'];
            }

            if ($deadline->expired($this->clock->now())) {
                throw new Exception(
                    "Pull request #{$pull} did not become mergeable: "
                    . implode(', ', $blocking),
                );
            }

            $this->sleeper->sleep(self::INTERVAL);
        }
    }

    /**
     * @return list<string>
     */
    private function required(): array
    {
        $required = $this->repository->required($this->base);
        if ($required === []) {
            throw new Exception(
                "Branch '{$this->base}' declares no required status checks, "
                . 'so a merge cannot be verified',
            );
        }

        return $required;
    }

    /**
     * @param list<array{name: string, status: string, conclusion: string}> $checks
     * @param list<string> $required
     */
    private function assertPassed(array $checks, array $required): void
    {
        $failed = Checks::failed($checks, $required);
        if ($failed !== []) {
            throw new Exception(
                'Base update CI did not succeed: ' . implode(', ', $failed),
            );
        }
    }

    private function tag(string $target): Release
    {
        $application = $this->constants->application(
            $this->repository->file(self::CONSTANTS, $target),
        );
        $release = Release::next(
            $application,
            array_map(
                static fn (Tag $tag): string => $tag->name,
                $this->repository->tags(Release::PREFIX),
            ),
        );
        $this->repository->tag((string) $release, $target);

        return $release;
    }
}
