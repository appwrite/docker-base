<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

interface Repository
{
    public function file(string $path, string $ref): string;

    public function head(string $branch): string;

    /**
     * @return list<Tag>
     */
    public function tags(string $prefix): array;

    public function mergeCommit(string $branch): ?string;

    public function contains(string $branch, string $commit): bool;

    /**
     * @return list<string>
     */
    public function required(string $branch): array;

    public function commit(
        string $branch,
        string $base,
        string $path,
        string $content,
        string $message,
    ): string;

    public function open(
        string $branch,
        string $base,
        string $title,
        string $body,
    ): Pull;

    /**
     * @return list<array{name: string, status: string, conclusion: string}>
     */
    public function checks(int $pull): array;

    public function merge(int $pull, string $head): string;

    public function tag(string $name, string $target): void;
}
