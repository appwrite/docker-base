<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class MergeResult
{
    /**
     * @param list<string> $parents
     */
    public function __construct(
        public string $head,
        public string $state,
        public ?string $commit,
        public array $parents,
    ) {
    }
}
