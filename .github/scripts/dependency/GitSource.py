"""A git release source."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class GitSource:
    """A git repository whose exact version tags are release candidates."""

    url: str
