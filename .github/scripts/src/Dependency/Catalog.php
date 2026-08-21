<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

use DockerBase\Dependency\Source\Git;
use DockerBase\Dependency\Source\PECL;

final readonly class Catalog
{
    public const string BASE = 'php:8.5-alpine';

    public const string PECL_RELEASES = 'https://pecl.php.net/rest/r/protobuf/allreleases.xml';

    /**
     * @param list<Dependency> $dependencies
     */
    private function __construct(
        private array $dependencies,
    ) {
    }

    public static function create(): self
    {
        return new self([
            new Dependency(
                'brotli',
                'PHP_BROTLI_VERSION',
                'PHP_BROTLI_COMMIT',
                new Git('https://github.com/kjdev/php-ext-brotli.git'),
            ),
            new Dependency(
                'imagick',
                'PHP_IMAGICK_VERSION',
                'PHP_IMAGICK_COMMIT',
                new Git('https://github.com/imagick/imagick'),
            ),
            new Dependency(
                'lz4',
                'PHP_LZ4_VERSION',
                'PHP_LZ4_COMMIT',
                new Git('https://github.com/kjdev/php-ext-lz4.git'),
            ),
            new Dependency(
                'maxminddb',
                'PHP_MAXMINDDB_VERSION',
                'PHP_MAXMINDDB_COMMIT',
                new Git('https://github.com/maxmind/MaxMind-DB-Reader-php.git'),
            ),
            new Dependency(
                'mongodb',
                'PHP_MONGODB_VERSION',
                'PHP_MONGODB_COMMIT',
                new Git('https://github.com/mongodb/mongo-php-driver.git'),
            ),
            new Dependency(
                'protobuf',
                'PHP_PROTOBUF_VERSION',
                'PHP_PROTOBUF_CHECKSUM',
                new PECL(self::PECL_RELEASES),
            ),
            new Dependency(
                'redis',
                'PHP_REDIS_VERSION',
                'PHP_REDIS_COMMIT',
                new Git('https://github.com/phpredis/phpredis.git'),
            ),
            new Dependency(
                'scrypt',
                'PHP_SCRYPT_VERSION',
                'PHP_SCRYPT_COMMIT',
                new Git('https://github.com/DomBlack/php-scrypt.git'),
            ),
            new Dependency(
                'snappy',
                'PHP_SNAPPY_VERSION',
                'PHP_SNAPPY_COMMIT',
                new Git('https://github.com/kjdev/php-ext-snappy.git'),
            ),
            new Dependency(
                'swoole',
                'PHP_SWOOLE_VERSION',
                'PHP_SWOOLE_COMMIT',
                new Git('https://github.com/swoole/swoole-src.git'),
            ),
            new Dependency(
                'xdebug',
                'PHP_XDEBUG_VERSION',
                'PHP_XDEBUG_COMMIT',
                new Git('https://github.com/xdebug/xdebug'),
            ),
            new Dependency(
                'yaml',
                'PHP_YAML_VERSION',
                'PHP_YAML_COMMIT',
                new Git('https://github.com/php/pecl-file_formats-yaml'),
            ),
            new Dependency(
                'zstd',
                'PHP_ZSTD_VERSION',
                'PHP_ZSTD_COMMIT',
                new Git('https://github.com/kjdev/php-ext-zstd.git'),
            ),
        ]);
    }

    /**
     * @return list<Dependency>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }
}
