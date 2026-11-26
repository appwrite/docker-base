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
                'entire',
                'ENTIRE_CLI_VERSION',
                'ENTIRE_CLI_COMMIT',
                new Git('https://github.com/entireio/cli.git'),
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
                'swoole',
                'PHP_SWOOLE_VERSION',
                'PHP_SWOOLE_COMMIT',
                new Git('https://github.com/appwrite-labs/swoole-rs.git'),
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
