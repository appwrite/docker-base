<?php

declare(strict_types=1);

namespace DockerBase\Command;

use Override;
use RuntimeException;
use Throwable;

final class Exception extends RuntimeException implements Failure
{
    /**
     * @param list<string> $command
     */
    #[Override]
    public function __construct(
        public readonly array $command,
        public readonly ?Result $result = null,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? self::describe($command, $result),
            $result->code ?? 0,
            $previous,
        );
    }

    /**
     * @param list<string> $command
     */
    private static function describe(array $command, ?Result $result): string
    {
        $message = 'Command failed: ' . implode(' ', $command);
        $detail = trim($result->error ?? '');

        return $detail === '' ? $message : "{$message}: {$detail}";
    }
}
