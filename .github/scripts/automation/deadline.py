from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta


@dataclass(frozen=True)
class Deadline:
    """An injected absolute deadline used without sleeping."""

    at: datetime

    def __post_init__(self) -> None:
        self.require_aware(self.at, 'Deadline')

    @staticmethod
    def require_aware(value: datetime, name: str) -> None:
        """Require an offset-aware timestamp."""
        if value.tzinfo is None or value.utcoffset() is None:
            raise ValueError(f'{name} must include a timezone')

    @classmethod
    def after(cls, now: datetime, timeout: timedelta) -> Deadline:
        """Create a deadline relative to injected current time."""
        cls.require_aware(now, 'Current time')
        if timeout <= timedelta():
            raise ValueError('Timeout must be positive')
        return cls(now + timeout)

    def expired(self, now: datetime) -> bool:
        """Return whether the deadline has been reached."""
        self.require_aware(now, 'Current time')
        return now >= self.at

    def remaining(self, now: datetime) -> timedelta:
        """Return remaining time, clamped at zero."""
        self.require_aware(now, 'Current time')
        return max(self.at - now, timedelta())
