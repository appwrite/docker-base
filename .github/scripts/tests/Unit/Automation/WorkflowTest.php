<?php

declare(strict_types=1);

namespace DockerBase\Tests\Unit\Automation;

use DateTimeImmutable;
use DockerBase\Automation\Deadline;
use DockerBase\Automation\Run;
use DockerBase\Automation\RunEvaluator;
use DockerBase\Automation\WorkflowState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    #[Test]
    public function test_selects_only_exact_runs_at_or_after_boundary(): void
    {
        $start = $this->start();
        $expected = $this->workflowRun(
            identifier: 8,
            created: $start->modify('+1 second'),
        );
        $runs = [
            $this->workflowRun(identifier: 1, workflow: 'Dive Test'),
            $this->workflowRun(identifier: 2, event: 'pull_request'),
            $this->workflowRun(identifier: 3, head: 'other-head'),
            $this->workflowRun(identifier: 4, branch: 'feature'),
            $this->workflowRun(
                identifier: 5,
                created: $start->modify('-1 microsecond'),
            ),
            $expected,
        ];

        self::assertSame(
            $expected,
            RunEvaluator::select(
                $runs,
                workflow: 'Build and Push',
                event: 'push',
                head: 'approved-head',
                branch: 'main',
                created: $start,
            ),
        );
    }

    #[Test]
    public function test_selects_newest_run_then_newest_rerun_attempt(): void
    {
        $earlier = $this->start()->modify('+1 second');
        $later = $this->start()->modify('+2 seconds');
        $expected = $this->workflowRun(
            identifier: 20,
            created: $later,
            attempt: 2,
        );
        $runs = [
            $this->workflowRun(
                identifier: 10,
                created: $earlier,
                attempt: 3,
            ),
            $this->workflowRun(
                identifier: 20,
                created: $later,
                attempt: 1,
                conclusion: 'failure',
            ),
            $expected,
        ];

        self::assertSame(
            $expected,
            RunEvaluator::select(
                $runs,
                workflow: 'Build and Push',
                event: 'push',
                head: 'approved-head',
                branch: 'main',
                created: $this->start(),
            ),
        );
    }

    #[Test]
    public function test_reports_missing_run_before_deadline(): void
    {
        self::assertSame(WorkflowState::Missing, $this->state([]));
    }

    #[Test]
    public function test_reports_successful_run(): void
    {
        self::assertSame(
            WorkflowState::Succeeded,
            $this->state([$this->workflowRun()]),
        );
    }

    #[Test]
    public function test_does_not_accept_branch_run_for_tag_release(): void
    {
        $start = $this->start();
        $state = RunEvaluator::state(
            [$this->workflowRun(branch: 'main')],
            workflow: 'Build and Push',
            event: 'push',
            head: 'approved-head',
            branch: '1.4.5',
            created: $start,
            deadline: Deadline::after($start, 1_800),
            now: $start,
        );

        self::assertSame(WorkflowState::Missing, $state);
    }

    #[Test]
    public function test_reports_failed_run(): void
    {
        self::assertSame(
            WorkflowState::Failed,
            $this->state([$this->workflowRun(conclusion: 'failure')]),
        );
    }

    #[Test]
    public function test_reports_cancelled_run(): void
    {
        self::assertSame(
            WorkflowState::Cancelled,
            $this->state([$this->workflowRun(conclusion: 'cancelled')]),
        );
    }

    #[Test]
    public function test_reports_run_timeout_conclusion(): void
    {
        self::assertSame(
            WorkflowState::TimedOut,
            $this->state([$this->workflowRun(conclusion: 'timed_out')]),
        );
    }

    #[Test]
    public function test_reports_pending_run_before_deadline(): void
    {
        self::assertSame(
            WorkflowState::Pending,
            $this->state(
                [
                    $this->workflowRun(
                        status: 'in_progress',
                        conclusion: null,
                    ),
                ],
                $this->start()->modify('+29 minutes'),
            ),
        );
    }

    #[Test]
    public function test_times_out_pending_run_at_deadline(): void
    {
        self::assertSame(
            WorkflowState::TimedOut,
            $this->state(
                [
                    $this->workflowRun(
                        status: 'queued',
                        conclusion: null,
                    ),
                ],
                $this->start()->modify('+30 minutes'),
            ),
        );
    }

    #[Test]
    public function test_times_out_missing_run_at_deadline(): void
    {
        self::assertSame(
            WorkflowState::TimedOut,
            $this->state(
                [],
                $this->start()->modify('+30 minutes'),
            ),
        );
    }

    #[Test]
    public function test_preserves_terminal_failure_after_deadline(): void
    {
        self::assertSame(
            WorkflowState::Failed,
            $this->state(
                [$this->workflowRun(conclusion: 'failure')],
                $this->start()->modify('+31 minutes'),
            ),
        );
    }

    private function workflowRun(
        int $identifier = 1,
        string $workflow = 'Build and Push',
        string $event = 'push',
        string $head = 'approved-head',
        string $branch = 'main',
        ?DateTimeImmutable $created = null,
        int $attempt = 1,
        string $status = 'completed',
        ?string $conclusion = 'success',
    ): Run {
        return new Run(
            identifier: $identifier,
            workflow: $workflow,
            event: $event,
            head: $head,
            branch: $branch,
            created: $created ?? $this->start(),
            attempt: $attempt,
            status: $status,
            conclusion: $conclusion,
        );
    }

    /**
     * @param list<Run> $runs
     */
    private function state(
        array $runs,
        ?DateTimeImmutable $now = null,
    ): WorkflowState {
        $start = $this->start();

        return RunEvaluator::state(
            $runs,
            workflow: 'Build and Push',
            event: 'push',
            head: 'approved-head',
            branch: 'main',
            created: $start,
            deadline: Deadline::after($start, 1_800),
            now: $now ?? $start,
        );
    }

    private function start(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-24T08:00:00+00:00');
    }
}
