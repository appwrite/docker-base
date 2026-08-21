<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Parity;

use DockerBase\Parity\Exception;
use DockerBase\Parity\Verifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VerifierTest extends TestCase
{
    public function test_accepts_an_executed_passing_mapped_test(): void
    {
        [$directory, $manifest, $results] = $this->evidence();

        try {
            self::assertSame(
                1,
                (new Verifier($manifest, $results))->verify(),
            );
        } finally {
            $this->remove($directory);
        }
    }

    public function test_rejects_a_mapped_test_that_was_not_executed(): void
    {
        [$directory, $manifest, $results] = $this->evidence(
            executed: false,
        );

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('was not executed');

            (new Verifier($manifest, $results))->verify();
        } finally {
            $this->remove($directory);
        }
    }

    #[DataProvider('unsuccessfulStates')]
    public function test_rejects_an_unsuccessful_mapped_test(
        string $state,
    ): void {
        [$directory, $manifest, $results] = $this->evidence($state);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage($state);

            (new Verifier($manifest, $results))->verify();
        } finally {
            $this->remove($directory);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsuccessfulStates(): iterable
    {
        yield 'error' => ['error'];
        yield 'failed' => ['failed'];
        yield 'skipped' => ['skipped'];
    }

    /**
     * @return array{string, string, string}
     */
    private function evidence(
        string $state = 'passed',
        bool $executed = true,
    ): array {
        $directory = sys_get_temp_dir()
            . '/docker-base-evidence-'
            . bin2hex(random_bytes(8));
        if (! mkdir($directory)) {
            self::fail(
                "Unable to create temporary directory: {$directory}",
            );
        }
        $manifest = "{$directory}/manifest.json";
        $results = "{$directory}/results.xml";
        $contract = 'Example\\ContractTest::test_contract';
        $manifestContent = json_encode(
            [
                'baseline' => ['total' => 1],
                'contracts' => [['php' => $contract]],
            ],
            JSON_THROW_ON_ERROR,
        );
        self::assertSame(
            strlen($manifestContent),
            file_put_contents($manifest, $manifestContent),
        );

        $case = '';
        if ($executed) {
            $element = $state === 'failed' ? 'failure' : $state;
            $failure = $state === 'passed'
                ? ''
                : "<{$element}/>";
            $case = '<testcase class="Example\\ContractTest" '
                . 'name="test_contract">'
                . $failure
                . '</testcase>';
        }
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
}
