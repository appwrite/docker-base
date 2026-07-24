"""Pure orchestration rules for dependency updates and patch releases."""

from __future__ import annotations

import re
from dataclasses import dataclass
from datetime import datetime, timedelta
from enum import Enum
from typing import Iterable, Sequence


_VERSION = re.compile(
    r'(?P<major>0|[1-9][0-9]*)\.'
    r'(?P<minor>0|[1-9][0-9]*)\.'
    r'(?P<patch>0|[1-9][0-9]*)'
)


class AutomationError(RuntimeError):
    """Base error for a failed orchestration invariant."""


class VersionMissingError(AutomationError):
    """Raised when no stable version tag exists."""


class VersionInvalidError(AutomationError):
    """Raised when a required value is not a stable version."""


class HeadChangedError(AutomationError):
    """Raised when a pull request no longer points to the approved head."""


class ApprovalMissingError(AutomationError):
    """Raised when a pull request does not currently have approval."""


class PullRequestUnavailableError(AutomationError):
    """Raised when a pull request cannot currently be merged."""


class TargetMismatchError(AutomationError):
    """Raised when a tag or release points at an unexpected commit."""


@dataclass(frozen=True, order=True)
class Version:
    """An unprefixed stable MAJOR.MINOR.PATCH version."""

    major: int
    minor: int
    patch: int

    @classmethod
    def parse(cls, value: str) -> Version | None:
        """Parse a stable version, returning None for unsupported tag names."""
        match = _VERSION.fullmatch(value)
        if match is None:
            return None

        try:
            return cls(
                major=int(match.group('major')),
                minor=int(match.group('minor')),
                patch=int(match.group('patch')),
            )
        except ValueError:
            return None

    def next_patch(self) -> Version:
        """Return the immediately following patch version."""
        return Version(self.major, self.minor, self.patch + 1)

    def __str__(self) -> str:
        return f'{self.major}.{self.minor}.{self.patch}'


def stable_versions(tags: Iterable[str]) -> tuple[Version, ...]:
    """Return unique stable versions in semantic order."""
    versions = {
        version
        for tag in tags
        if (version := Version.parse(tag)) is not None
    }
    return tuple(sorted(versions))


def latest_version(tags: Iterable[str]) -> Version:
    """Return the semantic maximum across all supplied remote tags."""
    versions = stable_versions(tags)
    if not versions:
        raise VersionMissingError('No stable remote version tag exists')
    return versions[-1]


def next_patch(tags: Iterable[str]) -> Version:
    """Compute the next patch from the semantic maximum remote tag."""
    return latest_version(tags).next_patch()


def newer_unreleased_tag(
    tags: Iterable[str],
    releases: Iterable[str],
) -> Version | None:
    """Find the newest remote tag newer than every published release."""
    tagged = stable_versions(tags)
    if not tagged:
        return None

    published = stable_versions(releases)
    threshold = published[-1] if published else None
    candidates = [
        version
        for version in tagged
        if version not in published
        and (threshold is None or version > threshold)
    ]
    return max(candidates, default=None)


def release_candidate(
    tags: Iterable[str],
    releases: Iterable[str],
) -> Version:
    """Resume an unreleased newer tag or compute a fresh patch version."""
    tags = tuple(tags)
    unreleased = newer_unreleased_tag(tags, releases)
    return unreleased if unreleased is not None else next_patch(tags)


def patch_after_collision(
    tags: Iterable[str],
    collision: str,
) -> Version:
    """Recompute a patch after refreshing tags following a create collision."""
    collided = Version.parse(collision)
    if collided is None:
        raise VersionInvalidError(
            f'Collision tag {collision!r} is not a stable version'
        )
    return next_patch((*tags, str(collided)))


def _require_aware(value: datetime, name: str) -> None:
    if value.tzinfo is None or value.utcoffset() is None:
        raise ValueError(f'{name} must include a timezone')


@dataclass(frozen=True)
class Deadline:
    """An injected absolute deadline used without sleeping."""

    at: datetime

    def __post_init__(self) -> None:
        _require_aware(self.at, 'Deadline')

    @classmethod
    def after(cls, now: datetime, timeout: timedelta) -> Deadline:
        """Create a deadline relative to injected current time."""
        _require_aware(now, 'Current time')
        if timeout <= timedelta():
            raise ValueError('Timeout must be positive')
        return cls(now + timeout)

    def expired(self, now: datetime) -> bool:
        """Return whether the deadline has been reached."""
        _require_aware(now, 'Current time')
        return now >= self.at

    def remaining(self, now: datetime) -> timedelta:
        """Return remaining time, clamped at zero."""
        _require_aware(now, 'Current time')
        return max(self.at - now, timedelta())


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
        _require_aware(self.created, 'Workflow run creation time')
        if self.attempt < 1:
            raise ValueError('Workflow run attempt must be positive')


class WorkflowState(Enum):
    """A closed set of states used by polling orchestration."""

    MISSING = 'missing'
    PENDING = 'pending'
    SUCCEEDED = 'succeeded'
    FAILED = 'failed'
    CANCELLED = 'cancelled'
    TIMED_OUT = 'timed_out'


def select_run(
    runs: Sequence[Run],
    *,
    workflow: str,
    event: str,
    head: str,
    branch: str,
    created: datetime,
) -> Run | None:
    """Select the newest exact run and its newest rerun attempt."""
    _require_aware(created, 'Workflow run boundary')
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


def workflow_state(
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
    run = select_run(
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


class ReviewDecision(Enum):
    """Normalized current pull request review decision."""

    APPROVED = 'approved'
    CHANGES_REQUESTED = 'changes_requested'
    REVIEW_REQUIRED = 'review_required'


@dataclass(frozen=True)
class PullRequest:
    """Current pull request state supplied immediately before merging."""

    number: int
    head: str
    state: str
    review: ReviewDecision


def validate_pull_request(
    pull_request: PullRequest,
    expected_head: str,
) -> None:
    """Require the unchanged head and a current approval before merging."""
    if pull_request.head != expected_head:
        raise HeadChangedError(
            f'Pull request #{pull_request.number} head changed from '
            f'{expected_head} to {pull_request.head}'
        )
    if pull_request.state != 'open':
        raise PullRequestUnavailableError(
            f'Pull request #{pull_request.number} is {pull_request.state}'
        )
    if pull_request.review is not ReviewDecision.APPROVED:
        raise ApprovalMissingError(
            f'Pull request #{pull_request.number} is not currently approved'
        )


@dataclass(frozen=True)
class Tag:
    """A remote tag resolved to its target commit."""

    name: str
    target: str


@dataclass(frozen=True)
class Release:
    """A published release resolved to its tag target commit."""

    tag: str
    target: str


def validate_tag_target(
    tag: Tag,
    *,
    expected_name: str,
    expected_target: str,
) -> None:
    """Require a tag with the expected name and exact target commit."""
    if tag.name != expected_name:
        raise TargetMismatchError(
            f'Expected tag {expected_name}, found {tag.name}'
        )
    if tag.target != expected_target:
        raise TargetMismatchError(
            f'Tag {tag.name} targets {tag.target}, expected {expected_target}'
        )


def validate_release_target(
    release: Release,
    *,
    expected_tag: str,
    expected_target: str,
) -> None:
    """Require a release for the expected tag and exact target commit."""
    if release.tag != expected_tag:
        raise TargetMismatchError(
            f'Expected release for {expected_tag}, found {release.tag}'
        )
    if release.target != expected_target:
        raise TargetMismatchError(
            f'Release {release.tag} targets {release.target}, '
            f'expected {expected_target}'
        )
