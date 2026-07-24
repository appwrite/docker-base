<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DockerBase\Automation\ApprovalMissingException;
use DockerBase\Automation\HeadChangedException;
use DockerBase\Automation\PullRequest;
use DockerBase\Automation\PullRequestValidator;
use DockerBase\Automation\ReviewDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PullRequestTest extends TestCase
{
    #[Test]
    public function test_accepts_current_approved_head(): void
    {
        $this->expectNotToPerformAssertions();

        PullRequestValidator::validate(
            new PullRequest(
                number: 75,
                head: 'approved-head',
                base: 'tested-base',
                baseBranch: 'main',
                state: 'open',
                review: ReviewDecision::Approved,
                mergeable: true,
            ),
            'approved-head',
            'tested-base',
        );
    }

    #[Test]
    public function test_rejects_changed_head_even_if_currently_approved(): void
    {
        $this->expectException(HeadChangedException::class);

        PullRequestValidator::validate(
            new PullRequest(
                number: 75,
                head: 'changed-head',
                base: 'tested-base',
                baseBranch: 'main',
                state: 'open',
                review: ReviewDecision::Approved,
                mergeable: true,
            ),
            'approved-head',
            'tested-base',
        );
    }

    #[Test]
    public function test_rejects_changed_base_after_ci_succeeds(): void
    {
        $this->expectException(HeadChangedException::class);

        PullRequestValidator::validate(
            new PullRequest(
                number: 75,
                head: 'approved-head',
                base: 'changed-base',
                baseBranch: 'main',
                state: 'open',
                review: ReviewDecision::Approved,
                mergeable: true,
            ),
            'approved-head',
            'tested-base',
        );
    }

    #[Test]
    public function test_rejects_missing_current_approval(): void
    {
        $this->expectException(ApprovalMissingException::class);

        PullRequestValidator::validate(
            new PullRequest(
                number: 75,
                head: 'approved-head',
                base: 'tested-base',
                baseBranch: 'main',
                state: 'open',
                review: ReviewDecision::ReviewRequired,
                mergeable: true,
            ),
            'approved-head',
            'tested-base',
        );
    }
}
