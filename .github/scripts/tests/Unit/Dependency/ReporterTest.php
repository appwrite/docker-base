<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Dependency;

use DockerBase\Dependency\Application;
use DockerBase\Dependency\Catalog;
use DockerBase\Dependency\Dockerfile;
use DockerBase\Dependency\Reporter;
use DockerBase\Dependency\Resolver;
use DockerBase\Dependency\Selector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reporter::class)]
final class ReporterTest extends TestCase
{
    public function test_renders_markdown_update_report(): void
    {
        $plan = $this->application(new Discovery(
            digest: Fixture::NEW_DIGEST,
            releases: [
                'redis' => [Fixture::CURRENT['redis'], '6.3.1'],
            ],
        ))->plan(Fixture::dockerfile());
        $report = (new Reporter())->render($plan);

        self::assertSame(
            true,
            str_starts_with($report, "## Dependency update report\n"),
        );
        self::assertSame(
            true,
            str_contains(
                $report,
                '| Dependency | Current | Selected | Result |',
            ),
        );
        self::assertSame(
            true,
            str_contains(
                $report,
                '| ' . Catalog::BASE . ' | `' . Fixture::OLD_DIGEST
                    . '` | `' . Fixture::NEW_DIGEST . '` | Updated |',
            ),
        );
        self::assertSame(
            true,
            str_contains(
                $report,
                '| redis | `' . Fixture::CURRENT['redis']
                    . '` | `6.3.1` | Updated |',
            ),
        );
        self::assertSame(true, str_contains($report, '**Updates:** 2'));
        self::assertSame(
            true,
            str_ends_with($report, 'Dockerfile pins were updated.'),
        );
    }

    public function test_renders_explicit_noop_report(): void
    {
        $plan = $this->application(new Discovery())->plan(
            Fixture::dockerfile(),
        );
        $report = (new Reporter())->render($plan);

        self::assertSame(true, str_contains($report, '**Updates:** 0'));
        self::assertSame(
            true,
            str_contains(
                $report,
                'No dependency updates were found.',
            ),
        );
        self::assertSame(false, str_contains($report, '| Updated |'));
    }

    private function application(Discovery $discovery): Application
    {
        $catalog = Catalog::create();

        return new Application(
            $catalog,
            new Dockerfile(),
            new Resolver($discovery, $discovery),
            new Selector(),
        );
    }
}
