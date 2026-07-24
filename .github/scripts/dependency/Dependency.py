"""A pinned Dockerfile dependency."""

from __future__ import annotations

from dataclasses import dataclass

from dependency.Source import Source


@dataclass(frozen=True, slots=True)
class Dependency:
    """A Dockerfile variable and its authoritative release source."""

    name: str
    variable: str
    source: Source
