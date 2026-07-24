<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Deadline
{
    public const int WORKFLOW_TIMEOUT_SECONDS = 7_200;

    public function __construct(
        public DateTimeImmutable $at,
    ) {
    }

    public static function after(DateTimeImmutable $now, int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        return new self($now->modify("+{$seconds} seconds"));
    }

    public function expired(DateTimeImmutable $now): bool
    {
        return $now >= $this->at;
    }

    public function remaining(DateTimeImmutable $now): int
    {
        $remaining = (float) $this->at->format('U.u')
            - (float) $now->format('U.u');

        return max((int) ceil($remaining), 0);
    }
}
