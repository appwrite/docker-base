<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Downstream\Checks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Checks::class)]
final class ChecksTest extends TestCase
{
    public function test_reports_a_required_check_that_has_not_registered(): void
    {
        self::assertSame(
            ['Tests / E2E'],
            Checks::pending(
                [self::check('Tests / Unit', 'COMPLETED', 'SUCCESS')],
                ['Tests / Unit', 'Tests / E2E'],
            ),
        );
    }

    public function test_reports_a_required_check_that_is_still_running(): void
    {
        self::assertSame(
            ['Build'],
            Checks::pending(
                [self::check('Build', 'IN_PROGRESS', '')],
                ['Build'],
            ),
        );
    }

    public function test_ignores_checks_that_are_not_required(): void
    {
        $checks = [
            self::check('Tests / Unit', 'COMPLETED', 'SUCCESS'),
            self::check('advisory', 'IN_PROGRESS', ''),
            self::check('flaky-optional', 'COMPLETED', 'FAILURE'),
        ];

        self::assertSame([], Checks::pending($checks, ['Tests / Unit']));
        self::assertSame([], Checks::failed($checks, ['Tests / Unit']));
    }

    public function test_accepts_skipped_and_neutral_required_conclusions(): void
    {
        $checks = [
            self::check('Tests / Unit', 'COMPLETED', 'SKIPPED'),
            self::check('Build', 'COMPLETED', 'NEUTRAL'),
        ];

        self::assertSame(
            [],
            Checks::pending($checks, ['Tests / Unit', 'Build']),
        );
        self::assertSame(
            [],
            Checks::failed($checks, ['Tests / Unit', 'Build']),
        );
    }

    public function test_reports_every_failing_required_check(): void
    {
        self::assertSame(
            ['Build=CANCELLED', 'Tests / Unit=FAILURE'],
            Checks::failed(
                [
                    self::check('Tests / Unit', 'COMPLETED', 'FAILURE'),
                    self::check('Build', 'COMPLETED', 'CANCELLED'),
                    self::check('lint', 'COMPLETED', 'FAILURE'),
                ],
                ['Tests / Unit', 'Build'],
            ),
        );
    }

    /**
     * @return array{name: string, status: string, conclusion: string}
     */
    private static function check(
        string $name,
        string $status,
        string $conclusion,
    ): array {
        return [
            'name' => $name,
            'status' => $status,
            'conclusion' => $conclusion,
        ];
    }
}
