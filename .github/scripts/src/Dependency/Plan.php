<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Plan
{
    /**
     * @param list<Change> $changes
     */
    public function __construct(
        public string $content,
        public array $changes,
    ) {
    }

    public function changed(): bool
    {
        foreach ($this->changes as $change) {
            if ($change->changed()) {
                return true;
            }
        }

        return false;
    }
}
