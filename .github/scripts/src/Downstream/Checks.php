<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Checks
{
    private const array PASSING = ['SUCCESS', 'SKIPPED', 'NEUTRAL'];

    /**
     * @param list<array{name: string, status: string, conclusion: string}> $checks
     */
    public static function settled(array $checks): bool
    {
        if ($checks === []) {
            return false;
        }

        foreach ($checks as $check) {
            if ($check['status'] !== 'COMPLETED') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{name: string, status: string, conclusion: string}> $checks
     *
     * @return list<string>
     */
    public static function failed(array $checks): array
    {
        $failed = [];
        foreach ($checks as $check) {
            if (! in_array($check['conclusion'], self::PASSING, true)) {
                $failed[] = "{$check['name']}={$check['conclusion']}";
            }
        }
        sort($failed, SORT_STRING);

        return $failed;
    }
}
