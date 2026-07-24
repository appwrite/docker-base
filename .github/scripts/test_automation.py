import sys
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path


sys.path.insert(0, str(Path(__file__).parent))

from automation import (  # noqa: E402
    ApprovalMissingError,
    Candidate,
    Deadline,
    HeadChangedError,
    Merge,
    MergeResult,
    PullRequest,
    Recovery,
    RecoveryError,
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

    def test_does_not_accept_branch_run_for_tag_release(self) -> None:
        state = workflow_state(
            [run(branch='main')],
            workflow='Build and Push',
            event='push',
            head='approved-head',
            branch='1.4.5',
            created=START,
            deadline=self.deadline,
            now=START,
        )

        self.assertIs(WorkflowState.MISSING, state)

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
                base='tested-base',
                state='open',
                review=ReviewDecision.APPROVED,
            ),
            'approved-head',
            'tested-base',
        )

    def test_rejects_changed_head_even_if_currently_approved(self) -> None:
        with self.assertRaises(HeadChangedError):
            validate_pull_request(
                PullRequest(
                    number=75,
                    head='changed-head',
                    base='tested-base',
                    state='open',
                    review=ReviewDecision.APPROVED,
                ),
                'approved-head',
                'tested-base',
            )

    def test_rejects_changed_base_after_ci_succeeds(self) -> None:
        with self.assertRaises(HeadChangedError):
            validate_pull_request(
                PullRequest(
                    number=75,
                    head='approved-head',
                    base='changed-base',
                    state='open',
                    review=ReviewDecision.APPROVED,
                ),
                'approved-head',
                'tested-base',
            )

    def test_rejects_missing_current_approval(self) -> None:
        with self.assertRaises(ApprovalMissingError):
            validate_pull_request(
                PullRequest(
                    number=75,
                    head='approved-head',
                    base='tested-base',
                    state='open',
                    review=ReviewDecision.REVIEW_REQUIRED,
                ),
                'approved-head',
                'tested-base',
            )


class MergeResultTest(unittest.TestCase):
    def test_accepts_merge_for_exact_tested_head(self) -> None:
        self.assertEqual(
            'b' * 40,
            MergeResult(
                head='a' * 40,
                state='merged',
                commit='b' * 40,
                parents=('c' * 40,),
            ).validate('a' * 40, 'c' * 40),
        )

    def test_rejects_merged_state_for_a_different_final_head(self) -> None:
        with self.assertRaises(HeadChangedError):
            MergeResult(
                head='c' * 40,
                state='merged',
                commit='b' * 40,
                parents=('d' * 40,),
            ).validate('a' * 40, 'd' * 40)

    def test_rejects_merge_commit_for_a_different_tested_base(self) -> None:
        with self.assertRaises(HeadChangedError):
            MergeResult(
                head='a' * 40,
                state='merged',
                commit='b' * 40,
                parents=('e' * 40,),
            ).validate('a' * 40, 'd' * 40)

    def test_rejects_a_non_squash_merge_commit(self) -> None:
        with self.assertRaises(HeadChangedError):
            MergeResult(
                head='a' * 40,
                state='merged',
                commit='b' * 40,
                parents=('c' * 40, 'd' * 40),
            ).validate('a' * 40, 'c' * 40)


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


class RecoveryTest(unittest.TestCase):
    def merge(
        self,
        *,
        number: int = 75,
        target: str = 'a' * 40,
        head: str = 'b' * 40,
        parent: str = 'c' * 40,
        tested_base: str | None = None,
        body: str | None = None,
        files: tuple[str, ...] = ('Dockerfile',),
    ) -> Merge:
        proof = parent if tested_base is None else tested_base
        return Merge(
            number=number,
            target=target,
            head=head,
            parents=(parent,),
            base='main',
            branch='automation/dependencies-100-1',
            body=(
                (
                    '<!-- dependency-automation:v1 -->\n'
                    f'<!-- dependency-tested-head:{head} -->\n'
                    f'<!-- dependency-tested-base:{proof} -->'
                )
                if body is None
                else body
            ),
            files=files,
            state='merged',
        )

    def release(
        self,
        *,
        identifier: int = 10,
        tag: str = '1.4.5',
        target: str = 'a' * 40,
        pull: int = 75,
        draft: bool = True,
    ) -> Recovery:
        return Recovery(
            identifier=identifier,
            tag=tag,
            target=target,
            pull=pull,
            draft=draft,
            prerelease=False,
            body=(
                '<!-- dependency-automation:v1 -->\n'
                f'<!-- dependency-target:{target} -->\n'
                f'<!-- dependency-pull:{pull} -->'
            ),
        )

    def test_resumes_draft_after_publish_failure(self) -> None:
        target = 'a' * 40

        self.assertEqual(
            Candidate(
                tag='1.4.5',
                target=target,
                pull=75,
                draft=10,
            ),
            Recovery.select(
                [Tag(name='1.4.5', target=target)],
                [
                    self.release(),
                    self.release(
                        identifier=9,
                        tag='1.4.4',
                        draft=False,
                    ),
                ],
                [self.merge()],
            )
        )

    def test_resumes_tag_when_draft_creation_failed_and_next_run_has_no_diff(
        self,
    ) -> None:
        target = 'a' * 40

        self.assertEqual(
            Candidate(
                tag='1.4.5',
                target=target,
                pull=75,
                draft=None,
            ),
            Recovery.select(
                [Tag(name='1.4.5', target=target)],
                [
                    self.release(
                        identifier=9,
                        tag='1.4.4',
                        draft=False,
                    )
                ],
                [self.merge()],
            )
        )

    def test_resumes_proven_merge_when_cancelled_before_tag_creation(
        self,
    ) -> None:
        target = 'd' * 40

        self.assertEqual(
            Candidate(
                tag=None,
                target=target,
                pull=76,
                draft=None,
            ),
            Recovery.select(
                [Tag(name='1.4.4', target='a' * 40)],
                [self.release(tag='1.4.4', draft=False)],
                [self.merge(number=76, target=target)],
            ),
        )

    def test_does_not_resume_merge_of_an_untested_base(self) -> None:
        self.assertIsNone(
            Recovery.select(
                [Tag(name='1.4.4', target='a' * 40)],
                [self.release(tag='1.4.4', draft=False)],
                [
                    self.merge(
                        target='d' * 40,
                        parent='e' * 40,
                        tested_base='c' * 40,
                    )
                ],
            )
        )

    def test_fails_closed_for_ambiguous_proven_untagged_merges(self) -> None:
        with self.assertRaises(RecoveryError):
            Recovery.select(
                [Tag(name='1.4.4', target='a' * 40)],
                [self.release(tag='1.4.4', draft=False)],
                [
                    self.merge(number=76, target='d' * 40),
                    self.merge(number=77, target='e' * 40),
                ],
            )

    def test_ignores_unrelated_orphan_tag(self) -> None:
        self.assertIsNone(
            Recovery.select(
                [Tag(name='9.9.9', target='b' * 40)],
                [self.release(tag='1.4.4', draft=False)],
                [self.merge(body='No automation marker')],
            )
        )

    def test_ignores_tag_for_unmarked_or_multi_file_pull_request(self) -> None:
        target = 'a' * 40

        self.assertIsNone(
            Recovery.select(
                [Tag(name='1.4.5', target=target)],
                [self.release(tag='1.4.4', draft=False)],
                [
                    self.merge(body='No automation marker'),
                    self.merge(files=('Dockerfile', 'README.md')),
                ],
            )
        )

    def test_fails_closed_for_multiple_recoverable_releases(self) -> None:
        first = 'a' * 40
        second = 'b' * 40

        with self.assertRaises(RecoveryError):
            Recovery.select(
                [
                    Tag(name='1.4.5', target=first),
                    Tag(name='1.4.6', target=second),
                ],
                [self.release(tag='1.4.4', draft=False)],
                [
                    self.merge(target=first),
                    self.merge(number=76, target=second),
                ],
            )

    def test_does_not_resume_wrong_target_draft(self) -> None:
        target = 'a' * 40

        with self.assertRaises(RecoveryError):
            Recovery.select(
                [Tag(name='1.4.5', target=target)],
                [
                    self.release(tag='1.4.4', draft=False),
                    self.release(target='b' * 40),
                ],
                [self.merge()],
            )


if __name__ == '__main__':
    unittest.main()
