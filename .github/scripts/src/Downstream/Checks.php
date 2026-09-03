<?php

declare(strict_types=1);

namespace DockerBase\Downstream;

final readonly class Checks
{
    private const array PASSING = ['SUCCESS', 'SKIPPED', 'NEUTRAL'];

    /**
     * @param list<array{name: string, status: string, conclusion: string}> $checks
     * @param list<string> $required
     *
     * @return list<string>
     */
    public static function pending(array $checks, array $required): array
    {
        $concluded = [];
        foreach ($checks as $check) {
            if ($check['status'] === 'COMPLETED') {
                $concluded[$check['name']] = $check['conclusion'];
            }
        }

        $pending = [];
        foreach ($required as $context) {
            if (! isset($concluded[$context])) {
                $pending[] = $context;
            }
        }
        sort($pending, SORT_STRING);

        return $pending;
    }

    /**
     * @param list<array{name: string, status: string, conclusion: string}> $checks
     * @param list<string> $required
     *
     * @return list<string>
     */
    public static function failed(array $checks, array $required): array
    {
        $wanted = array_flip($required);
        $failed = [];
        foreach ($checks as $check) {
            if (
                isset($wanted[$check['name']])
                && ! in_array($check['conclusion'], self::PASSING, true)
            ) {
                $failed[] = "{$check['name']}={$check['conclusion']}";
            }
        }
        sort($failed, SORT_STRING);

        return $failed;
    }
}
