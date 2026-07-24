<?php

declare(strict_types=1);

namespace DockerBase\Tests\E2E\Bin;

use DockerBase\Command\Process;
use DockerBase\Command\Result;
use DockerBase\Dependency\Console;
use DockerBase\Tests\Unit\Dependency\Fixture;
use PHPUnit\Framework\TestCase;

final class DependenciesTest extends TestCase
{
    public function test_help_uses_standard_output_and_exit_zero(): void
    {
        $result = $this->execute('--help');

        self::assertSame(0, $result->code);
        self::assertSame(Console::USAGE . PHP_EOL, $result->output);
        self::assertSame('', $result->error);
    }

    public function test_invalid_arguments_use_standard_error_and_exit_two(): void
    {
        $result = $this->execute('--unknown');

        self::assertSame(2, $result->code);
        self::assertSame('', $result->output);
        self::assertSame(
            "Error: Unknown dependency updater argument '--unknown'"
                . PHP_EOL,
            $result->error,
        );
    }

    public function test_domain_failures_use_standard_error_and_exit_one(): void
    {
        $result = $this->execute(
            '--dockerfile',
            '/tmp/not-a-dockerfile',
        );

        self::assertSame(1, $result->code);
        self::assertSame('', $result->output);
        self::assertSame(
            'Error: The update target must be named Dockerfile' . PHP_EOL,
            $result->error,
        );
        self::assertStringNotContainsString('Stack trace', $result->error);
    }

    public function test_runtime_warnings_are_returned_as_one_concise_error(): void
    {
        $path = sys_get_temp_dir()
            . '/docker-base-missing-'
            . bin2hex(random_bytes(8))
            . '/Dockerfile';
        $result = $this->execute('--dockerfile', $path);

        self::assertSame(1, $result->code);
        self::assertSame('', $result->output);
        self::assertStringStartsWith(
            "Error: file_get_contents({$path}):",
            $result->error,
        );
        self::assertSame(1, substr_count($result->error, PHP_EOL));
        self::assertStringNotContainsString('Warning:', $result->error);
        self::assertStringNotContainsString('Stack trace', $result->error);
    }

    public function test_success_report_uses_standard_output_and_exit_zero(): void
    {
        $directory = sys_get_temp_dir()
            . '/docker-base-entrypoint-'
            . bin2hex(random_bytes(8));
        if (! mkdir($directory)) {
            self::fail(
                "Unable to create temporary directory: {$directory}",
            );
        }
        $path = "{$directory}/Dockerfile";
        self::assertSame(
            strlen(Fixture::dockerfile()),
            file_put_contents($path, Fixture::dockerfile()),
        );

        try {
            $result = (new Process($this->root()))->run(
                [
                    PHP_BINARY,
                    '.github/scripts/tests/E2E/Bin/Fixture/Dependencies.php',
                    $path,
                    '--dry-run',
                ],
                check: false,
            );

            self::assertSame(0, $result->code);
            self::assertStringContainsString(
                '**Updates:** 0',
                $result->output,
            );
            self::assertSame('', $result->error);
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    private function execute(string ...$arguments): Result
    {
        $command = [
            PHP_BINARY,
            '.github/scripts/bin/dependencies.php',
        ];
        array_push($command, ...$arguments);

        return (new Process($this->root()))->run($command, check: false);
    }

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }
}
