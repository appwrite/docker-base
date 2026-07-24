<?php

declare(strict_types=1);

namespace DockerBase\Automation;

interface Sleeper
{
    public function sleep(int $seconds): void;
}
