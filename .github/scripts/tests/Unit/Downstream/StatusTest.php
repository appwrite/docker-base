<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Downstream\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Status::class)]
final class StatusTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function states(): iterable
    {
        yield 'clean' => ['CLEAN', true, false];
        yield 'hooks' => ['HAS_HOOKS', true, false];
        yield 'unstable' => ['UNSTABLE', true, false];
        yield 'blocked' => ['BLOCKED', false, false];
        yield 'unknown' => ['UNKNOWN', false, false];
        yield 'behind' => ['BEHIND', false, true];
        yield 'dirty' => ['DIRTY', false, true];
        yield 'draft' => ['DRAFT', false, true];
    }

    #[DataProvider('states')]
    public function test_classifies_the_merge_state(
        string $state,
        bool $mergeable,
        bool $stuck,
    ): void {
        $status = new Status([], $state);

        self::assertSame($mergeable, $status->mergeable());
        self::assertSame($stuck, $status->stuck());
    }
}
