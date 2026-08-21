<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DateTimeImmutable;
use DateTimeZone;
use DockerBase\Automation\Clock;
use DockerBase\Automation\Sleeper;
use DockerBase\Downstream\Constants;
use DockerBase\Downstream\Dockerfile;
use DockerBase\Downstream\Exception;
use DockerBase\Downstream\Orchestrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Orchestrator::class)]
final class OrchestratorTest extends TestCase
{
    private const string DOCKERFILE = "FROM appwrite/base:2.0.0 AS base\n";

    public function test_opens_a_pull_request_for_a_new_base_version(): void
    {
        $repository = new Fake(self::DOCKERFILE);

        $pull = $this->orchestrator($repository)->propose('2.0.1');

        self::assertNotNull($pull);
        self::assertSame(93, $pull->number);
        self::assertSame('main', $pull->base);
        self::assertSame(
            [
                'head:main',
                'file:Dockerfile',
                'commit:automation/base-2.0.1',
                'open:automation/base-2.0.1->main',
            ],
            $repository->calls,
        );
    }

    public function test_opens_nothing_when_the_base_is_already_current(): void
    {
        $repository = new Fake("FROM appwrite/base:2.0.1 AS base\n");

        self::assertNull($this->orchestrator($repository)->propose('2.0.1'));
        self::assertSame(
            ['head:main', 'file:Dockerfile'],
            $repository->calls,
        );
    }

    public function test_waits_until_every_check_concludes(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [
                [self::check('build', 'IN_PROGRESS', '')],
                [self::check('build', 'COMPLETED', 'SUCCESS')],
            ],
        );

        $this->orchestrator($repository)->wait(93);

        self::assertSame(['checks:93', 'checks:93'], $repository->calls);
    }

    public function test_refuses_to_continue_when_a_check_failed(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [[self::check('tests', 'COMPLETED', 'FAILURE')]],
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Base update CI did not succeed: tests=FAILURE',
        );

        $this->orchestrator($repository)->wait(93);
    }

    public function test_merges_then_tags_the_merge_commit(): void
    {
        $repository = new Fake(self::DOCKERFILE);
        $head = 'a0000000000000000000000000000000000000aa';

        $release = $this->orchestrator($repository)->release(93, $head);

        self::assertSame('cl-1.9.6-2', (string) $release);
        self::assertSame('cl-1.9.6-2', $repository->tagged);
        self::assertSame(
            [
                "merge:93@{$head}",
                'file:app/init/constants.php',
                'tags:cl-',
                'tag:cl-1.9.6-2@b0000000000000000000000000000000000000bb',
            ],
            $repository->calls,
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

    private function orchestrator(Fake $repository): Orchestrator
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(
            new DateTimeImmutable(
                '2026-08-21T00:00:00+00:00',
                new DateTimeZone('UTC'),
            ),
        );

        return new Orchestrator(
            $repository,
            new Dockerfile(),
            new Constants(),
            $clock,
            $this->createStub(Sleeper::class),
        );
    }
}
