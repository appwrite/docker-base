from enum import Enum


class ReviewDecision(Enum):
    """Normalized current pull request review decision."""

    APPROVED = 'approved'
    CHANGES_REQUESTED = 'changes_requested'
    REVIEW_REQUIRED = 'review_required'
