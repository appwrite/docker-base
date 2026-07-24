<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

use DockerBase\Command\Result;
use ErrorException;
use Throwable;

final readonly class Program
{
    public function __construct(
        private Console $console,
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public function execute(array $arguments): Result
    {
        try {
            set_error_handler(
                static function (
                    int $severity,
                    string $message,
                    string $file,
                    int $line,
                ): bool {
                    if ((error_reporting() & $severity) === 0) {
                        return false;
                    }

                    throw new ErrorException(
                        $message,
                        0,
                        $severity,
                        $file,
                        $line,
                    );
                },
            );

            try {
                return new Result(
                    0,
                    $this->console->execute($arguments) . PHP_EOL,
                    '',
                );
            } catch (UsageException $exception) {
                return new Result(
                    2,
                    '',
                    'Error: ' . $this->detail($exception) . PHP_EOL,
                );
            } catch (Throwable $exception) {
                return new Result(
                    1,
                    '',
                    'Error: ' . $this->detail($exception) . PHP_EOL,
                );
            }
        } finally {
            restore_error_handler();
        }
    }

    private function detail(Throwable $exception): string
    {
        $detail = preg_replace(
            '/\s+/',
            ' ',
            trim($exception->getMessage()),
        );

        return $detail === null || $detail === ''
            ? 'Dependency update failed'
            : $detail;
    }
}
