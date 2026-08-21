<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Constants
{
    public function application(string $content): string
    {
        $count = preg_match_all(
            '/APP_VERSION_STABLE[ \t]*=[ \t]*\'([^\']+)\'/',
            $content,
            $matches,
        );
        if ($count !== 1) {
            throw new Exception(
                'Expected exactly one APP_VERSION_STABLE declaration, found '
                . (int) $count,
            );
        }

        $version = $matches[1][0];
        if (! Version::isExact($version)) {
            throw new Exception(
                "APP_VERSION_STABLE must be MAJOR.MINOR.PATCH, got '{$version}'",
            );
        }

        return $version;
    }
}
