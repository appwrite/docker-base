from enum import Enum


class WorkflowState(Enum):
    """A closed set of states used by polling orchestration."""

    MISSING = 'missing'
    PENDING = 'pending'
    SUCCEEDED = 'succeeded'
    FAILED = 'failed'
    CANCELLED = 'cancelled'
    TIMED_OUT = 'timed_out'
