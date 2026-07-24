<?php

declare(strict_types=1);

namespace DockerBase\Automation;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunEvaluator
{
    /**
     * @param list<Run> $runs
     */
    public static function select(
        array $runs,
        string $workflow,
        string $event,
        string $head,
        string $branch,
        DateTimeImmutable $created,
    ): ?Run {
        $selected = null;
        foreach ($runs as $run) {
            if ($run->attempt < 1) {
                throw new InvalidArgumentException(
                    'Workflow run attempt must be positive',
                );
            }
            if (
                $run->workflow !== $workflow
                || $run->event !== $event
                || $run->head !== $head
                || $run->branch !== $branch
                || $run->created < $created
            ) {
                continue;
            }
            if (
                $selected === null
                || self::isNewer($run, $selected)
            ) {
                $selected = $run;
            }
        }

        return $selected;
    }

    /**
     * @param list<Run> $runs
     */
    public static function state(
        array $runs,
        string $workflow,
        string $event,
        string $head,
        string $branch,
        DateTimeImmutable $created,
        Deadline $deadline,
        DateTimeImmutable $now,
    ): WorkflowState {
        $run = self::select(
            $runs,
            workflow: $workflow,
            event: $event,
            head: $head,
            branch: $branch,
            created: $created,
        );
        if ($run === null) {
            return $deadline->expired($now)
                ? WorkflowState::TimedOut
                : WorkflowState::Missing;
        }
        if ($run->status !== 'completed') {
            return $deadline->expired($now)
                ? WorkflowState::TimedOut
                : WorkflowState::Pending;
        }

        return match ($run->conclusion) {
            'success' => WorkflowState::Succeeded,
            'cancelled' => WorkflowState::Cancelled,
            'timed_out' => WorkflowState::TimedOut,
            default => WorkflowState::Failed,
        };
    }

    private static function isNewer(Run $candidate, Run $selected): bool
    {
        if ($candidate->created != $selected->created) {
            return $candidate->created > $selected->created;
        }
        if ($candidate->identifier !== $selected->identifier) {
            return $candidate->identifier > $selected->identifier;
        }

        return $candidate->attempt > $selected->attempt;
    }
}
