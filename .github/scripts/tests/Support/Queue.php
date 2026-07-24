<?php

declare(strict_types=1);

namespace DockerBase\Tests\Support;

use DockerBase\Command\Exception;
use DockerBase\Command\Result;
use DockerBase\Command\Runner;
use Override;
use RuntimeException;

final class Queue implements Runner
{
    /**
     * @var list<Result>
     */
    private array $results;

    /**
     * @var list<array{command: list<string>, check: bool}>
     */
    private array $commands = [];

    /**
     * @param list<Result> $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * @param list<string> $command
     */
    #[Override]
    public function run(array $command, bool $check = true): Result
    {
        $this->commands[] = [
            'command' => $command,
            'check' => $check,
        ];
        $result = array_shift($this->results);
        if ($result === null) {
            throw new RuntimeException('No queued command result remains');
        }
        if ($check && ! $result->succeeded()) {
            throw new Exception($command, $result);
        }

        return $result;
    }

    /**
     * @return list<array{command: list<string>, check: bool}>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    public function remaining(): int
    {
        return count($this->results);
    }
}
