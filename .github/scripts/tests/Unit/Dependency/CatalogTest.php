<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Application;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Change;
use DockerBase\Dependency\Dependency;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Exception;
use DockerBase\Dependency\Fetcher;
use DockerBase\Dependency\Pin;
use DockerBase\Dependency\Plan;
use DockerBase\Dependency\Reporter;
use DockerBase\Dependency\Resolver;
use DockerBase\Dependency\Selector;
use DockerBase\Dependency\Source;
use DockerBase\Dependency\Source\Git;
use DockerBase\Dependency\Source\PECL;
use DockerBase\Dependency\Updater;
use DockerBase\Dependency\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Catalog::class)]
#[CoversClass(Dependency::class)]
final class CatalogTest extends TestCase
{
    public function test_defines_all_thirteen_extensions(): void
    {
        $dependencies = Catalog::create()->dependencies();
        $names = array_map(
            static fn (Dependency $dependency): string => $dependency->name,
            $dependencies,
        );
        $expected = array_keys(Fixture::CURRENT);
        sort($names, SORT_STRING);
        sort($expected, SORT_STRING);

        self::assertSame(13, count($dependencies));
        self::assertSame($expected, $names);
    }

    public function test_uses_exact_dockerfile_sources(): void
    {
        $expected = [
            'brotli' => 'https://github.com/kjdev/php-ext-brotli.git',
            'imagick' => 'https://github.com/imagick/imagick',
            'lz4' => 'https://github.com/kjdev/php-ext-lz4.git',
            'maxminddb' => 'https://github.com/maxmind/MaxMind-DB-Reader-php.git',
            'mongodb' => 'https://github.com/mongodb/mongo-php-driver.git',
            'redis' => 'https://github.com/phpredis/phpredis.git',
            'scrypt' => 'https://github.com/DomBlack/php-scrypt.git',
            'snappy' => 'https://github.com/kjdev/php-ext-snappy.git',
            'swoole' => 'https://github.com/swoole/swoole-src.git',
            'xdebug' => 'https://github.com/xdebug/xdebug',
            'yaml' => 'https://github.com/php/pecl-file_formats-yaml',
            'zstd' => 'https://github.com/kjdev/php-ext-zstd.git',
        ];
        $actual = [];
        $protobuf = null;

        foreach (Catalog::create()->dependencies() as $dependency) {
            if ($dependency->source instanceof Git) {
                $actual[$dependency->name] = $dependency->source->url();
            } elseif ($dependency->name === 'protobuf') {
                $protobuf = $dependency;
            }
        }

        self::assertSame($expected, $actual);
        if (! $protobuf instanceof Dependency) {
            self::fail('Expected protobuf dependency');
        }

        self::assertSame(true, $protobuf->source instanceof PECL);
        self::assertSame(Catalog::PECL_RELEASES, $protobuf->source->url());
    }

    public function test_source_definitions_are_immutable(): void
    {
        self::assertSame(true, (new ReflectionClass(Git::class))->isReadOnly());
        self::assertSame(true, (new ReflectionClass(PECL::class))->isReadOnly());
    }

    public function test_domain_classes_are_in_matching_files(): void
    {
        $symbols = [
            Application::class,
            Catalog::class,
            Change::class,
            Dependency::class,
            Dockerfile::class,
            Exception::class,
            Fetcher::class,
            Pin::class,
            Plan::class,
            Reporter::class,
            Resolver::class,
            Selector::class,
            Source::class,
            Git::class,
            PECL::class,
            Updater::class,
            Version::class,
        ];

        foreach ($symbols as $symbol) {
            $reflection = new ReflectionClass($symbol);
            $path = $reflection->getFileName();
            self::assertSame(true, is_string($path));
            self::assertSame(
                $reflection->getShortName() . '.php',
                basename($path),
            );
        }
    }
}
