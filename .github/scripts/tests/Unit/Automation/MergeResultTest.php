<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DockerBase\Automation\HeadChangedException;
use DockerBase\Automation\MergeResult;
use DockerBase\Automation\MergeValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MergeResultTest extends TestCase
{
    #[Test]
    public function test_accepts_merge_for_exact_tested_head(): void
    {
        self::assertSame(
            str_repeat('b', 40),
            MergeValidator::validateResult(
                new MergeResult(
                    head: str_repeat('a', 40),
                    state: 'merged',
                    commit: str_repeat('b', 40),
                    parents: [str_repeat('c', 40)],
                ),
                str_repeat('a', 40),
                str_repeat('c', 40),
            ),
        );
    }

    #[Test]
    public function test_rejects_merged_state_for_a_different_final_head(): void
    {
        $this->expectException(HeadChangedException::class);

        MergeValidator::validateResult(
            new MergeResult(
                head: str_repeat('c', 40),
                state: 'merged',
                commit: str_repeat('b', 40),
                parents: [str_repeat('d', 40)],
            ),
            str_repeat('a', 40),
            str_repeat('d', 40),
        );
    }

    #[Test]
    public function test_rejects_merge_commit_for_a_different_tested_base(): void
    {
        $this->expectException(HeadChangedException::class);

        MergeValidator::validateResult(
            new MergeResult(
                head: str_repeat('a', 40),
                state: 'merged',
                commit: str_repeat('b', 40),
                parents: [str_repeat('e', 40)],
            ),
            str_repeat('a', 40),
            str_repeat('d', 40),
        );
    }

    #[Test]
    public function test_rejects_a_non_squash_merge_commit(): void
    {
        $this->expectException(HeadChangedException::class);

        MergeValidator::validateResult(
            new MergeResult(
                head: str_repeat('a', 40),
                state: 'merged',
                commit: str_repeat('b', 40),
                parents: [
                    str_repeat('c', 40),
                    str_repeat('d', 40),
                ],
            ),
            str_repeat('a', 40),
            str_repeat('c', 40),
        );
    }
}
