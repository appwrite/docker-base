<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class MergeValidator
{
    public static function isAutomation(Merge $merge): bool
    {
        $lines = preg_split('/\R/', $merge->body);
        if ($lines === false) {
            return false;
        }

        $head = "<!-- dependency-tested-head:{$merge->head} -->";
        $parent = count($merge->parents) === 1
            ? "<!-- dependency-tested-base:{$merge->parents[0]} -->"
            : '';

        return $merge->state === 'merged'
            && $merge->base === 'main'
            && str_starts_with(
                $merge->branch,
                'automation/dependencies-',
            )
            && self::count($lines, Merge::MARKER) === 1
            && self::count($lines, $head) === 1
            && self::countPrefix(
                $lines,
                '<!-- dependency-tested-head:',
            ) === 1
            && count($merge->parents) === 1
            && self::count($lines, $parent) === 1
            && self::countPrefix(
                $lines,
                '<!-- dependency-tested-base:',
            ) === 1
            && self::countPrefix(
                $lines,
                '<!-- dependency-automation:',
            ) === 1
            && $merge->files === ['Dockerfile'];
    }

    public static function validateResult(
        MergeResult $result,
        string $expectedHead,
        string $expectedBase,
    ): string {
        if ($result->head !== $expectedHead) {
            throw new HeadChangedException(
                "Merged pull request head changed from {$expectedHead} "
                . "to {$result->head}",
            );
        }
        if ($result->state !== 'merged') {
            throw new PullRequestUnavailableException(
                "Pull request merge ended in state {$result->state}",
            );
        }
        if ($result->commit === null) {
            throw new PullRequestUnavailableException(
                'Pull request merge produced no commit',
            );
        }
        if ($result->parents !== [$expectedBase]) {
            $parents = implode(', ', $result->parents) ?: 'none';
            throw new HeadChangedException(
                "Merge commit parents changed from {$expectedBase} "
                . "to {$parents}",
            );
        }

        return $result->commit;
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

    /**
     * @param list<string> $lines
     */
    private static function countPrefix(array $lines, string $prefix): int
    {
        return count(
            array_filter(
                $lines,
                static fn (string $line): bool => str_starts_with(
                    $line,
                    $prefix,
                ),
            ),
        );
    }
}
