<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class Recovery
{
    public function __construct(
        public int $identifier,
        public string $tag,
        public string $target,
        public int $pull,
        public bool $draft,
        public bool $prerelease,
        public string $body,
    ) {
    }
}
