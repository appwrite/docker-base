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
        ['brotli', 'PHP_BROTLI_VERSION', 'PHP_BROTLI_COMMIT'],
        ['imagick', 'PHP_IMAGICK_VERSION', 'PHP_IMAGICK_COMMIT'],
        ['lz4', 'PHP_LZ4_VERSION', 'PHP_LZ4_COMMIT'],
        ['maxminddb', 'PHP_MAXMINDDB_VERSION', 'PHP_MAXMINDDB_COMMIT'],
        ['mongodb', 'PHP_MONGODB_VERSION', 'PHP_MONGODB_COMMIT'],
        ['protobuf', 'PHP_PROTOBUF_VERSION', 'PHP_PROTOBUF_CHECKSUM'],
        ['redis', 'PHP_REDIS_VERSION', 'PHP_REDIS_COMMIT'],
        ['scrypt', 'PHP_SCRYPT_VERSION', 'PHP_SCRYPT_COMMIT'],
        ['snappy', 'PHP_SNAPPY_VERSION', 'PHP_SNAPPY_COMMIT'],
        ['swoole', 'PHP_SWOOLE_VERSION', 'PHP_SWOOLE_COMMIT'],
        ['yaml', 'PHP_YAML_VERSION', 'PHP_YAML_COMMIT'],
        ['zstd', 'PHP_ZSTD_VERSION', 'PHP_ZSTD_COMMIT'],
    ];

    public const array EXPECTED_DOCKERFILE_DECLARATIONS = [
        'BASE_IMAGE',
        'PHP_BROTLI_COMMIT',
        'PHP_BROTLI_VERSION',
        'PHP_IMAGICK_COMMIT',
        'PHP_IMAGICK_VERSION',
        'PHP_LZ4_COMMIT',
        'PHP_LZ4_VERSION',
        'PHP_MAXMINDDB_COMMIT',
        'PHP_MAXMINDDB_VERSION',
        'PHP_MONGODB_COMMIT',
        'PHP_MONGODB_VERSION',
        'PHP_PROTOBUF_CHECKSUM',
        'PHP_PROTOBUF_VERSION',
        'PHP_REDIS_COMMIT',
        'PHP_REDIS_VERSION',
        'PHP_SCRYPT_COMMIT',
        'PHP_SCRYPT_VERSION',
        'PHP_SNAPPY_COMMIT',
        'PHP_SNAPPY_VERSION',
        'PHP_SWOOLE_COMMIT',
        'PHP_SWOOLE_VERSION',
        'PHP_XDEBUG_COMMIT',
        'PHP_XDEBUG_VERSION',
        'PHP_YAML_COMMIT',
        'PHP_YAML_VERSION',
        'PHP_ZSTD_COMMIT',
        'PHP_ZSTD_VERSION',
    ];

    public const string OLD_CHECKSUM = 'c0ffee00000000000000000000000000000000000000000000000000000000ee';

    public const string NEW_CHECKSUM = 'facade11111111111111111111111111111111111111111111111111111111dd';

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

        foreach (self::DECLARATIONS as $index => [$name, $variable, $reference]) {
            $last = $index === count(self::DECLARATIONS) - 1;
            $lines[] = "    {$variable}=\"" . self::CURRENT[$name] . '" \\';
            $lines[] = "    {$reference}=\"" . self::reference($name) . '"'
                . ($last ? '' : ' \\');
        }

        array_push(
            $lines,
            '',
            '# References should never be rewritten:',
            'RUN echo "$PHP_REDIS_VERSION"',
            '',
            'FROM compile AS xdebug-build',
            '',
            'ENV PHP_XDEBUG_VERSION="' . self::CURRENT['xdebug'] . '" \\',
            '    PHP_XDEBUG_COMMIT="' . self::reference('xdebug') . '"',
            '',
        );

        return implode("\n", $lines);
    }

    public static function reference(string $name): string
    {
        return $name === 'protobuf'
            ? self::checksum(self::CURRENT[$name])
            : self::commit(self::CURRENT[$name]);
    }

    public static function tarball(string $version): string
    {
        return "protobuf-{$version} tarball";
    }

    public static function checksum(string $version): string
    {
        return hash('sha256', self::tarball($version));
    }

    public static function commit(string $spelling): string
    {
        return substr(hash('sha256', "tag:{$spelling}"), 0, 40);
    }

    public static function gitTags(string ...$spellings): string
    {
        $output = '';
        foreach ($spellings as $spelling) {
            $output .= self::annotation($spelling)
                . "\trefs/tags/{$spelling}\n"
                . self::commit($spelling)
                . "\trefs/tags/{$spelling}^{}\n";
        }

        return $output;
    }

    public static function annotation(string $spelling): string
    {
        return substr(hash('sha256', "annotation:{$spelling}"), 0, 40);
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
