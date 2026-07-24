<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Selector
{
    /**
     * @param iterable<string> $releases
     */
    public function select(string $current, iterable $releases): string
    {
        $currentVersion = Version::parse($current);
        if ($currentVersion === null) {
            throw new Exception("Invalid current version: {$current}");
        }

        $candidates = [];
        $latest = null;
        foreach ($releases as $release) {
            $version = Version::parse($release);
            if (
                $version === null
                || $version->major !== $currentVersion->major
                || $version->compare($currentVersion) <= 0
            ) {
                continue;
            }

            $comparison = $latest === null ? 1 : $version->compare($latest);
            if ($comparison > 0) {
                $latest = $version;
                $candidates = [$release];
            } elseif ($comparison === 0) {
                $candidates[] = $release;
            }
        }

        if ($latest === null) {
            return $current;
        }

        $prefixed = str_starts_with($current, 'v');
        $matching = array_values(array_filter(
            $candidates,
            static fn (string $release): bool => str_starts_with($release, 'v') === $prefixed,
        ));
        $selected = $matching === [] ? $candidates : $matching;
        sort($selected, SORT_STRING);

        return $selected[0];
    }
}
