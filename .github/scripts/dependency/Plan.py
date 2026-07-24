"""A complete dependency update plan."""

from __future__ import annotations

from dataclasses import dataclass

from dependency.Change import Change


@dataclass(frozen=True, slots=True)
class Plan:
    """The complete in-memory Dockerfile update."""

    content: str
    changes: tuple[Change, ...]

    @property
    def changed(self) -> bool:
        return any(change.changed for change in self.changes)
