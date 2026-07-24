"""A PECL release source."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class PeclSource:
    """A PECL stable-release feed."""

    url: str
