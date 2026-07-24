from automation.automation_error import AutomationError


class TargetMismatchError(AutomationError):
    """Raised when a tag or release points at an unexpected commit."""
