<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Downstream\Dockerfile;
use DockerBase\Downstream\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dockerfile::class)]
final class BumpTest extends TestCase
{
    public function test_rewrites_every_reference_to_the_same_version(): void
    {
        $content = "FROM appwrite/base:2.0.0 AS base\n"
            . "FROM appwrite/base:2.0.0-xdebug AS xdebug\n"
            . "# appwrite/base:2.0.0 ships without XDebug\n";

        $bump = (new Dockerfile())->bump($content, '2.0.1');

        self::assertSame('2.0.0', $bump->current);
        self::assertSame('2.0.1', $bump->selected);
        self::assertSame(true, $bump->changed());
        self::assertSame(
            "FROM appwrite/base:2.0.1 AS base\n"
            . "FROM appwrite/base:2.0.1-xdebug AS xdebug\n"
            . "# appwrite/base:2.0.1 ships without XDebug\n",
            $bump->content,
        );
    }

    public function test_reports_no_change_when_already_current(): void
    {
        $bump = (new Dockerfile())->bump(
            "FROM appwrite/base:2.0.1 AS base\n",
            '2.0.1',
        );

        self::assertSame(false, $bump->changed());
    }

    public function test_rejects_conflicting_versions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Conflicting appwrite/base versions: 1.4.3, 2.0.0',
        );

        (new Dockerfile())->bump(
            "FROM appwrite/base:2.0.0 AS base\n"
            . "FROM appwrite/base:1.4.3 AS other\n",
            '2.0.1',
        );
    }

    public function test_rejects_a_dockerfile_with_no_reference(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No appwrite/base reference found');

        (new Dockerfile())->bump("FROM php:8.5-alpine\n", '2.0.1');
    }

    public function test_rejects_an_inexact_selected_version(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('exact MAJOR.MINOR.PATCH');

        (new Dockerfile())->bump("FROM appwrite/base:2.0.0\n", '2.0');
    }
}
