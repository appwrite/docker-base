from automation.automation_error import AutomationError


class HeadChangedError(AutomationError):
    """Raised when a pull request no longer points to an approved ref."""
