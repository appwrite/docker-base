<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Exception;
use DockerBase\Dependency\Release;
use DockerBase\Dependency\Selector;
use DockerBase\Dependency\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Selector::class)]
#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    public function test_parses_only_exact_stable_versions(): void
    {
        foreach (['1.2.3', 'v1.2.3'] as $spelling) {
            $version = Version::parse($spelling);
            self::assertSame(true, $version instanceof Version);
            self::assertSame('1', $version->major);
            self::assertSame('2', $version->minor);
            self::assertSame('3', $version->patch);
        }

        $large = Version::parse('18446744073709551616.2.3');
        self::assertSame(true, $large instanceof Version);
        self::assertSame('18446744073709551616', $large->major);

        foreach ([
            'V1.2.3',
            '01.2.3',
            '1.02.3',
            '1.2.03',
            'v00.0.0',
            '1.2',
            '1.2.3.4',
            'release-1.2.3',
            '1.2.3RC1',
            'v1.2.3-beta.1',
        ] as $spelling) {
            self::assertSame(null, Version::parse($spelling));
        }
    }

    public function test_selects_semantic_maximum_not_lexical_maximum(): void
    {
        self::assertSame(
            '1.10.0',
            (new Selector())->select(
                self::release('1.2.0'),
                self::releases(['1.2.9', '1.10.0', '1.9.12']),
            )->version,
        );
        self::assertSame(
            '1.18446744073709551616.0',
            (new Selector())->select(
                self::release('1.18446744073709551615.0'),
                self::releases(['1.18446744073709551616.0']),
            )->version,
        );
    }

    public function test_selects_minor_and_patch_updates(): void
    {
        $selector = new Selector();

        self::assertSame(
            '1.3.0',
            $selector->select(
                self::release('1.2.3'),
                self::releases(['1.2.4', '1.3.0']),
            )->version,
        );
        self::assertSame(
            '1.2.4',
            $selector->select(
                self::release('1.2.3'),
                self::releases(['1.2.4']),
            )->version,
        );
    }

    public function test_ignores_higher_major(): void
    {
        self::assertSame(
            '1.2.3',
            (new Selector())->select(
                self::release('1.2.3'),
                self::releases(['2.0.0', 'v3.4.5']),
            )->version,
        );
    }

    public function test_ignores_prereleases(): void
    {
        self::assertSame(
            '1.2.4',
            (new Selector())->select(
                self::release('1.2.3'),
                self::releases(['1.2.4RC1', 'v1.3.0-beta.1', '1.2.4']),
            )->version,
        );
    }

    public function test_never_downgrades(): void
    {
        self::assertSame(
            '1.5.0',
            (new Selector())->select(
                self::release('1.5.0'),
                self::releases(['1.4.9', '1.5.0', '2.0.0']),
            )->version,
        );
    }

    public function test_preserves_selected_upstream_prefix(): void
    {
        $selector = new Selector();

        self::assertSame(
            'v1.3.0',
            $selector->select(
                self::release('1.2.3'),
                self::releases(['v1.3.0']),
            )->version,
        );
        self::assertSame(
            '1.3.0',
            $selector->select(
                self::release('v1.2.3'),
                self::releases(['1.3.0']),
            )->version,
        );
    }

    public function test_prefers_current_prefix_for_equivalent_tags(): void
    {
        $selector = new Selector();
        $releases = self::releases(['1.3.0', 'v1.3.0']);

        self::assertSame(
            'v1.3.0',
            $selector->select(self::release('v1.2.3'), $releases)->version,
        );
        self::assertSame(
            '1.3.0',
            $selector->select(self::release('1.2.3'), $releases)->version,
        );
    }

    public function test_rejects_invalid_current_version(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid current version: 1.2.3RC1');

        (new Selector())->select(
            self::release('1.2.3RC1'),
            self::releases(['1.2.4']),
        );
    }

    private static function release(string $version): Release
    {
        return new Release($version, null);
    }

    /**
     * @param list<string> $versions
     * @return list<Release>
     */
    private static function releases(array $versions): array
    {
        return array_map(
            static fn (string $version): Release => self::release($version),
            $versions,
        );
    }
}
