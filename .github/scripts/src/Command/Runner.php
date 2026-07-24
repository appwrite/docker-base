<?php

declare(strict_types=1);

namespace DockerBase\Command;

interface Runner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, bool $check = true): Result;
}
