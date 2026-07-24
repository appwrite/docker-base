<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Application;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Console;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Program;
use DockerBase\Dependency\Reporter;
use DockerBase\Dependency\Resolver;
use DockerBase\Dependency\Selector;
use DockerBase\Dependency\Updater;
use PHPUnit\Framework\TestCase;

final class ProgramTest extends TestCase
{
    public function test_returns_help_on_standard_output(): void
    {
        $result = $this->program('/tmp/Dockerfile')->execute(['--help']);

        self::assertSame(0, $result->code);
        self::assertSame(Console::USAGE . PHP_EOL, $result->output);
        self::assertSame('', $result->error);
    }

    public function test_returns_usage_errors_on_standard_error(): void
    {
        $result = $this->program('/tmp/Dockerfile')->execute(['--unknown']);

        self::assertSame(2, $result->code);
        self::assertSame('', $result->output);
        self::assertSame(
            "Error: Unknown dependency updater argument '--unknown'"
                . PHP_EOL,
            $result->error,
        );
    }

    public function test_returns_domain_errors_without_a_stack_trace(): void
    {
        $result = $this->program('/tmp/not-a-dockerfile')
            ->execute([]);

        self::assertSame(1, $result->code);
        self::assertSame('', $result->output);
        self::assertSame(
            'Error: The update target must be named Dockerfile' . PHP_EOL,
            $result->error,
        );
        self::assertStringNotContainsString('Stack trace', $result->error);
    }

    public function test_returns_a_success_report_on_standard_output(): void
    {
        $directory = sys_get_temp_dir()
            . '/docker-base-program-'
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
            $result = $this->program($path)->execute(['--dry-run']);

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

    private function program(string $path): Program
    {
        $discovery = new Discovery();

        return new Program(
            new Console(
                new Updater(
                    new Application(
                        Catalog::create(),
                        new Dockerfile(),
                        new Resolver($discovery, $discovery),
                        new Selector(),
                    ),
                ),
                new Reporter(),
                $path,
            ),
        );
    }
}
