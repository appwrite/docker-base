<?php

declare(strict_types=1);

namespace DockerBase\Dependency\Source;

use DockerBase\Dependency\Source;
use Override;

final readonly class PECL implements Source
{
    public function __construct(
        private string $url,
    ) {
    }

    #[Override]
    public function url(): string
    {
        return $this->url;
    }
}
