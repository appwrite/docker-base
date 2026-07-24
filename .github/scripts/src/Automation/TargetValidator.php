<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class TargetValidator
{
    public static function validateTag(
        Tag $tag,
        string $expectedName,
        string $expectedTarget,
    ): void {
        if ($tag->name !== $expectedName) {
            throw new TargetMismatchException(
                "Expected tag {$expectedName}, found {$tag->name}",
            );
        }
        if ($tag->target !== $expectedTarget) {
            throw new TargetMismatchException(
                "Tag {$tag->name} targets {$tag->target}, "
                . "expected {$expectedTarget}",
            );
        }
    }

    public static function validateRelease(
        Release $release,
        string $expectedTag,
        string $expectedTarget,
    ): void {
        if ($release->tag !== $expectedTag) {
            throw new TargetMismatchException(
                "Expected release for {$expectedTag}, "
                . "found {$release->tag}",
            );
        }
        if ($release->target !== $expectedTarget) {
            throw new TargetMismatchException(
                "Release {$release->tag} targets {$release->target}, "
                . "expected {$expectedTarget}",
            );
        }
    }
}
