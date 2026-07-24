<?php

declare(strict_types=1);

namespace DockerBase\Command;

final readonly class Result
{
    public function __construct(
        public int $code,
        public string $output,
        public string $error,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->code === 0;
    }
}
