from automation.automation_error import AutomationError


class RecoveryError(AutomationError):
    """Raised when release recovery state is unsafe or ambiguous."""
