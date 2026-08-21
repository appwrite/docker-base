<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Catalog;

final readonly class Fixture
{
    public const array CURRENT = [
        'brotli' => '0.18.3',
        'imagick' => '3.8.1',
        'lz4' => '0.6.0',
        'maxminddb' => 'v1.13.1',
        'mongodb' => '2.2.1',
        'protobuf' => '5.34.0',
        'redis' => '6.3.0',
        'scrypt' => '2.0.1',
        'snappy' => '0.2.3',
        'swoole' => 'v6.2.0',
        'xdebug' => '3.5.1',
        'yaml' => '2.3.0',
        'zstd' => '0.15.2',
    ];

    public const array DECLARATIONS = [
        ['brotli', 'PHP_BROTLI_VERSION'],
        ['imagick', 'PHP_IMAGICK_VERSION'],
        ['lz4', 'PHP_LZ4_VERSION'],
        ['maxminddb', 'PHP_MAXMINDDB_VERSION'],
        ['mongodb', 'PHP_MONGODB_VERSION'],
        ['protobuf', 'PHP_PROTOBUF_VERSION'],
        ['redis', 'PHP_REDIS_VERSION'],
        ['scrypt', 'PHP_SCRYPT_VERSION'],
        ['snappy', 'PHP_SNAPPY_VERSION'],
        ['swoole', 'PHP_SWOOLE_VERSION'],
        ['yaml', 'PHP_YAML_VERSION'],
        ['zstd', 'PHP_ZSTD_VERSION'],
    ];

    public const array EXPECTED_DOCKERFILE_DECLARATIONS = [
        'BASE_IMAGE',
        'PHP_BROTLI_VERSION',
        'PHP_IMAGICK_VERSION',
        'PHP_LZ4_VERSION',
        'PHP_MAXMINDDB_VERSION',
        'PHP_MONGODB_VERSION',
        'PHP_PROTOBUF_VERSION',
        'PHP_REDIS_VERSION',
        'PHP_SCRYPT_VERSION',
        'PHP_SNAPPY_VERSION',
        'PHP_SWOOLE_VERSION',
        'PHP_XDEBUG_VERSION',
        'PHP_YAML_VERSION',
        'PHP_ZSTD_VERSION',
    ];

    public const string OLD_DIGEST = 'sha256:1111111111111111111111111111111111111111111111111111111111111111';

    public const string NEW_DIGEST = 'sha256:2222222222222222222222222222222222222222222222222222222222222222';

    public static function dockerfile(): string
    {
        $lines = [
            'ARG BASE_IMAGE="' . Catalog::BASE . '@' . self::OLD_DIGEST . '"',
            '',
            'FROM $BASE_IMAGE AS compile',
            '',
            'ENV \\',
        ];

        foreach (self::DECLARATIONS as $index => [$name, $variable]) {
            $suffix = $index < count(self::DECLARATIONS) - 1 ? ' \\' : '';
            $lines[] = "    {$variable}=\"" . self::CURRENT[$name] . "\"{$suffix}";
        }

        array_push(
            $lines,
            '',
            '# References should never be rewritten:',
            'RUN echo "$PHP_REDIS_VERSION"',
            '',
            'FROM compile AS xdebug-build',
            '',
            'ENV PHP_XDEBUG_VERSION="' . self::CURRENT['xdebug'] . '"',
            '',
        );

        return implode("\n", $lines);
    }

    public static function gitTags(string ...$spellings): string
    {
        $output = '';
        foreach ($spellings as $spelling) {
            $output .= str_repeat('a', 40) . "\trefs/tags/{$spelling}\n";
        }

        return $output;
    }

    /**
     * @param array{string, string} ...$releases
     */
    public static function peclReleases(array ...$releases): string
    {
        $entries = '';
        foreach ($releases as [$version, $state]) {
            $entries .= "<r><v>{$version}</v><s>{$state}</s></r>";
        }

        return '<?xml version="1.0"?>'
            . '<a xmlns="http://pear.php.net/dtd/rest.allreleases">'
            . "<p>protobuf</p>{$entries}</a>";
    }
}
