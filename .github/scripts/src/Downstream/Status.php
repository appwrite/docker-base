<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Status
{
    private const array STUCK = ['BEHIND', 'DIRTY', 'DRAFT'];

    /**
     * @param list<array{name: string, status: string, conclusion: string}> $checks
     */
    public function __construct(
        public array $checks,
        public string $state,
    ) {
    }

    /**
     * GitHub computes mergeability lazily; UNKNOWN means "ask again".
     */
    public function computing(): bool
    {
        return $this->state === 'UNKNOWN';
    }

    /**
     * Nothing this automation can do clears these states.
     */
    public function stuck(): bool
    {
        return in_array($this->state, self::STUCK, true);
    }
}
