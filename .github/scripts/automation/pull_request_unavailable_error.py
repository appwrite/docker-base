from automation.automation_error import AutomationError


class PullRequestUnavailableError(AutomationError):
    """Raised when a pull request cannot currently be merged."""
