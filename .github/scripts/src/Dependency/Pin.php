<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Pin
{
    public function __construct(
        public string $name,
        public string $current,
        public int $start,
        public int $end,
    ) {
    }
}
