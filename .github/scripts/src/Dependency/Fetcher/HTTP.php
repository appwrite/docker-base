<?php

declare(strict_types=1);

namespace DockerBase\Dependency\Fetcher;

use DockerBase\Dependency\Exception;
use DockerBase\Dependency\Fetcher;
use Override;

final readonly class HTTP implements Fetcher
{
    public function __construct(
        private int $timeout = 30,
    ) {
    }

    #[Override]
    public function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => $this->timeout,
            ],
        ]);

        error_clear_last();
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $error = error_get_last();
            $detail = trim($error['message'] ?? 'unknown error');

            throw new Exception("Failed to fetch {$url}: {$detail}");
        }

        return $content;
    }
}
