<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

interface Source
{
    public function url(): string;
}
