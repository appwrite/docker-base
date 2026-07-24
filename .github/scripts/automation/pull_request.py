from dataclasses import dataclass

from automation.approval_missing_error import ApprovalMissingError
from automation.head_changed_error import HeadChangedError
from automation.pull_request_unavailable_error import (
    PullRequestUnavailableError,
)
from automation.review_decision import ReviewDecision


@dataclass(frozen=True)
class PullRequest:
    """Current pull request state supplied immediately before merging."""

    number: int
    head: str
    state: str
    review: ReviewDecision
    base: str = ''

    def validate(
        self,
        expected_head: str,
        expected_base: str | None = None,
    ) -> None:
        """Require unchanged tested refs and a current approval."""
        if self.head != expected_head:
            raise HeadChangedError(
                f'Pull request #{self.number} head changed from '
                f'{expected_head} to {self.head}'
            )
        if expected_base is not None and self.base != expected_base:
            raise HeadChangedError(
                f'Pull request #{self.number} base changed from '
                f'{expected_base} to {self.base}'
            )
        if self.state != 'open':
            raise PullRequestUnavailableError(
                f'Pull request #{self.number} is {self.state}'
            )
        if self.review is not ReviewDecision.APPROVED:
            raise ApprovalMissingError(
                f'Pull request #{self.number} is not currently approved'
            )
