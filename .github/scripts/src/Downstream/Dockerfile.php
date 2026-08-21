<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Dockerfile
{
    public const string IMAGE = 'appwrite/base';

    public function bump(string $content, string $selected): Bump
    {
        if (! Version::isExact($selected)) {
            throw new Exception(
                "Base version must be an exact MAJOR.MINOR.PATCH release, got '{$selected}'",
            );
        }

        $image = preg_quote(self::IMAGE, '/');
        $count = preg_match_all(
            "/{$image}:(" . Version::PATTERN . ')/',
            $content,
            $matches,
        );
        if ($count === false || $count === 0) {
            throw new Exception(
                'No ' . self::IMAGE . ' reference found to bump',
            );
        }

        $versions = array_values(array_unique($matches[1]));
        if (count($versions) !== 1) {
            sort($versions, SORT_STRING);

            throw new Exception(
                'Conflicting ' . self::IMAGE . ' versions: '
                . implode(', ', $versions),
            );
        }

        $current = $versions[0];
        $updated = preg_replace(
            "/{$image}:" . preg_quote($current, '/') . '/',
            self::IMAGE . ":{$selected}",
            $content,
        );
        if ($updated === null) {
            throw new Exception('Unable to rewrite the base image reference');
        }

        return new Bump($current, $selected, $updated);
    }
}
