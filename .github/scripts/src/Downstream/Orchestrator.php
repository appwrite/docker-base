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
        $required = $this->repository->required($this->base);
        if ($required === []) {
            throw new Exception(
                "Branch '{$this->base}' declares no required status checks, "
                . 'so a merge cannot be verified',
            );
        }

        $deadline = Deadline::after($this->clock->now(), self::TIMEOUT);

        while (true) {
            $checks = $this->repository->checks($pull);
            $pending = Checks::pending($checks, $required);
            if ($pending === []) {
                $failed = Checks::failed($checks, $required);
                if ($failed !== []) {
                    throw new Exception(
                        'Base update CI did not succeed: '
                        . implode(', ', $failed),
                    );
                }

                return;
            }

            if ($deadline->expired($this->clock->now())) {
                throw new Exception(
                    'Base update CI did not conclude for pull request '
                    . "#{$pull}: " . implode(', ', $pending),
                );
            }

            $this->sleeper->sleep(self::INTERVAL);
        }
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
        return $this->tag($this->repository->merge($pull, $head));
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
