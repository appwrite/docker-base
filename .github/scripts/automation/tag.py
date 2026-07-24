from dataclasses import dataclass

from automation.target_mismatch_error import TargetMismatchError


@dataclass(frozen=True)
class Tag:
    """A remote tag resolved to its target commit."""

    name: str
    target: str

    def validate(self, *, expected_name: str, expected_target: str) -> None:
        """Require the expected name and exact target commit."""
        if self.name != expected_name:
            raise TargetMismatchError(
                f'Expected tag {expected_name}, found {self.name}'
            )
        if self.target != expected_target:
            raise TargetMismatchError(
                f'Tag {self.name} targets {self.target}, '
                f'expected {expected_target}'
            )
