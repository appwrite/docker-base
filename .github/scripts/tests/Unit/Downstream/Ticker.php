<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DateTimeImmutable;
use DateTimeZone;
use DockerBase\Automation\Clock;
use Override;

final class Ticker implements Clock
{
    private int $ticks = 0;

    public function __construct(
        private readonly int $seconds = 90,
    ) {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        $elapsed = $this->ticks * $this->seconds;
        ++$this->ticks;

        return (new DateTimeImmutable(
            '2026-08-21T00:00:00+00:00',
            new DateTimeZone('UTC'),
        ))->modify("+{$elapsed} seconds");
    }
}
