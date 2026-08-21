<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Downstream;

use DockerBase\Automation\Sleeper;
use DockerBase\Downstream\Constants;
use DockerBase\Downstream\Dockerfile;
use DockerBase\Downstream\Exception;
use DockerBase\Downstream\Orchestrator;
use DockerBase\Downstream\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Orchestrator::class)]
final class OrchestratorTest extends TestCase
{
    private const string DOCKERFILE = "FROM appwrite/base:2.0.0 AS base\n";

    private const string HEAD = 'a0000000000000000000000000000000000000aa';

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

    public function test_waits_until_every_required_check_concludes(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [
                [self::check('Tests / Unit', 'IN_PROGRESS', '')],
                [self::check('Tests / Unit', 'COMPLETED', 'SUCCESS')],
            ],
        );

        $this->orchestrator($repository)->wait(93);

        self::assertSame(
            ['required:main', 'checks:93', 'checks:93'],
            $repository->calls,
        );
    }

    public function test_waits_for_a_required_check_that_registers_late(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [
                [self::check('lint', 'COMPLETED', 'SUCCESS')],
                [self::check('lint', 'COMPLETED', 'SUCCESS')],
                [
                    self::check('lint', 'COMPLETED', 'SUCCESS'),
                    self::check('Tests / Unit', 'IN_PROGRESS', ''),
                ],
                [
                    self::check('lint', 'COMPLETED', 'SUCCESS'),
                    self::check('Tests / Unit', 'COMPLETED', 'SUCCESS'),
                ],
            ],
        );

        $this->orchestrator($repository)->wait(93);

        self::assertSame(5, count($repository->calls));
    }

    public function test_refuses_to_merge_when_nothing_is_required(): void
    {
        $repository = new Fake(self::DOCKERFILE, requiredChecks: []);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Branch 'main' declares no required status checks",
        );

        $this->orchestrator($repository)->wait(93);
    }

    public function test_refuses_to_continue_when_a_check_failed(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [[self::check('Tests / Unit', 'COMPLETED', 'FAILURE')]],
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Base update CI did not succeed: Tests / Unit=FAILURE',
        );

        $this->orchestrator($repository)->wait(93);
    }

    public function test_refuses_to_merge_a_check_that_went_pending_again(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [[self::check('Tests / Unit', 'IN_PROGRESS', '')]],
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Required checks are no longer concluded: Tests / Unit',
        );

        $this->orchestrator($repository)->release(93, self::HEAD);
    }

    public function test_refuses_to_merge_a_check_that_failed_after_waiting(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [[self::check('Tests / Unit', 'COMPLETED', 'FAILURE')]],
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Base update CI did not succeed: Tests / Unit=FAILURE',
        );

        $this->orchestrator($repository)->release(93, self::HEAD);
    }

    public function test_merges_then_tags_the_merge_commit(): void
    {
        $repository = new Fake(
            self::DOCKERFILE,
            rounds: [[self::check('Tests / Unit', 'COMPLETED', 'SUCCESS')]],
        );
        $head = 'a0000000000000000000000000000000000000aa';

        $release = $this->orchestrator($repository)->release(93, $head);

        self::assertSame('cl-1.9.6-2', (string) $release);
        self::assertSame('cl-1.9.6-2', $repository->tagged);
        self::assertSame(
            [
                'required:main',
                'checks:93',
                "merge:93@{$head}",
                'file:app/init/constants.php',
                'tags:cl-',
                'tag:cl-1.9.6-2@b0000000000000000000000000000000000000bb',
            ],
            $repository->calls,
        );
    }

    public function test_tags_a_merge_that_never_got_its_tag(): void
    {
        $merge = 'b0000000000000000000000000000000000000bb';
        $repository = new Fake(
            "FROM appwrite/base:2.0.1 AS base\n",
            head: $merge,
            merged: $merge,
        );

        $release = $this->orchestrator($repository)->recover('2.0.1');

        self::assertNotNull($release);
        self::assertSame('cl-1.9.6-2', (string) $release);
        self::assertSame('cl-1.9.6-2', $repository->tagged);
    }

    public function test_does_not_recover_a_merge_already_tagged(): void
    {
        $merge = 'b0000000000000000000000000000000000000bb';
        $repository = new Fake(
            "FROM appwrite/base:2.0.1 AS base\n",
            tags: [new Tag('cl-1.9.6-2', $merge)],
            head: $merge,
            merged: $merge,
        );

        self::assertNull($this->orchestrator($repository)->recover('2.0.1'));
        self::assertNull($repository->tagged);
    }

    public function test_recovers_after_main_has_moved_past_the_merge(): void
    {
        $repository = new Fake(
            "FROM appwrite/base:2.0.1 AS base\n",
            head: 'd0000000000000000000000000000000000000dd',
            merged: 'b0000000000000000000000000000000000000bb',
        );

        $release = $this->orchestrator($repository)->recover('2.0.1');

        self::assertNotNull($release);
        self::assertSame('cl-1.9.6-2', (string) $release);
    }

    public function test_does_not_recover_a_merge_absent_from_the_branch(): void
    {
        $repository = new Fake(
            "FROM appwrite/base:2.0.1 AS base\n",
            merged: 'b0000000000000000000000000000000000000bb',
            contained: false,
        );

        self::assertNull($this->orchestrator($repository)->recover('2.0.1'));
        self::assertNull($repository->tagged);
    }

    public function test_recovers_nothing_without_a_merged_pull_request(): void
    {
        $repository = new Fake(self::DOCKERFILE);

        self::assertNull($this->orchestrator($repository)->recover('2.0.1'));
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
        return new Orchestrator(
            $repository,
            new Dockerfile(),
            new Constants(),
            new Ticker(),
            $this->createStub(Sleeper::class),
        );
    }
}
