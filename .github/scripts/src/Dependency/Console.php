<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

use InvalidArgumentException;

final readonly class Console
{
    public function __construct(
        private Updater $updater,
        private Reporter $reporter,
        private string $dockerfile,
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public function execute(array $arguments): string
    {
        $dockerfile = $this->dockerfile;
        $dryRun = false;

        for ($index = 0; $index < count($arguments); ++$index) {
            $argument = $arguments[$index];
            if ($argument === '--dry-run') {
                $dryRun = true;

                continue;
            }
            if ($argument === '--dockerfile') {
                $dockerfile = $arguments[++$index] ?? '';
                if ($dockerfile === '') {
                    throw new InvalidArgumentException(
                        '--dockerfile requires a path',
                    );
                }

                continue;
            }
            if (str_starts_with($argument, '--dockerfile=')) {
                $dockerfile = substr($argument, strlen('--dockerfile='));
                if ($dockerfile === '') {
                    throw new InvalidArgumentException(
                        '--dockerfile requires a path',
                    );
                }

                continue;
            }

            throw new InvalidArgumentException(
                "Unknown dependency updater argument '{$argument}'",
            );
        }

        return $this->reporter->render(
            $this->updater->update($dockerfile, $dryRun),
        );
    }
}
