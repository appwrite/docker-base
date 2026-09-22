<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Bump
{
    public function __construct(
        public string $current,
        public string $selected,
        public string $content,
    ) {
    }

    public function changed(): bool
    {
        return $this->current !== $this->selected;
    }
}
