<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Application;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Reporter;
use DockerBase\Dependency\Resolver;
use DockerBase\Dependency\Selector;
use DockerBase\Dependency\Updater;
use JsonException;
use PHPUnit\Framework\TestCase;

final class ParityTest extends TestCase
{
    public function test_matches_the_recorded_python_bytes(): void
    {
        $content = Fixture::dockerfile();
        $updated = $this->application(new Discovery(
            digest: Fixture::NEW_DIGEST,
            releases: [
                'redis' => [Fixture::CURRENT['redis'], '6.3.1'],
                'swoole' => [
                    Fixture::CURRENT['swoole'],
                    '6.2.1',
                    'v6.2.1',
                ],
            ],
            pecl: [
                [Fixture::CURRENT['protobuf'], 'stable'],
                ['5.34.1', 'stable'],
                ['5.35.0RC1', 'beta'],
            ],
        ))->plan($content);
        $noop = $this->application(new Discovery())->plan($content);

        $directory = sys_get_temp_dir()
            . '/docker-base-parity-'
            . bin2hex(random_bytes(8));
        if (! mkdir($directory)) {
            self::fail("Unable to create parity directory: {$directory}");
        }
        $path = "{$directory}/Dockerfile";
        $sibling = "{$directory}/keep.txt";
        file_put_contents($path, $content);
        file_put_contents($sibling, 'unchanged');

        try {
            $dryDiscovery = new Discovery(digest: Fixture::NEW_DIGEST);
            $dry = (new Updater(
                $this->application($dryDiscovery),
            ))->update($path, true);
            $selected = [];
            foreach ($updated->changes as $change) {
                $selected[$change->name] = $change->latest;
            }
            ksort($selected, SORT_STRING);

            $result = [
                'dry_changed' => $dry->changed(),
                'dry_dockerfile' => base64_encode(
                    (string) file_get_contents($path),
                ),
                'dry_sibling' => base64_encode(
                    (string) file_get_contents($sibling),
                ),
                'noop' => base64_encode($noop->content),
                'noop_report' => base64_encode(
                    (new Reporter())->render($noop),
                ),
                'selected' => $selected,
                'update' => base64_encode($updated->content),
                'update_report' => base64_encode(
                    (new Reporter())->render($updated),
                ),
            ];
            ksort($result, SORT_STRING);
            $record = json_encode(
                $result,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );

            $expected = $this->expected();
            self::assertSame($expected['bytes'], strlen($record));
            self::assertSame(
                $expected['sha256'],
                hash('sha256', $record),
            );
        } finally {
            unlink($path);
            unlink($sibling);
            rmdir($directory);
        }
    }

    private function application(Discovery $discovery): Application
    {
        return new Application(
            Catalog::create(),
            new Dockerfile(),
            new Resolver($discovery, $discovery),
            new Selector(),
        );
    }

    /**
     * @return array{bytes: int, sha256: string}
     */
    private function expected(): array
    {
        $document = file_get_contents(
            dirname(__DIR__, 2) . '/equivalence.json',
        );
        if (! is_string($document)) {
            self::fail('Unable to read parity evidence');
        }
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
        $expected = is_array($equivalence)
            ? ($equivalence['deterministic'] ?? null)
            : null;
        if (
            ! is_array($expected)
            || ! is_int($expected['bytes'] ?? null)
            || ! is_string($expected['sha256'] ?? null)
        ) {
            self::fail('Parity evidence is invalid');
        }

        return [
            'bytes' => $expected['bytes'],
            'sha256' => $expected['sha256'],
        ];
    }
}
