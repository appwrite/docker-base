<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

use Throwable;
use Utopia\CLI\CLI;

final readonly class Console
{
    public const string USAGE = 'Usage: dependencies.php [--dry-run] [--dockerfile PATH]';

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
        if ($arguments === ['--help']) {
            return self::USAGE;
        }
        if (in_array('--help', $arguments, true)) {
            throw new UsageException(
                '--help cannot be combined with other arguments',
            );
        }

        $normalized = ['dependencies.php', 'update'];
        for ($index = 0; $index < count($arguments); ++$index) {
            $argument = $arguments[$index];
            if ($argument === '--dry-run') {
                $normalized[] = '--dry-run=true';
            } elseif ($argument === '--dockerfile') {
                $path = $arguments[++$index] ?? '';
                if ($path === '') {
                    throw new UsageException('--dockerfile requires a path');
                }
                $normalized[] = '--dockerfile=' . $path;
            } elseif (str_starts_with($argument, '--dockerfile=')) {
                $path = substr($argument, strlen('--dockerfile='));
                if ($path === '') {
                    throw new UsageException('--dockerfile requires a path');
                }
                $normalized[] = $argument;
            } else {
                throw new UsageException("Unknown dependency updater argument '{$argument}'");
            }
        }

        $cli = new CLI(args: $normalized);
        $result = '';
        $failure = null;
        $cli->task('update')->action(function () use ($cli, &$result): void {
            $args = $cli->getArgs();
            $dockerfile = is_string($args['dockerfile'] ?? null)
                ? $args['dockerfile']
                : $this->dockerfile;
            $dryRun = ($args['dry-run'] ?? 'false') === 'true';
            $result = $this->reporter->render($this->updater->update($dockerfile, $dryRun));
        });
        $cli->error()->action(function () use ($cli, &$failure): void {
            $failure = $cli->getResource('error');
        });
        $cli->run();

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return $result;
    }
}
