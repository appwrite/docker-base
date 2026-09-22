<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Downstream\Refusal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Refusal::class)]
final class RefusalTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function refusals(): iterable
    {
        yield 'one approving review' => [
            'At least 1 approving review is required by reviewers with write access.',
            true,
        ];
        yield 'two approving reviews' => [
            'At least 2 approving reviews are required by reviewers with write access.',
            true,
        ];
        yield 'changes requested' => [
            'Changes requested by a reviewer.',
            true,
        ];
        yield 'required deployment' => [
            'Required deployment "production" is pending.',
            false,
        ];
        yield 'unresolved conversations' => [
            'All conversations on this pull request must be resolved.',
            false,
        ];
        yield 'signed commits' => [
            'Commits must have valid signatures.',
            false,
        ];
        yield 'branch restriction' => [
            'You are not authorized to push to this branch.',
            false,
        ];
        yield 'required status check' => [
            'Required status check "Tests / Unit" is expected.',
            false,
        ];
        yield 'anything unrecognised' => [
            'Something GitHub has not said before.',
            false,
        ];
    }

    #[DataProvider('refusals')]
    public function test_only_a_review_requirement_may_be_bypassed(
        string $message,
        bool $bypassable,
    ): void {
        self::assertSame(
            $bypassable,
            new Refusal($message)->isReviewRequirement(),
        );
    }
}
