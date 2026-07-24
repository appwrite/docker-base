from dataclasses import dataclass

from automation.head_changed_error import HeadChangedError
from automation.pull_request_unavailable_error import (
    PullRequestUnavailableError,
)


@dataclass(frozen=True)
class MergeResult:
    """Final pull request state returned after a successful merge command."""

    head: str
    state: str
    commit: str | None
    parents: tuple[str, ...]

    def validate(self, expected_head: str, expected_base: str) -> str:
        """Require a squash merge of the exact tested head and base."""
        if self.head != expected_head:
            raise HeadChangedError(
                f'Merged pull request head changed from {expected_head} '
                f'to {self.head}'
            )
        if self.state != 'merged':
            raise PullRequestUnavailableError(
                f'Pull request merge ended in state {self.state}'
            )
        if self.commit is None:
            raise PullRequestUnavailableError(
                'Pull request merge produced no commit'
            )
        if self.parents != (expected_base,):
            parents = ', '.join(self.parents) or 'none'
            raise HeadChangedError(
                f'Merge commit parents changed from {expected_base} '
                f'to {parents}'
            )
        return self.commit
