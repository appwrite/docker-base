<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use DateTimeImmutable;

final readonly class Run
{
    public function __construct(
        public int $identifier,
        public string $workflow,
        public string $event,
        public string $head,
        public string $branch,
        public DateTimeImmutable $created,
        public int $attempt,
        public string $status,
        public ?string $conclusion,
    ) {
    }
}
