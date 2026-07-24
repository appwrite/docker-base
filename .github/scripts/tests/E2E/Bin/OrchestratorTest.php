<?php

declare(strict_types=1);

namespace DockerBase\Tests\E2E\Bin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrchestratorTest extends TestCase
{
    /**
     * @param list<string> $arguments
     */
    #[DataProvider('operations')]
    public function test_non_validate_operations_do_not_read_standard_input(
        array $arguments,
    ): void {
        [$process, $pipes] = $this->start($arguments);

        try {
            self::assertTrue(
                $this->exits($process),
                implode(' ', $arguments) . ' waited for standard input',
            );
        } finally {
            $this->close($process, $pipes);
        }
    }

    public function test_validate_pull_reads_standard_input(): void
    {
        [$process, $pipes] = $this->start([
            'validate-pull',
            'automation/dependencies-1-1',
            str_repeat('a', 40),
            str_repeat('b', 40),
        ]);

        try {
            usleep(100_000);
            self::assertTrue(
                proc_get_status($process)['running'],
                'validate-pull exited before standard input was available',
            );
            self::assertSame(2, fwrite($pipes[0], '{}'));
            fclose($pipes[0]);
            unset($pipes[0]);
            self::assertTrue(
                $this->exits($process),
                'validate-pull did not consume standard input',
            );
        } finally {
            $this->close($process, $pipes);
        }
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function operations(): iterable
    {
        yield 'recover' => [['recover', 'unexpected']];
        yield 'merge' => [['merge']];
        yield 'prepare' => [['prepare']];
        yield 'wait' => [['wait']];
        yield 'publish' => [['publish']];
        yield 'wait-checks' => [['wait-checks']];
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{
     *     resource,
     *     array<int, resource>
     * }
     */
    private function start(array $arguments): array
    {
        $pipes = [];
        $process = proc_open(
            [
                PHP_BINARY,
                '.github/scripts/bin/orchestrator.php',
                ...$arguments,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->root(),
            [
                'GITHUB_API_VERSION' => '2026-03-10',
                'GITHUB_OUTPUT' => sys_get_temp_dir()
                    . '/docker-base-orchestrator-output',
                'GITHUB_REPOSITORY' => 'appwrite/docker-base',
            ],
            ['bypass_shell' => true],
        );
        if (! is_resource($process)) {
            self::fail('Unable to start orchestrator process');
        }

        return [$process, $pipes];
    }

    /**
     * @param resource $process
     */
    private function exits(mixed $process): bool
    {
        $deadline = microtime(true) + 1;
        do {
            if (! proc_get_status($process)['running']) {
                return true;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     */
    private function close(mixed $process, array $pipes): void
    {
        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process);
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }
}
