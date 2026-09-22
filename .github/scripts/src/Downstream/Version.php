<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Version
{
    public const string PATTERN = '[0-9]+\.[0-9]+\.[0-9]+';

    public static function isExact(string $spelling): bool
    {
        return preg_match('/\A' . self::PATTERN . '\z/', $spelling) === 1;
    }
}
