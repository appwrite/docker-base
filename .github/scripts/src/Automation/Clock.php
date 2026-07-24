<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
