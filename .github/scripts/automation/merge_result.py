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

    def validate(self, expected_head: str) -> str:
        """Require a merged result for the exact tested head."""
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
        return self.commit
