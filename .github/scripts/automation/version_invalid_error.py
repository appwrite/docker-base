from automation.automation_error import AutomationError


class VersionInvalidError(AutomationError):
    """Raised when a required value is not a stable version."""
