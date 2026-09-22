<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Status
{
    private const array MERGEABLE = ['CLEAN', 'HAS_HOOKS', 'UNSTABLE'];

    private const array STUCK = ['BEHIND', 'DIRTY', 'DRAFT'];

    /**
     * @param list<array{name: string, status: string, conclusion: string}> $checks
     */
    public function __construct(
        public array $checks,
        public string $state,
    ) {
    }

    public function mergeable(): bool
    {
        return in_array($this->state, self::MERGEABLE, true);
    }

    public function stuck(): bool
    {
        return in_array($this->state, self::STUCK, true);
    }
}
