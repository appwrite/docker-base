from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Sequence

from automation.deadline import Deadline
from automation.workflow_state import WorkflowState


@dataclass(frozen=True)
class Run:
    """Normalized workflow run state supplied by a GitHub adapter."""

    identifier: int
    workflow: str
    event: str
    head: str
    branch: str
    created: datetime
    attempt: int
    status: str
    conclusion: str | None

    def __post_init__(self) -> None:
        Deadline.require_aware(self.created, 'Workflow run creation time')
        if self.attempt < 1:
            raise ValueError('Workflow run attempt must be positive')

    @classmethod
    def select(
        cls,
        runs: Sequence[Run],
        *,
        workflow: str,
        event: str,
        head: str,
        branch: str,
        created: datetime,
    ) -> Run | None:
        """Select the newest exact run and its newest rerun attempt."""
        Deadline.require_aware(created, 'Workflow run boundary')
        matches = [
            run
            for run in runs
            if run.workflow == workflow
            and run.event == event
            and run.head == head
            and run.branch == branch
            and run.created >= created
        ]
        return max(
            matches,
            key=lambda run: (run.created, run.identifier, run.attempt),
            default=None,
        )

    @classmethod
    def workflow_state(
        cls,
        runs: Sequence[Run],
        *,
        workflow: str,
        event: str,
        head: str,
        branch: str,
        created: datetime,
        deadline: Deadline,
        now: datetime,
    ) -> WorkflowState:
        """Evaluate one exact workflow run without polling or sleeping."""
        run = cls.select(
            runs,
            workflow=workflow,
            event=event,
            head=head,
            branch=branch,
            created=created,
        )
        if run is None:
            if deadline.expired(now):
                return WorkflowState.TIMED_OUT
            return WorkflowState.MISSING

        if run.status != 'completed':
            if deadline.expired(now):
                return WorkflowState.TIMED_OUT
            return WorkflowState.PENDING

        if run.conclusion == 'success':
            return WorkflowState.SUCCEEDED
        if run.conclusion == 'cancelled':
            return WorkflowState.CANCELLED
        if run.conclusion == 'timed_out':
            return WorkflowState.TIMED_OUT
        return WorkflowState.FAILED
