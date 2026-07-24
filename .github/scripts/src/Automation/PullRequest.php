<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class PullRequest
{
    public function __construct(
        public int $number,
        public string $head,
        public string $base,
        public string $baseBranch,
        public string $state,
        public ReviewDecision $review,
        public bool $mergeable,
    ) {
    }
}
