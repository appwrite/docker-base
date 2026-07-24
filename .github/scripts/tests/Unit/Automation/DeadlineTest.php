<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DateTimeImmutable;
use DockerBase\Automation\Deadline;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeadlineTest extends TestCase
{
    #[Test]
    public function test_uses_injected_time_and_clamps_remaining_time(): void
    {
        $start = $this->start();
        $deadline = Deadline::after($start, 600);

        self::assertSame(
            false,
            $deadline->expired($start->modify('+9 minutes')),
        );
        self::assertSame(
            true,
            $deadline->expired($start->modify('+10 minutes')),
        );
        self::assertSame(
            60,
            $deadline->remaining($start->modify('+9 minutes')),
        );
        self::assertSame(
            0,
            $deadline->remaining($start->modify('+11 minutes')),
        );
        self::assertSame(7_200, Deadline::WORKFLOW_TIMEOUT_SECONDS);
    }

    #[Test]
    public function test_rejects_naive_times_and_nonpositive_timeouts(): void
    {
        self::assertSame('+00:00', $this->start()->format('P'));

        $this->expectException(InvalidArgumentException::class);
        Deadline::after($this->start(), 0);
    }

    private function start(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-24T08:00:00+00:00');
    }
}
