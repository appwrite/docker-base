import sys
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path


sys.path.insert(0, str(Path(__file__).parent))

from automation import (  # noqa: E402
    ApprovalMissingError,
    Deadline,
    HeadChangedError,
    PullRequest,
    Release,
    ReviewDecision,
    Run,
    Tag,
    TargetMismatchError,
    Version,
    VersionInvalidError,
    VersionMissingError,
    WorkflowState,
    latest_version,
    newer_unreleased_tag,
    next_patch,
    patch_after_collision,
    release_candidate,
    select_run,
    stable_versions,
    validate_pull_request,
    validate_release_target,
    validate_tag_target,
    workflow_state,
)


UTC = timezone.utc
START = datetime(2026, 7, 24, 8, 0, tzinfo=UTC)


def run(
    *,
    identifier: int = 1,
    workflow: str = 'Build and Push',
    event: str = 'push',
    head: str = 'approved-head',
    branch: str = 'main',
    created: datetime = START,
    attempt: int = 1,
    status: str = 'completed',
    conclusion: str | None = 'success',
) -> Run:
    return Run(
        identifier=identifier,
        workflow=workflow,
        event=event,
        head=head,
        branch=branch,
        created=created,
        attempt=attempt,
        status=status,
        conclusion=conclusion,
    )


class VersionTest(unittest.TestCase):
    def test_orders_stable_versions_semantically(self) -> None:
        self.assertEqual(
            (
                Version(1, 2, 99),
                Version(1, 10, 0),
                Version(2, 0, 0),
            ),
            stable_versions(
                ['1.10.0', '2.0.0', '1.2.99', '1.10.0']
            ),
        )

    def test_ignores_nonstable_and_prefixed_tags(self) -> None:
        self.assertEqual(
            (Version(0, 0, 0), Version(12, 34, 56)),
            stable_versions(
                [
                    '0.0.0',
                    '12.34.56',
                    'v12.34.57',
                    '12.34.57-rc.1',
                    '12.34',
                    '12.34.57+build',
                    '01.2.3',
                    '1.02.3',
                    '1.2.03',
                    '',
                ]
            ),
        )

    def test_next_patch_uses_semantic_maximum_of_all_remote_tags(self) -> None:
        self.assertEqual(
            Version(2, 0, 1),
            next_patch(
                ['1.999.999', '2.0.0', 'v9.0.0', '9.0.0-rc.1']
            ),
        )

    def test_latest_version_requires_a_stable_remote_tag(self) -> None:
        with self.assertRaises(VersionMissingError):
            latest_version(['v1.0.0', '1.0.0-rc.1'])

    def test_resumes_newest_tag_newer_than_latest_release(self) -> None:
        tags = ['1.4.1', '1.4.2', '1.4.3', '1.4.4']
        releases = ['1.4.1', '1.4.3']

        self.assertEqual(
            Version(1, 4, 4),
            newer_unreleased_tag(tags, releases),
        )
        self.assertEqual(
            Version(1, 4, 4),
            release_candidate(tags, releases),
        )

    def test_ignores_unreleased_holes_older_than_latest_release(self) -> None:
        self.assertIsNone(
            newer_unreleased_tag(
                ['1.4.1', '1.4.2', '1.4.3'],
                ['1.4.1', '1.4.3'],
            )
        )

    def test_computes_new_patch_when_every_newer_tag_is_released(self) -> None:
        self.assertEqual(
            Version(1, 4, 5),
            release_candidate(
                ['1.4.3', '1.4.4'],
                ['1.4.3', '1.4.4'],
            ),
        )

    def test_recomputes_after_collision_from_refreshed_remote_tags(self) -> None:
        self.assertEqual(
            Version(1, 4, 8),
            patch_after_collision(
                ['1.4.4', '1.4.5', '1.4.7'],
                '1.4.5',
            ),
        )

    def test_rejects_malformed_collision_tag(self) -> None:
        with self.assertRaises(VersionInvalidError):
            patch_after_collision(['1.4.4'], 'v1.4.5')


class DeadlineTest(unittest.TestCase):
    def test_uses_injected_time_and_clamps_remaining_time(self) -> None:
        deadline = Deadline.after(START, timedelta(minutes=10))

        self.assertFalse(deadline.expired(START + timedelta(minutes=9)))
        self.assertTrue(deadline.expired(START + timedelta(minutes=10)))
        self.assertEqual(
            timedelta(minutes=1),
            deadline.remaining(START + timedelta(minutes=9)),
        )
        self.assertEqual(
            timedelta(),
            deadline.remaining(START + timedelta(minutes=11)),
        )

    def test_rejects_naive_times_and_nonpositive_timeouts(self) -> None:
        with self.assertRaises(ValueError):
            Deadline(datetime(2026, 7, 24, 8, 0))
        with self.assertRaises(ValueError):
            Deadline.after(START, timedelta())


class WorkflowTest(unittest.TestCase):
    def setUp(self) -> None:
        self.deadline = Deadline.after(START, timedelta(minutes=30))

    def state(
        self,
        runs: list[Run],
        *,
        now: datetime = START,
    ) -> WorkflowState:
        return workflow_state(
            runs,
            workflow='Build and Push',
            event='push',
            head='approved-head',
            branch='main',
            created=START,
            deadline=self.deadline,
            now=now,
        )

    def test_selects_only_exact_runs_at_or_after_boundary(self) -> None:
        expected = run(identifier=8, created=START + timedelta(seconds=1))
        runs = [
            run(identifier=1, workflow='Dive Test'),
            run(identifier=2, event='pull_request'),
            run(identifier=3, head='other-head'),
            run(identifier=4, branch='feature'),
            run(identifier=5, created=START - timedelta(microseconds=1)),
            expected,
        ]

        self.assertIs(
            expected,
            select_run(
                runs,
                workflow='Build and Push',
                event='push',
                head='approved-head',
                branch='main',
                created=START,
            ),
        )

    def test_selects_newest_run_then_newest_rerun_attempt(self) -> None:
        earlier = START + timedelta(seconds=1)
        later = START + timedelta(seconds=2)
        expected = run(identifier=20, created=later, attempt=2)
        runs = [
            run(identifier=10, created=earlier, attempt=3),
            run(
                identifier=20,
                created=later,
                attempt=1,
                conclusion='failure',
            ),
            expected,
        ]

        self.assertIs(
            expected,
            select_run(
                runs,
                workflow='Build and Push',
                event='push',
                head='approved-head',
                branch='main',
                created=START,
            ),
        )

    def test_reports_missing_run_before_deadline(self) -> None:
        self.assertIs(WorkflowState.MISSING, self.state([]))

    def test_reports_successful_run(self) -> None:
        self.assertIs(WorkflowState.SUCCEEDED, self.state([run()]))

    def test_reports_failed_run(self) -> None:
        self.assertIs(
            WorkflowState.FAILED,
            self.state([run(conclusion='failure')]),
        )

    def test_reports_cancelled_run(self) -> None:
        self.assertIs(
            WorkflowState.CANCELLED,
            self.state([run(conclusion='cancelled')]),
        )

    def test_reports_run_timeout_conclusion(self) -> None:
        self.assertIs(
            WorkflowState.TIMED_OUT,
            self.state([run(conclusion='timed_out')]),
        )

    def test_reports_pending_run_before_deadline(self) -> None:
        self.assertIs(
            WorkflowState.PENDING,
            self.state(
                [run(status='in_progress', conclusion=None)],
                now=START + timedelta(minutes=29),
            ),
        )

    def test_times_out_pending_run_at_deadline(self) -> None:
        self.assertIs(
            WorkflowState.TIMED_OUT,
            self.state(
                [run(status='queued', conclusion=None)],
                now=START + timedelta(minutes=30),
            ),
        )

    def test_times_out_missing_run_at_deadline(self) -> None:
        self.assertIs(
            WorkflowState.TIMED_OUT,
            self.state([], now=START + timedelta(minutes=30)),
        )

    def test_preserves_terminal_failure_after_deadline(self) -> None:
        self.assertIs(
            WorkflowState.FAILED,
            self.state(
                [run(conclusion='failure')],
                now=START + timedelta(minutes=31),
            ),
        )


class PullRequestTest(unittest.TestCase):
    def test_accepts_current_approved_head(self) -> None:
        validate_pull_request(
            PullRequest(
                number=75,
                head='approved-head',
                state='open',
                review=ReviewDecision.APPROVED,
            ),
            'approved-head',
        )

    def test_rejects_changed_head_even_if_currently_approved(self) -> None:
        with self.assertRaises(HeadChangedError):
            validate_pull_request(
                PullRequest(
                    number=75,
                    head='changed-head',
                    state='open',
                    review=ReviewDecision.APPROVED,
                ),
                'approved-head',
            )

    def test_rejects_missing_current_approval(self) -> None:
        with self.assertRaises(ApprovalMissingError):
            validate_pull_request(
                PullRequest(
                    number=75,
                    head='approved-head',
                    state='open',
                    review=ReviewDecision.REVIEW_REQUIRED,
                ),
                'approved-head',
            )


class TargetTest(unittest.TestCase):
    def test_accepts_exact_tag_and_release_targets(self) -> None:
        validate_tag_target(
            Tag(name='1.4.5', target='merged-head'),
            expected_name='1.4.5',
            expected_target='merged-head',
        )
        validate_release_target(
            Release(tag='1.4.5', target='merged-head'),
            expected_tag='1.4.5',
            expected_target='merged-head',
        )

    def test_rejects_tag_target_mismatch(self) -> None:
        with self.assertRaises(TargetMismatchError):
            validate_tag_target(
                Tag(name='1.4.5', target='wrong-head'),
                expected_name='1.4.5',
                expected_target='merged-head',
            )

    def test_rejects_release_target_mismatch(self) -> None:
        with self.assertRaises(TargetMismatchError):
            validate_release_target(
                Release(tag='1.4.5', target='wrong-head'),
                expected_tag='1.4.5',
                expected_target='merged-head',
            )


if __name__ == '__main__':
    unittest.main()
