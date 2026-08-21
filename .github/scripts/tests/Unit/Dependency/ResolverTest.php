<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Exception;
use DockerBase\Dependency\Resolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resolver::class)]
final class ResolverTest extends TestCase
{
    public function test_parses_only_exact_git_version_tags(): void
    {
        $output = Fixture::gitTags(
            '1.2.3',
            'v1.3.0',
            '1.4.0RC1',
            'release-1.5.0',
        ) . str_repeat('b', 40) . "\trefs/heads/1.6.0\n"
            . "malformed\n";

        self::assertSame(
            ['1.2.3', 'v1.3.0'],
            $this->resolver()->git($output),
        );
    }

    public function test_filters_pecl_by_stable_state_and_exact_version(): void
    {
        $document = Fixture::peclReleases(
            ['5.35.0RC1', 'beta'],
            ['5.35.0', 'stable'],
            ['5.34.2', 'stable'],
            ['5.36.0', 'beta'],
            ['v5.35.1', 'stable'],
            ['5.35.2RC1', 'stable'],
        );

        self::assertSame(
            ['5.35.0', '5.34.2', 'v5.35.1'],
            $this->resolver()->pecl($document),
        );
    }

    public function test_rejects_invalid_pecl_xml(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid PECL release XML');

        $this->resolver()->pecl('<not-closed>');
    }

    public function test_rejects_source_with_no_stable_releases(): void
    {
        $catalog = Catalog::create();
        $discovery = new Discovery(
            pecl: [['5.35.0RC1', 'beta']],
        );
        $resolver = new Resolver($discovery, $discovery);
        $protobuf = array_values(array_filter(
            $catalog->dependencies(),
            static fn ($dependency): bool => $dependency->name === 'protobuf',
        ))[0];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'No exact stable releases found for protobuf',
        );

        $resolver->releases($protobuf);
    }

    private function resolver(): Resolver
    {
        $discovery = new Discovery();

        return new Resolver($discovery, $discovery);
    }
}
