<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class Release
{
    public function __construct(
        public string $tag,
        public string $target,
    ) {
    }
}
