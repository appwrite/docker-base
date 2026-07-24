from dataclasses import dataclass


@dataclass(frozen=True)
class Merge:
    """Repository evidence for a merged dependency automation pull request."""

    marker = '<!-- dependency-automation:v1 -->'

    number: int
    target: str
    base: str
    branch: str
    body: str
    files: tuple[str, ...]
    state: str

    def is_automation(self) -> bool:
        """Return whether all immutable automation provenance facts match."""
        return (
            self.state == 'merged'
            and self.base == 'main'
            and self.branch.startswith('automation/dependencies-')
            and self.marker in self.body
            and self.files == ('Dockerfile',)
        )
