<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Selector
{
    /**
     * @param iterable<Release> $releases
     */
    public function select(Release $current, iterable $releases): Release
    {
        $currentVersion = Version::parse($current->version);
        if ($currentVersion === null) {
            throw new Exception("Invalid current version: {$current->version}");
        }

        $candidates = [];
        $latest = null;
        foreach ($releases as $release) {
            $version = Version::parse($release->version);
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

        $prefixed = str_starts_with($current->version, 'v');
        $matching = array_values(array_filter(
            $candidates,
            static fn (Release $release): bool => str_starts_with(
                $release->version,
                'v',
            ) === $prefixed,
        ));
        $selected = $matching === [] ? $candidates : $matching;
        usort(
            $selected,
            static fn (Release $left, Release $right): int => strcmp(
                $left->version,
                $right->version,
            ),
        );

        return $selected[0];
    }
}
