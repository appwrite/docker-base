<?php

declare(strict_types=1);

namespace DockerBase\Automation;

final readonly class Merge
{
    public const string MARKER = '<!-- dependency-automation:v1 -->';

    /**
     * @param list<string> $parents
     * @param list<string> $files
     */
    public function __construct(
        public int $number,
        public string $target,
        public string $head,
        public array $parents,
        public string $base,
        public string $branch,
        public string $body,
        public array $files,
        public string $state,
    ) {
    }
}
