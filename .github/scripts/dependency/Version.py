"""A stable semantic version."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, order=True, slots=True)
class Version:
    """A stable semantic release."""

    major: int
    minor: int
    patch: int
