<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DockerBase\Automation\Version;
use DockerBase\Automation\VersionInvalidException;
use DockerBase\Automation\VersionMissingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    #[Test]
    public function test_parses_unbounded_canonical_components_as_strings(): void
    {
        $version = Version::parse(
            '18446744073709551616.9223372036854775808.99999999999999999999',
        );

        self::assertNotNull($version);
        self::assertSame('18446744073709551616', $version->major);
        self::assertSame('9223372036854775808', $version->minor);
        self::assertSame('99999999999999999999', $version->patch);
    }

    #[Test]
    public function test_orders_stable_versions_semantically(): void
    {
        self::assertSame(
            ['1.2.99', '1.10.0', '2.0.0'],
            array_map(
                static fn (Version $version): string => (string) $version,
                Version::stable([
                    '1.10.0',
                    '2.0.0',
                    '1.2.99',
                    '1.10.0',
                ]),
            ),
        );
    }

    #[Test]
    public function test_orders_adjacent_components_above_integer_range(): void
    {
        self::assertSame(
            [
                '1.18446744073709551616.0',
                '1.18446744073709551617.0',
                '18446744073709551616.0.0',
            ],
            array_map(
                static fn (Version $version): string => (string) $version,
                Version::stable([
                    '18446744073709551616.0.0',
                    '1.18446744073709551617.0',
                    '1.18446744073709551616.0',
                ]),
            ),
        );
    }

    #[Test]
    public function test_ignores_nonstable_and_prefixed_tags(): void
    {
        self::assertSame(
            ['0.0.0', '12.34.56'],
            array_map(
                static fn (Version $version): string => (string) $version,
                Version::stable([
                    '0.0.0',
                    '12.34.56',
                    'v12.34.57',
                    '12.34.57-rc.1',
                    '12.34',
                    '12.34.57+build',
                    '01.2.3',
                    '1.02.3',
                    '1.2.03',
                    '',
                ]),
            ),
        );
    }

    #[Test]
    public function test_next_patch_uses_semantic_maximum_of_all_remote_tags(): void
    {
        self::assertSame(
            '2.0.1',
            (string) Version::next([
                '1.999.999',
                '2.0.0',
                'v9.0.0',
                '9.0.0-rc.1',
            ]),
        );
    }

    #[Test]
    public function test_next_patch_carries_without_an_integer_ceiling(): void
    {
        self::assertSame(
            '1.2.100000000000000000000',
            (string) Version::next([
                '1.2.99999999999999999999',
            ]),
        );
    }

    #[Test]
    public function test_latest_version_requires_a_stable_remote_tag(): void
    {
        $this->expectException(VersionMissingException::class);

        Version::latest(['v1.0.0', '1.0.0-rc.1']);
    }

    #[Test]
    public function test_resumes_newest_tag_newer_than_latest_release(): void
    {
        $tags = ['1.4.1', '1.4.2', '1.4.3', '1.4.4'];
        $releases = ['1.4.1', '1.4.3'];

        self::assertSame(
            '1.4.4',
            (string) Version::unreleased($tags, $releases),
        );
        self::assertSame(
            '1.4.4',
            (string) Version::candidate($tags, $releases),
        );
    }

    #[Test]
    public function test_ignores_unreleased_holes_older_than_latest_release(): void
    {
        self::assertSame(
            null,
            Version::unreleased(
                ['1.4.1', '1.4.2', '1.4.3'],
                ['1.4.1', '1.4.3'],
            ),
        );
    }

    #[Test]
    public function test_computes_new_patch_when_every_newer_tag_is_released(): void
    {
        self::assertSame(
            '1.4.5',
            (string) Version::candidate(
                ['1.4.3', '1.4.4'],
                ['1.4.3', '1.4.4'],
            ),
        );
    }

    #[Test]
    public function test_recomputes_after_collision_from_refreshed_remote_tags(): void
    {
        self::assertSame(
            '1.4.8',
            (string) Version::afterCollision(
                ['1.4.4', '1.4.5', '1.4.7'],
                '1.4.5',
            ),
        );
    }

    #[Test]
    public function test_recomputes_unbounded_patch_after_collision(): void
    {
        self::assertSame(
            '1.4.18446744073709551618',
            (string) Version::afterCollision(
                [
                    '1.4.18446744073709551616',
                    '1.4.18446744073709551617',
                ],
                '1.4.18446744073709551616',
            ),
        );
    }

    #[Test]
    public function test_rejects_malformed_collision_tag(): void
    {
        $this->expectException(VersionInvalidException::class);

        Version::afterCollision(['1.4.4'], 'v1.4.5');
    }
}
