<?php

declare(strict_types=1);

namespace DockerBase\Automation\Clock;

use DateTimeImmutable;
use DateTimeZone;
use DockerBase\Automation\Clock;
use Override;

final readonly class System implements Clock
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
