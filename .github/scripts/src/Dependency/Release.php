<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Release
{
    public function __construct(
        public string $version,
        public ?string $reference,
    ) {
    }
}
