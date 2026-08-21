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
        $deadline = Deadline::after($this->clock->now(), self::TIMEOUT);

        while (true) {
            $checks = $this->repository->checks($pull);
            if (Checks::settled($checks)) {
                $failed = Checks::failed($checks);
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
                    "Base update CI did not settle for pull request #{$pull}",
                );
            }

            $this->sleeper->sleep(self::INTERVAL);
        }
    }

    public function release(int $pull, string $head): Release
    {
        $target = $this->repository->merge($pull, $head);
        $application = $this->constants->application(
            $this->repository->file(self::CONSTANTS, $target),
        );
        $release = Release::next(
            $application,
            $this->repository->tags(Release::PREFIX),
        );
        $this->repository->tag((string) $release, $target);

        return $release;
    }
}
