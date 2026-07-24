from __future__ import annotations

from dataclasses import dataclass
from typing import Sequence

from automation.candidate import Candidate
from automation.merge import Merge
from automation.recovery_error import RecoveryError
from automation.tag import Tag
from automation.version import Version


@dataclass(frozen=True)
class Recovery:
    """Select safe release recovery state from repository facts."""

    marker = '<!-- dependency-automation:v1 -->'

    identifier: int
    tag: str
    target: str
    pull: int
    draft: bool
    prerelease: bool
    body: str

    def matches(self, merge: Merge) -> bool:
        """Return whether this draft records the exact merge provenance."""
        lines = self.body.splitlines()
        return (
            self.draft
            and not self.prerelease
            and lines.count(self.marker) == 1
            and lines.count(
                f'<!-- dependency-target:{merge.target} -->'
            ) == 1
            and lines.count(
                f'<!-- dependency-pull:{merge.number} -->'
            ) == 1
            and self.pull in {0, merge.number}
            and self.target == merge.target
        )

    @classmethod
    def select(
        cls,
        tags: Sequence[Tag],
        releases: Sequence[Recovery],
        merges: Sequence[Merge],
    ) -> Candidate | None:
        """Select one qualified unpublished merge or fail closed."""
        released = {
            release.tag
            for release in releases
            if not release.draft
        }
        stable_published = Version.stable(
            release.tag
            for release in releases
            if not release.draft and not release.prerelease
        )
        threshold = stable_published[-1] if stable_published else None
        candidates: list[Candidate] = []
        targets = {tag.target for tag in tags}

        for tag in tags:
            version = Version.parse(tag.name)
            if (
                version is None
                or tag.name in released
                or (threshold is not None and version <= threshold)
            ):
                continue

            evidence = [
                merge
                for merge in merges
                if merge.target == tag.target and merge.is_automation()
            ]
            if len(evidence) != 1:
                continue

            merge = evidence[0]
            drafts = [
                release
                for release in releases
                if release.tag == tag.name and release.matches(merge)
            ]
            if len(drafts) > 1:
                raise RecoveryError(
                    f'Multiple automation drafts exist for {tag.name}'
                )
            candidates.append(
                Candidate(
                    tag=tag.name,
                    target=tag.target,
                    pull=merge.number,
                    draft=drafts[0].identifier if drafts else None,
                )
            )

        candidates.extend(
            Candidate(
                tag=None,
                target=merge.target,
                pull=merge.number,
                draft=None,
            )
            for merge in merges
            if merge.target not in targets and merge.is_automation()
        )

        unique = {
            (candidate.tag, candidate.target, candidate.pull, candidate.draft):
            candidate
            for candidate in candidates
        }
        if len(unique) > 1:
            names = ', '.join(
                sorted(
                    candidate.tag or f'pull request #{candidate.pull}'
                    for candidate in unique.values()
                )
            )
            raise RecoveryError(
                f'Multiple dependency releases are recoverable: {names}'
            )
        candidate = next(iter(unique.values()), None)
        marked = [
            release
            for release in releases
            if release.draft and cls.marker in release.body
        ]
        if marked and (
            candidate is None
            or any(
                release.identifier != candidate.draft
                for release in marked
            )
        ):
            raise RecoveryError(
                'Unsafe dependency automation draft state exists'
            )
        return candidate
