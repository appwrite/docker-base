from dataclasses import dataclass


@dataclass(frozen=True)
class Candidate:
    """A uniquely recoverable dependency release."""

    tag: str
    target: str
    pull: int
    draft: int | None
