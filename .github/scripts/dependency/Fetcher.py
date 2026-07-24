"""A dependency release feed fetcher."""

from __future__ import annotations

from typing import Protocol


class Fetcher(Protocol):
    """Fetch one authoritative dependency release feed."""

    def __call__(self, url: str) -> bytes:
        """Return the response body."""

        ...
