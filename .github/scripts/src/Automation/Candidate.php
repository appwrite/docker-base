<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class Candidate
{
    public function __construct(
        public ?string $tag,
        public string $target,
        public int $pull,
        public ?int $draft,
    ) {
    }
}
