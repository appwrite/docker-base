<?php

declare(strict_types=1);

namespace DockerBase\Automation\Pull;

use JsonException;
use RuntimeException;

final readonly class Payload
{
    /**
     * @return array{number: string, base: string}
     */
    public static function validate(
        string $json,
        string $branch,
        string $head,
        string $base,
    ): array {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Pull request response is invalid',
                previous: $exception,
            );
        }
        if (! is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException('Pull request response is invalid');
        }

        $number = $payload['number'] ?? null;
        if (! is_int($number) || $number < 1) {
            throw new RuntimeException('Pull request number is invalid');
        }
        if (($payload['baseRefName'] ?? null) !== 'main') {
            throw new RuntimeException(
                'Pull request does not target main',
            );
        }
        if (($payload['baseRefOid'] ?? null) !== $base) {
            throw new RuntimeException(
                'Pull request does not use the tested base',
            );
        }
        if (
            ($payload['headRefName'] ?? null) !== $branch
            || ($payload['headRefOid'] ?? null) !== $head
        ) {
            throw new RuntimeException(
                'Pull request does not use the pushed head',
            );
        }
        if (($payload['state'] ?? null) !== 'OPEN') {
            throw new RuntimeException('Pull request is not open');
        }

        return [
            'number' => (string) $number,
            'base' => $base,
        ];
    }
}
