<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Dependency
{
    public function __construct(
        public string $name,
        public string $variable,
        public Source $source,
    ) {
    }
}
