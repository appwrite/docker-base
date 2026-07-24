<?php

declare(strict_types=1);

namespace DockerBase\Automation\Sleeper;

use DockerBase\Automation\Sleeper;
use Override;

final readonly class System implements Sleeper
{
    #[Override]
    public function sleep(int $seconds): void
    {
        if ($seconds > 0) {
            \sleep($seconds);
        }
    }
}
