"""A dependency update selection."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class Change:
    """The current and selected value for one dependency."""

    name: str
    current: str
    latest: str

    @property
    def changed(self) -> bool:
        return self.current != self.latest
