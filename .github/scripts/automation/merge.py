from dataclasses import dataclass


@dataclass(frozen=True)
class Merge:
    """Repository evidence for a merged dependency automation pull request."""

    marker = '<!-- dependency-automation:v1 -->'

    number: int
    target: str
    head: str
    parents: tuple[str, ...]
    base: str
    branch: str
    body: str
    files: tuple[str, ...]
    state: str

    def is_automation(self) -> bool:
        """Return whether all immutable automation provenance facts match."""
        lines = self.body.splitlines()
        head = f'<!-- dependency-tested-head:{self.head} -->'
        parent = (
            f'<!-- dependency-tested-base:{self.parents[0]} -->'
            if len(self.parents) == 1
            else ''
        )
        return (
            self.state == 'merged'
            and self.base == 'main'
            and self.branch.startswith('automation/dependencies-')
            and lines.count(self.marker) == 1
            and lines.count(head) == 1
            and sum(
                line.startswith('<!-- dependency-tested-head:')
                for line in lines
            ) == 1
            and len(self.parents) == 1
            and lines.count(parent) == 1
            and sum(
                line.startswith('<!-- dependency-tested-base:')
                for line in lines
            ) == 1
            and (
                sum(
                    line.startswith('<!-- dependency-automation:')
                    for line in lines
                )
                == 1
            )
            and self.files == ('Dockerfile',)
        )
