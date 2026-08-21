<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class Tag
{
    public function __construct(
        public string $name,
        public string $target,
    ) {
    }
}
