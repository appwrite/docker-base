<?php

declare(strict_types=1);

namespace DockerBase\Tests\E2E\Bin;

use DockerBase\Command\Process;
use DockerBase\Command\Result;
use PHPUnit\Framework\TestCase;

final class ParityTest extends TestCase
{
    public function test_verifies_executed_contracts_at_the_process_boundary(): void
    {
        [$directory, $manifest, $results] = $this->evidence();

        try {
            $result = $this->execute($results, $manifest);

            self::assertSame(0, $result->code);
            self::assertSame(
                'Verified 1 parity contracts.' . PHP_EOL,
                $result->output,
            );
            self::assertSame('', $result->error);
        } finally {
            $this->remove($directory);
        }
    }

    public function test_rejects_unexecuted_contracts_at_the_process_boundary(): void
    {
        [$directory, $manifest, $results] = $this->evidence(false);

        try {
            $result = $this->execute($results, $manifest);

            self::assertSame(1, $result->code);
            self::assertSame('', $result->output);
            self::assertSame(
                'Error: Mapped parity test '
                    . 'Example\\ContractTest::test_contract '
                    . 'was not executed'
                    . PHP_EOL,
                $result->error,
            );
        } finally {
            $this->remove($directory);
        }
    }

    public function test_rejects_invalid_arguments_at_the_process_boundary(): void
    {
        $result = $this->execute();

        self::assertSame(2, $result->code);
        self::assertSame('', $result->output);
        self::assertSame(
            'Usage: parity.php RESULTS [MANIFEST]' . PHP_EOL,
            $result->error,
        );
    }

    private function execute(string ...$arguments): Result
    {
        $command = [
            PHP_BINARY,
            '.github/scripts/bin/parity.php',
        ];
        array_push($command, ...$arguments);

        return (new Process($this->root()))->run($command, check: false);
    }

    /**
     * @return array{string, string, string}
     */
    private function evidence(bool $executed = true): array
    {
        $directory = sys_get_temp_dir()
            . '/docker-base-process-evidence-'
            . bin2hex(random_bytes(8));
        if (! mkdir($directory)) {
            self::fail(
                "Unable to create temporary directory: {$directory}",
            );
        }
        $manifest = "{$directory}/manifest.json";
        $results = "{$directory}/results.xml";
        $manifestContent = json_encode(
            [
                'baseline' => ['total' => 1],
                'contracts' => [[
                    'php' => 'Example\\ContractTest::test_contract',
                ]],
            ],
            JSON_THROW_ON_ERROR,
        );
        self::assertSame(
            strlen($manifestContent),
            file_put_contents($manifest, $manifestContent),
        );
        $case = $executed
            ? '<testcase class="Example\\ContractTest" '
                . 'name="test_contract"/>'
            : '';
        $resultsContent = '<?xml version="1.0"?>'
            . "<testsuites><testsuite>{$case}</testsuite></testsuites>";
        self::assertSame(
            strlen($resultsContent),
            file_put_contents($results, $resultsContent),
        );

        return [$directory, $manifest, $results];
    }

    private function remove(string $directory): void
    {
        unlink("{$directory}/manifest.json");
        unlink("{$directory}/results.xml");
        rmdir($directory);
    }

    private function root(): string
    {
        return dirname(__DIR__, 5);
    }
}
