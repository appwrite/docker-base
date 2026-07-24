from automation.automation_error import AutomationError


class VersionMissingError(AutomationError):
    """Raised when no stable version tag exists."""
