<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Updater
{
    public function __construct(
        private Application $application,
    ) {
    }

    public function update(string $path, bool $dryRun = false): Plan
    {
        if (basename($path) !== 'Dockerfile') {
            throw new Exception('The update target must be named Dockerfile');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception("Unable to read {$path}");
        }

        $plan = $this->application->plan($content);
        if (! $plan->changed() || $dryRun) {
            return $plan;
        }

        $written = file_put_contents($path, $plan->content, LOCK_EX);
        if ($written !== strlen($plan->content)) {
            throw new Exception("Unable to write {$path}");
        }

        return $plan;
    }
}
