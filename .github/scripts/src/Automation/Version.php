<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use Override;
use Stringable;

final readonly class Version implements Stringable
{
    private const string COMPONENT = '/\A(?:0|[1-9][0-9]*)\z/D';

    private const string PATTERN = '/\A(0|[1-9][0-9]*)\.'
        . '(0|[1-9][0-9]*)\.'
        . '(0|[1-9][0-9]*)\z/D';

    public function __construct(
        public string $major,
        public string $minor,
        public string $patch,
    ) {
        foreach ([$major, $minor, $patch] as $component) {
            if (preg_match(self::COMPONENT, $component) !== 1) {
                throw new VersionInvalidException(
                    'Version components must be canonical non-negative integers',
                );
            }
        }
    }

    public static function parse(string $value): ?self
    {
        $matched = preg_match(self::PATTERN, $value, $matches);
        if ($matched !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2], $matches[3]);
    }

    /**
     * @param iterable<string> $tags
     *
     * @return list<self>
     */
    public static function stable(iterable $tags): array
    {
        $versions = [];
        foreach ($tags as $tag) {
            $version = self::parse($tag);
            if ($version !== null) {
                $versions[(string) $version] = $version;
            }
        }

        $versions = array_values($versions);
        usort(
            $versions,
            static fn (self $left, self $right): int => $left->compare(
                $right,
            ),
        );

        return $versions;
    }

    /**
     * @param iterable<string> $tags
     */
    public static function latest(iterable $tags): self
    {
        $versions = self::stable($tags);
        if ($versions === []) {
            throw new VersionMissingException(
                'No stable remote version tag exists',
            );
        }

        return $versions[array_key_last($versions)];
    }

    /**
     * @param iterable<string> $tags
     */
    public static function next(iterable $tags): self
    {
        return self::latest($tags)->nextPatch();
    }

    /**
     * @param iterable<string> $tags
     * @param iterable<string> $releases
     */
    public static function unreleased(
        iterable $tags,
        iterable $releases,
    ): ?self {
        $tagged = self::stable($tags);
        if ($tagged === []) {
            return null;
        }

        $published = self::stable($releases);
        $released = [];
        foreach ($published as $version) {
            $released[(string) $version] = true;
        }
        $threshold = $published === []
            ? null
            : $published[array_key_last($published)];
        $candidate = null;
        foreach ($tagged as $version) {
            if (
                isset($released[(string) $version])
                || (
                    $threshold !== null
                    && $version->compare($threshold) <= 0
                )
            ) {
                continue;
            }
            if (
                $candidate === null
                || $version->compare($candidate) > 0
            ) {
                $candidate = $version;
            }
        }

        return $candidate;
    }

    /**
     * @param iterable<string> $tags
     * @param iterable<string> $releases
     */
    public static function candidate(
        iterable $tags,
        iterable $releases,
    ): self {
        $values = is_array($tags)
            ? array_values($tags)
            : iterator_to_array($tags, false);
        $unreleased = self::unreleased($values, $releases);

        return $unreleased ?? self::next($values);
    }

    /**
     * @param iterable<string> $tags
     */
    public static function afterCollision(
        iterable $tags,
        string $collision,
    ): self {
        $collided = self::parse($collision);
        if ($collided === null) {
            throw new VersionInvalidException(
                "Collision tag '{$collision}' is not a stable version",
            );
        }

        $values = is_array($tags)
            ? array_values($tags)
            : iterator_to_array($tags, false);
        $values[] = (string) $collided;

        return self::next($values);
    }

    public function nextPatch(): self
    {
        return new self(
            $this->major,
            $this->minor,
            self::increment($this->patch),
        );
    }

    public function compare(self $other): int
    {
        foreach ([
            [$this->major, $other->major],
            [$this->minor, $other->minor],
            [$this->patch, $other->patch],
        ] as [$current, $candidate]) {
            $comparison = strlen($current) <=> strlen($candidate);
            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = strcmp($current, $candidate) <=> 0;
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    #[Override]
    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

    private static function increment(string $value): string
    {
        $incremented = $value;
        for ($index = strlen($incremented) - 1; $index >= 0; $index--) {
            if ($incremented[$index] === '9') {
                $incremented[$index] = '0';

                continue;
            }

            $incremented[$index] = chr(ord($incremented[$index]) + 1);

            return $incremented;
        }

        return '1' . $incremented;
    }
}
