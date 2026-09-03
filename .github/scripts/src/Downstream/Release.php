<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

use Override;
use Stringable;

final readonly class Release implements Stringable
{
    public const string PREFIX = 'cl-';

    public function __construct(
        public string $application,
        public int $sub,
    ) {
        if (! Version::isExact($this->application)) {
            throw new Exception(
                "Application version must be MAJOR.MINOR.PATCH, got '{$this->application}'",
            );
        }
        if ($this->sub < 1) {
            throw new Exception('Release sub-version must be positive');
        }
    }

    /**
     * @param list<string> $tags
     */
    public static function next(string $application, array $tags): self
    {
        $pattern = '/\A' . preg_quote(self::PREFIX, '/')
            . preg_quote($application, '/')
            . '-([0-9]+)\z/';

        $highest = 0;
        foreach ($tags as $tag) {
            if (preg_match($pattern, $tag, $matched) !== 1) {
                continue;
            }

            $sub = (int) $matched[1];
            if ((string) $sub !== $matched[1]) {
                throw new Exception(
                    "Release tag '{$tag}' has a non-canonical sub-version",
                );
            }
            if ($sub > $highest) {
                $highest = $sub;
            }
        }

        return new self($application, $highest + 1);
    }

    #[Override]
    public function __toString(): string
    {
        return self::PREFIX . "{$this->application}-{$this->sub}";
    }
}
