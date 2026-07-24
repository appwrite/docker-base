<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class PullRequestValidator
{
    public static function validate(
        PullRequest $pullRequest,
        string $expectedHead,
        string $expectedBase,
    ): void {
        if ($pullRequest->head !== $expectedHead) {
            throw new HeadChangedException(
                "Pull request #{$pullRequest->number} head changed from "
                . "{$expectedHead} to {$pullRequest->head}",
            );
        }
        if ($pullRequest->base !== $expectedBase) {
            throw new HeadChangedException(
                "Pull request #{$pullRequest->number} base changed from "
                . "{$expectedBase} to {$pullRequest->base}",
            );
        }
        if ($pullRequest->baseBranch !== 'main') {
            throw new PullRequestUnavailableException(
                "Pull request #{$pullRequest->number} does not target main",
            );
        }
        if ($pullRequest->state !== 'open') {
            throw new PullRequestUnavailableException(
                "Pull request #{$pullRequest->number} is "
                . $pullRequest->state,
            );
        }
        if ($pullRequest->review !== ReviewDecision::Approved) {
            throw new ApprovalMissingException(
                "Pull request #{$pullRequest->number} is not currently "
                . 'approved',
            );
        }
        if (!$pullRequest->mergeable) {
            throw new PullRequestUnavailableException(
                "Pull request #{$pullRequest->number} is not currently "
                . 'mergeable',
            );
        }
    }
}
