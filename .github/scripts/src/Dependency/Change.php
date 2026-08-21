<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Change
{
    public function __construct(
        public string $name,
        public string $current,
        public string $latest,
    ) {
    }

    public function changed(): bool
    {
        return $this->current !== $this->latest;
    }
}
