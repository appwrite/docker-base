<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class RecoverySelector
{
    /**
     * @param list<Tag> $tags
     * @param list<Recovery> $releases
     * @param list<Merge> $merges
     */
    public static function select(
        array $tags,
        array $releases,
        array $merges,
    ): ?Candidate {
        $released = [];
        $published = [];
        foreach ($releases as $release) {
            if (!$release->draft) {
                $released[$release->tag] = true;
            }
            if (!$release->draft && !$release->prerelease) {
                $published[] = $release->tag;
            }
        }

        $stable = Version::stable($published);
        $threshold = $stable === [] ? null : $stable[array_key_last($stable)];
        $candidates = [];
        $targets = [];
        foreach ($tags as $tag) {
            $targets[$tag->target] = true;
            $version = Version::parse($tag->name);
            if (
                $version === null
                || isset($released[$tag->name])
                || (
                    $threshold !== null
                    && $version->compare($threshold) <= 0
                )
            ) {
                continue;
            }

            $evidence = array_values(
                array_filter(
                    $merges,
                    static fn (Merge $merge): bool => (
                        $merge->target === $tag->target
                        && MergeValidator::isAutomation($merge)
                    ),
                ),
            );
            if (count($evidence) !== 1) {
                continue;
            }

            $merge = $evidence[0];
            $drafts = array_values(
                array_filter(
                    $releases,
                    static fn (Recovery $release): bool => (
                        $release->tag === $tag->name
                        && self::matches($release, $merge)
                    ),
                ),
            );
            if (count($drafts) > 1) {
                throw new RecoveryException(
                    "Multiple automation drafts exist for {$tag->name}",
                );
            }

            $candidates[] = new Candidate(
                tag: $tag->name,
                target: $tag->target,
                pull: $merge->number,
                draft: $drafts === [] ? null : $drafts[0]->identifier,
            );
        }

        foreach ($merges as $merge) {
            if (
                !isset($targets[$merge->target])
                && MergeValidator::isAutomation($merge)
            ) {
                $candidates[] = new Candidate(
                    tag: null,
                    target: $merge->target,
                    pull: $merge->number,
                    draft: null,
                );
            }
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $key = implode(
                "\0",
                [
                    $candidate->tag ?? '',
                    $candidate->target,
                    (string) $candidate->pull,
                    $candidate->draft === null
                        ? ''
                        : (string) $candidate->draft,
                ],
            );
            $unique[$key] = $candidate;
        }
        if (count($unique) > 1) {
            $names = array_map(
                static fn (Candidate $candidate): string => (
                    $candidate->tag
                    ?? "pull request #{$candidate->pull}"
                ),
                array_values($unique),
            );
            sort($names);
            throw new RecoveryException(
                'Multiple dependency releases are recoverable: '
                . implode(', ', $names),
            );
        }

        $candidate = $unique === []
            ? null
            : array_values($unique)[0];
        $marked = array_filter(
            $releases,
            static fn (Recovery $release): bool => (
                $release->draft
                && str_contains($release->body, Merge::MARKER)
            ),
        );
        if (
            $marked !== []
            && (
                $candidate === null
                || self::hasDifferentDraft(
                    array_values($marked),
                    $candidate->draft,
                )
            )
        ) {
            throw new RecoveryException(
                'Unsafe dependency automation draft state exists',
            );
        }

        return $candidate;
    }

    public static function matches(
        Recovery $release,
        Merge $merge,
    ): bool {
        $lines = preg_split('/\R/', $release->body);
        if ($lines === false) {
            return false;
        }

        return $release->draft
            && !$release->prerelease
            && self::count($lines, Merge::MARKER) === 1
            && self::count(
                $lines,
                "<!-- dependency-target:{$merge->target} -->",
            ) === 1
            && self::count(
                $lines,
                "<!-- dependency-pull:{$merge->number} -->",
            ) === 1
            && in_array($release->pull, [0, $merge->number], true)
            && $release->target === $merge->target;
    }

    /**
     * @param list<Recovery> $releases
     */
    private static function hasDifferentDraft(
        array $releases,
        ?int $draft,
    ): bool {
        foreach ($releases as $release) {
            if ($release->identifier !== $draft) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $lines
     */
    private static function count(array $lines, string $expected): int
    {
        return count(
            array_filter(
                $lines,
                static fn (string $line): bool => $line === $expected,
            ),
        );
    }
}
