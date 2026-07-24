from dataclasses import dataclass

from automation.target_mismatch_error import TargetMismatchError


@dataclass(frozen=True)
class Release:
    """A release and the resolved target of its associated tag."""

    tag: str
    target: str

    def validate(self, *, expected_tag: str, expected_target: str) -> None:
        """Require the expected tag and exact target commit."""
        if self.tag != expected_tag:
            raise TargetMismatchError(
                f'Expected release for {expected_tag}, found {self.tag}'
            )
        if self.target != expected_target:
            raise TargetMismatchError(
                f'Release {self.tag} targets {self.target}, '
                f'expected {expected_target}'
            )
