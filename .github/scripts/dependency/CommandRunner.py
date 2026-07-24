"""A dependency discovery command runner."""

from __future__ import annotations

from typing import Protocol


class CommandRunner(Protocol):
    """Execute one dependency discovery command."""

    def __call__(self, command: tuple[str, ...]) -> str:
        """Return the command's standard output."""

        ...
