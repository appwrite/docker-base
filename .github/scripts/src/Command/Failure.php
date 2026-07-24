<?php

declare(strict_types=1);

namespace DockerBase\Command;

use Throwable;

interface Failure
{
    /**
     * @param list<string> $command
     */
    public function __construct(
        array $command,
        ?Result $result = null,
        ?string $message = null,
        ?Throwable $previous = null,
    );
}
