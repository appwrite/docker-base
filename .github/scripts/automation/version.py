from __future__ import annotations

import re
from dataclasses import dataclass
from typing import ClassVar, Iterable, Pattern

from automation.version_invalid_error import VersionInvalidError
from automation.version_missing_error import VersionMissingError


@dataclass(frozen=True, order=True)
class Version:
    """An unprefixed stable MAJOR.MINOR.PATCH version."""

    pattern: ClassVar[Pattern[str]] = re.compile(
        r'(?P<major>0|[1-9][0-9]*)\.'
        r'(?P<minor>0|[1-9][0-9]*)\.'
        r'(?P<patch>0|[1-9][0-9]*)'
    )

    major: int
    minor: int
    patch: int

    @classmethod
    def parse(cls, value: str) -> Version | None:
        """Parse a stable version, returning None for unsupported tag names."""
        match = cls.pattern.fullmatch(value)
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

    @classmethod
    def stable(cls, tags: Iterable[str]) -> tuple[Version, ...]:
        """Return unique stable versions in semantic order."""
        versions = {
            version
            for tag in tags
            if (version := cls.parse(tag)) is not None
        }
        return tuple(sorted(versions))

    @classmethod
    def latest(cls, tags: Iterable[str]) -> Version:
        """Return the semantic maximum across all supplied remote tags."""
        versions = cls.stable(tags)
        if not versions:
            raise VersionMissingError('No stable remote version tag exists')
        return versions[-1]

    @classmethod
    def next(cls, tags: Iterable[str]) -> Version:
        """Compute the next patch from the semantic maximum remote tag."""
        return cls.latest(tags).next_patch()

    @classmethod
    def unreleased(
        cls,
        tags: Iterable[str],
        releases: Iterable[str],
    ) -> Version | None:
        """Find the newest remote tag newer than every published release."""
        tagged = cls.stable(tags)
        if not tagged:
            return None

        published = cls.stable(releases)
        threshold = published[-1] if published else None
        candidates = [
            version
            for version in tagged
            if version not in published
            and (threshold is None or version > threshold)
        ]
        return max(candidates, default=None)

    @classmethod
    def candidate(
        cls,
        tags: Iterable[str],
        releases: Iterable[str],
    ) -> Version:
        """Resume an unreleased newer tag or compute a fresh patch version."""
        tags = tuple(tags)
        unreleased = cls.unreleased(tags, releases)
        return unreleased if unreleased is not None else cls.next(tags)

    @classmethod
    def after_collision(
        cls,
        tags: Iterable[str],
        collision: str,
    ) -> Version:
        """Recompute a patch after refreshing tags following a collision."""
        collided = cls.parse(collision)
        if collided is None:
            raise VersionInvalidError(
                f'Collision tag {collision!r} is not a stable version'
            )
        return cls.next((*tags, str(collided)))

    def next_patch(self) -> Version:
        """Return the immediately following patch version."""
        return Version(self.major, self.minor, self.patch + 1)

    def __str__(self) -> str:
        return f'{self.major}.{self.minor}.{self.patch}'
