"""Dependency updater failures."""


class UpdateError(RuntimeError):
    """Raised when dependency discovery or Dockerfile validation fails."""
