<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use Stringable;

final readonly class Version implements Stringable
{
    private const string PATTERN = '/\A(0|[1-9][0-9]*)\.'
        . '(0|[1-9][0-9]*)\.'
        . '(0|[1-9][0-9]*)\z/D';

    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
    ) {
        if ($major < 0 || $minor < 0 || $patch < 0) {
            throw new VersionInvalidException(
                'Version components cannot be negative',
            );
        }
    }

    public static function parse(string $value): ?self
    {
        $matched = preg_match(self::PATTERN, $value, $matches);
        if ($matched !== 1) {
            return null;
        }

        $major = self::component($matches[1]);
        $minor = self::component($matches[2]);
        $patch = self::component($matches[3]);
        if ($major === null || $minor === null || $patch === null) {
            return null;
        }

        return new self($major, $minor, $patch);
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
        if ($this->patch === PHP_INT_MAX) {
            throw new VersionInvalidException(
                'Patch version cannot be incremented',
            );
        }

        return new self($this->major, $this->minor, $this->patch + 1);
    }

    public function compare(self $other): int
    {
        return [$this->major, $this->minor, $this->patch]
            <=> [$other->major, $other->minor, $other->patch];
    }

    #[\Override]
    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

    private static function component(string $value): ?int
    {
        $component = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );

        return is_int($component) ? $component : null;
    }
}
