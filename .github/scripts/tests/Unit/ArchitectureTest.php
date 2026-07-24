<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureTest extends TestCase
{
    public function test_maps_every_python_contract_to_php(): void
    {
        $document = file_get_contents(
            dirname(__DIR__) . '/equivalence.json',
        );
        self::assertIsString($document);

        try {
            $equivalence = json_decode(
                $document,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }
        self::assertIsArray($equivalence);
        self::assertSame(
            [
                'automation' => 43,
                'dependency' => 35,
                'adapter' => 13,
                'total' => 91,
            ],
            $equivalence['baseline'] ?? null,
        );
        self::assertSame(
            [
                'bytes' => 5477,
                'sha256' => '50736031df39f38434d6c09b455aa651e886a6a79319c951b631df8a49a93a77',
            ],
            $equivalence['deterministic'] ?? null,
        );

        $contracts = $equivalence['contracts'] ?? null;
        self::assertIsArray($contracts);
        self::assertCount(91, $contracts);

        $python = [];
        $php = [];
        $groups = [];
        foreach ($contracts as $contract) {
            self::assertIsArray($contract);
            $pythonContract = $this->value($contract, 'python');
            $phpContract = $this->value($contract, 'php');
            $group = $this->value($contract, 'group');
            $this->value($contract, 'fixture');
            $this->value($contract, 'assertion');
            self::assertArrayNotHasKey('status', $contract);

            $python[] = $pythonContract;
            $php[] = $phpContract;
            $groups[] = $group;

            $method = explode('::', $phpContract);
            self::assertCount(2, $method);
            self::assertTrue(
                class_exists($method[0]),
                "Missing parity class {$method[0]}",
            );
            self::assertTrue(
                method_exists($method[0], $method[1]),
                "Missing parity method {$phpContract}",
            );
        }

        self::assertCount(91, array_unique($python));
        self::assertCount(91, array_unique($php));
        $counts = array_count_values($groups);
        ksort($counts, SORT_STRING);
        self::assertSame(
            [
                'adapter' => 13,
                'automation' => 43,
                'dependency' => 35,
            ],
            $counts,
        );
    }

    public function test_contains_no_python_implementation_or_invocation(): void
    {
        $root = dirname(__DIR__, 4);
        $python = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $relative = substr($path, strlen($root) + 1);
            if (
                str_starts_with($relative, 'vendor/')
                || str_starts_with($relative, '.git/')
            ) {
                continue;
            }
            if (
                $file->getExtension() === 'py'
                || str_contains($relative, '/__pycache__/')
                || str_ends_with($relative, '.pyc')
            ) {
                $python[] = $relative;
            }
        }
        self::assertSame([], $python, 'Python artifacts remain');

        $workflow = file_get_contents(
            $root . '/.github/workflows/dependencies.yml',
        );
        self::assertIsString($workflow);
        self::assertSame(
            0,
            preg_match('/\bpython(?:3)?\b|<<\s*[\'"]?PY\b/i', $workflow),
        );
    }

    public function test_declares_and_preflights_required_php_extensions(): void
    {
        $root = dirname(__DIR__, 4);
        $document = file_get_contents($root . '/composer.json');
        self::assertIsString($document);

        try {
            $composer = json_decode(
                $document,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }
        self::assertIsArray($composer);
        $requirements = $composer['require'] ?? null;
        self::assertIsArray($requirements);

        $extensions = [];
        foreach ($requirements as $package => $constraint) {
            if (! is_string($package) || ! str_starts_with($package, 'ext-')) {
                continue;
            }
            self::assertSame('*', $constraint);
            $extensions[] = substr($package, strlen('ext-'));
        }
        sort($extensions, SORT_STRING);
        self::assertSame(
            [
                'dom',
                'filter',
                'json',
                'libxml',
                'mbstring',
                'openssl',
                'phar',
                'tokenizer',
                'xml',
                'xmlwriter',
            ],
            $extensions,
        );

        $workflow = file_get_contents(
            $root . '/.github/workflows/dependencies.yml',
        );
        self::assertIsString($workflow);
        self::assertSame(
            1,
            preg_match(
                '/\\$required = \\[(.*?)\\];/s',
                $workflow,
                $matched,
            ),
        );
        $preflightBlock = $matched[1] ?? null;
        self::assertIsString($preflightBlock);
        self::assertSame(
            10,
            preg_match_all(
                '/"([a-z][a-z0-9_]*)"/',
                $preflightBlock,
                $preflight,
            ),
        );
        self::assertSame($extensions, $preflight[1]);
    }

    /**
     * @param array<mixed> $contract
     */
    private function value(array $contract, string $field): string
    {
        self::assertArrayHasKey($field, $contract);
        $value = $contract[$field];
        if (! is_string($value)) {
            self::fail("Parity field {$field} must be a string");
        }
        self::assertNotSame('', trim($value));

        return $value;
    }
}
