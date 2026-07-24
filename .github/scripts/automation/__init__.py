"""Typed orchestration rules for dependency updates and patch releases."""

from automation.approval_missing_error import ApprovalMissingError
from automation.automation_error import AutomationError
from automation.candidate import Candidate
from automation.deadline import Deadline
from automation.head_changed_error import HeadChangedError
from automation.merge import Merge
from automation.merge_result import MergeResult
from automation.pull_request import PullRequest
from automation.pull_request_unavailable_error import (
    PullRequestUnavailableError,
)
from automation.recovery import Recovery
from automation.recovery_error import RecoveryError
from automation.release import Release
from automation.review_decision import ReviewDecision
from automation.run import Run
from automation.tag import Tag
from automation.target_mismatch_error import TargetMismatchError
from automation.version import Version
from automation.version_invalid_error import VersionInvalidError
from automation.version_missing_error import VersionMissingError
from automation.workflow_state import WorkflowState


latest_version = Version.latest
newer_unreleased_tag = Version.unreleased
next_patch = Version.next
patch_after_collision = Version.after_collision
release_candidate = Version.candidate
select_run = Run.select
stable_versions = Version.stable
validate_pull_request = PullRequest.validate
validate_release_target = Release.validate
validate_tag_target = Tag.validate
workflow_state = Run.workflow_state

__all__ = (
    'ApprovalMissingError',
    'AutomationError',
    'Candidate',
    'Deadline',
    'HeadChangedError',
    'Merge',
    'MergeResult',
    'PullRequest',
    'PullRequestUnavailableError',
    'Recovery',
    'RecoveryError',
    'Release',
    'ReviewDecision',
    'Run',
    'Tag',
    'TargetMismatchError',
    'Version',
    'VersionInvalidError',
    'VersionMissingError',
    'WorkflowState',
    'latest_version',
    'newer_unreleased_tag',
    'next_patch',
    'patch_after_collision',
    'release_candidate',
    'select_run',
    'stable_versions',
    'validate_pull_request',
    'validate_release_target',
    'validate_tag_target',
    'workflow_state',
)
