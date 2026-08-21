<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Downstream\Checks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Checks::class)]
final class ChecksTest extends TestCase
{
    public function test_is_unsettled_while_any_check_runs(): void
    {
        self::assertSame(
            false,
            Checks::settled([
                self::check('build', 'COMPLETED', 'SUCCESS'),
                self::check('tests', 'IN_PROGRESS', ''),
            ]),
        );
    }

    public function test_is_unsettled_when_no_checks_exist(): void
    {
        self::assertSame(false, Checks::settled([]));
    }

    public function test_accepts_skipped_and_neutral_conclusions(): void
    {
        $checks = [
            self::check('build', 'COMPLETED', 'SUCCESS'),
            self::check('tag-only', 'COMPLETED', 'SKIPPED'),
            self::check('advisory', 'COMPLETED', 'NEUTRAL'),
        ];

        self::assertSame(true, Checks::settled($checks));
        self::assertSame([], Checks::failed($checks));
    }

    public function test_reports_every_failing_check(): void
    {
        self::assertSame(
            ['lint=CANCELLED', 'tests=FAILURE'],
            Checks::failed([
                self::check('build', 'COMPLETED', 'SUCCESS'),
                self::check('tests', 'COMPLETED', 'FAILURE'),
                self::check('lint', 'COMPLETED', 'CANCELLED'),
            ]),
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
