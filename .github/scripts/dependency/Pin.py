"""A located Dockerfile dependency pin."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class Pin:
    """An exact declaration location in the Dockerfile."""

    name: str
    current: str
    start: int
    end: int
