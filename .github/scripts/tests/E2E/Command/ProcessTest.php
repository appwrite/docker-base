<?php

declare(strict_types=1);

namespace DockerBase\Tests\E2E\Command;

use DockerBase\Command\Exception;
use DockerBase\Command\Process;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Exception::class)]
#[CoversClass(Process::class)]
final class ProcessTest extends TestCase
{
    public function testCapturesSuccessfulOutput(): void
    {
        $result = (new Process())->run([
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, \'ready\');',
        ]);

        self::assertSame(0, $result->code);
        self::assertSame('ready', $result->output);
        self::assertSame('', $result->error);
    }

    public function testReturnsAnUncheckedFailure(): void
    {
        $result = (new Process())->run(
            [
                PHP_BINARY,
                '-r',
                'fwrite(STDERR, \'failed\'); exit(7);',
            ],
            check: false,
        );

        self::assertSame(7, $result->code);
        self::assertSame('', $result->output);
        self::assertSame('failed', $result->error);
    }

    public function testThrowsACheckedFailureWithItsResult(): void
    {
        try {
            (new Process())->run([
                PHP_BINARY,
                '-r',
                'fwrite(STDERR, \'failed\'); exit(9);',
            ]);
            self::fail('A checked command failure must throw');
        } catch (Exception $exception) {
            $result = $exception->result;
            if ($result === null) {
                self::fail('A checked command failure must contain its result');
            }

            self::assertSame(9, $result->code);
            self::assertSame('failed', $result->error);
        }
    }

    public function testExplicitEnvironmentReplacesInheritedEnvironment(): void
    {
        putenv('DOCKER_BASE_INHERITED_SENTINEL=should-not-leak');

        try {
            $result = (new Process(environment: ['DOCKER_BASE_MARKER' => 'present']))->run([
                PHP_BINARY,
                '-r',
                'echo getenv("DOCKER_BASE_MARKER") . "|" . (getenv("DOCKER_BASE_INHERITED_SENTINEL") ?: "absent");',
            ]);
        } finally {
            putenv('DOCKER_BASE_INHERITED_SENTINEL');
        }

        self::assertSame('present|absent', $result->output);
    }

    public function testHonorsAndRestoresWorkingDirectoryWithoutExplicitEnvironment(): void
    {
        $directory = sys_get_temp_dir();
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            self::fail('Unable to determine the current working directory');
        }

        $result = (new Process($directory))->run([
            PHP_BINARY,
            '-r',
            'echo getcwd();',
        ]);

        self::assertSame(realpath($directory), $result->output);
        self::assertSame($workingDirectory, getcwd());
    }

    public function testRestoresWorkingDirectoryWhenCommandFails(): void
    {
        $directory = sys_get_temp_dir();
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            self::fail('Unable to determine the current working directory');
        }

        try {
            (new Process($directory))->run([
                PHP_BINARY,
                '-r',
                'exit(11);',
            ]);
            self::fail('A checked command failure must throw');
        } catch (Exception $exception) {
            self::assertSame(11, $exception->result?->code);
        }

        self::assertSame($workingDirectory, getcwd());
    }

    public function testRejectsAnInvalidWorkingDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/docker-base-missing-directory';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Unable to start command in directory: {$directory}");

        (new Process($directory))->run([PHP_BINARY, '-r', 'echo "ready";']);
    }
}
