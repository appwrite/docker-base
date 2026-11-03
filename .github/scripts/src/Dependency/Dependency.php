<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

final readonly class Dependency
{
    /**
     * @param list<string> $excluded Releases the updater must never select.
     */
    public function __construct(
        public string $name,
        public string $variable,
        public string $reference,
        public Source $source,
        public array $excluded = [],
    ) {
    }
}
