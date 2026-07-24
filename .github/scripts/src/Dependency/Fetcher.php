<?php

declare(strict_types=1);

namespace DockerBase\Dependency;

interface Fetcher
{
    public function fetch(string $url): string;
}
