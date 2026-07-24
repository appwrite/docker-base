from automation.automation_error import AutomationError


class ApprovalMissingError(AutomationError):
    """Raised when a pull request does not currently have approval."""
