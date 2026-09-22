<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Pull
{
    public function __construct(
        public int $number,
        public string $head,
        public string $base,
    ) {
    }
}
