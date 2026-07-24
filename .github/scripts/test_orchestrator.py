import subprocess
import sys
import unittest
from pathlib import Path
from unittest.mock import Mock, call


sys.path.insert(0, str(Path(__file__).parent))

from automation import (  # noqa: E402
    HeadChangedError,
    Merge,
    PullRequestUnavailableError,
    Recovery,
    Tag,
    TargetMismatchError,
)
from orchestrator import Orchestrator  # noqa: E402


class OrchestratorTest(unittest.TestCase):
    def orchestrator(self) -> Orchestrator:
        orchestrator = object.__new__(Orchestrator)
        orchestrator.repository = 'appwrite/docker-base'
        orchestrator.version = '2026-03-10'
        orchestrator.header = 'X-GitHub-Api-Version: 2026-03-10'
        return orchestrator

    def result(
        self,
        *,
        returncode: int = 0,
        output: str = '{}',
    ) -> subprocess.CompletedProcess[str]:
        return subprocess.CompletedProcess(
            args=('gh',),
            returncode=returncode,
            stdout=output,
            stderr='failure',
        )

    def release(
        self,
        *,
        identifier: int,
        tag: str,
        target: str,
        draft: bool,
        prerelease: bool = False,
        body: str = '',
    ) -> dict[str, object]:
        return {
            'id': identifier,
            'tag_name': tag,
            'target_commitish': target,
            'draft': draft,
            'prerelease': prerelease,
            'body': body,
        }

    def pull(
        self,
        *,
        head: str,
        base: str,
        state: str = 'OPEN',
        commit: str | None = None,
    ) -> dict[str, object]:
        return {
            'baseRefName': 'main',
            'baseRefOid': base,
            'headRefOid': head,
            'mergeCommit': (
                {'oid': commit}
                if commit is not None
                else None
            ),
            'mergeable': 'MERGEABLE',
            'number': 75,
            'reviewDecision': 'APPROVED',
            'state': state,
        }

    def test_handles_missing_release_by_tag_as_none(self) -> None:
        orchestrator = self.orchestrator()
        orchestrator._api = Mock(
            return_value=self.result(
                returncode=1,
                output='{"status":"404"}',
            )
        )
        orchestrator._read_graphql_release = Mock(return_value=None)

        self.assertIsNone(
            orchestrator._read_release_by_tag('1.4.5')
        )

    def test_finds_draft_after_release_by_tag_returns_404(self) -> None:
        orchestrator = self.orchestrator()
        draft = self.release(
            identifier=10,
            tag='1.4.5',
            target='a' * 40,
            draft=True,
        )
        orchestrator._api = Mock(
            return_value=self.result(
                returncode=1,
                output='{"status":"404"}',
            )
        )
        orchestrator._read_graphql_release = Mock(return_value=draft)

        self.assertIs(
            draft,
            orchestrator._read_release_by_tag('1.4.5'),
        )

    def test_exact_lookup_finds_published_release_omitted_from_list(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        first = 'a' * 40
        second = 'b' * 40
        orchestrator._listed_releases = Mock(
            return_value=(
                Recovery(
                    identifier=9,
                    tag='1.4.3',
                    target=first,
                    pull=0,
                    draft=False,
                    prerelease=False,
                    body='',
                ),
            )
        )
        payloads = {
            '1.4.3': self.release(
                identifier=9,
                tag='1.4.3',
                target=first,
                draft=False,
            ),
            '1.4.4': self.release(
                identifier=10,
                tag='1.4.4',
                target=second,
                draft=False,
            ),
        }
        orchestrator._read_release_by_tag = Mock(
            side_effect=payloads.get
        )

        releases = orchestrator._releases(
            (
                Tag(name='1.4.3', target=first),
                Tag(name='1.4.4', target=second),
            )
        )

        self.assertEqual(
            {'1.4.3', '1.4.4'},
            {release.tag for release in releases},
        )
        orchestrator._read_release_by_tag.assert_has_calls(
            [call('1.4.3'), call('1.4.4')]
        )

    def test_recovers_merge_cancelled_before_tag_on_next_no_diff_run(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        released = 'a' * 40
        target = 'b' * 40
        head = 'c' * 40
        base = 'd' * 40
        orchestrator._tags = Mock(
            return_value=(Tag(name='1.4.4', target=released),)
        )
        orchestrator._releases = Mock(
            return_value=(
                Recovery(
                    identifier=9,
                    tag='1.4.4',
                    target=released,
                    pull=0,
                    draft=False,
                    prerelease=False,
                    body='',
                ),
            )
        )
        orchestrator._merges = Mock(
            return_value=(
                Merge(
                    number=75,
                    target=target,
                    head=head,
                    parents=(base,),
                    base='main',
                    branch='automation/dependencies-100-1',
                    body=(
                        '<!-- dependency-automation:v1 -->\n'
                        f'<!-- dependency-tested-head:{head} -->\n'
                        f'<!-- dependency-tested-base:{base} -->'
                    ),
                    files=('Dockerfile',),
                    state='merged',
                ),
            )
        )
        orchestrator._write = Mock()

        orchestrator.recover()

        orchestrator._write.assert_called_once_with(
            {
                'pending': 'true',
                'tag': '',
                'head': target,
                'pull': '75',
                'draft': '',
            }
        )

    def test_resolves_recovery_commit_when_rest_merge_sha_is_null(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        head = 'a' * 40
        base = 'b' * 40
        commit = 'c' * 40
        body = (
            '<!-- dependency-automation:v1 -->\n'
            f'<!-- dependency-tested-head:{head} -->\n'
            f'<!-- dependency-tested-base:{base} -->'
        )
        orchestrator._pages = Mock(
            side_effect=[
                [
                    {
                        'number': 75,
                        'merged_at': '2026-07-24T00:00:00Z',
                        'merge_commit_sha': None,
                        'head': {
                            'ref': 'automation/dependencies-100-1',
                            'sha': head,
                        },
                        'base': {'ref': 'main'},
                        'body': body,
                    }
                ],
                [{'filename': 'Dockerfile'}],
            ]
        )
        orchestrator._read_pull = Mock(
            return_value=self.pull(
                head=head,
                base=base,
                state='MERGED',
                commit=commit,
            )
        )
        orchestrator._read_parents = Mock(return_value=(base,))

        self.assertEqual(
            (
                Merge(
                    number=75,
                    target=commit,
                    head=head,
                    parents=(base,),
                    base='main',
                    branch='automation/dependencies-100-1',
                    body=body,
                    files=('Dockerfile',),
                    state='merged',
                ),
            ),
            orchestrator._merges(),
        )

    def test_accepts_nonzero_merge_only_after_exact_remote_proof(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        head = 'a' * 40
        base = 'b' * 40
        commit = 'c' * 40
        orchestrator._read_pull = Mock(
            side_effect=[
                self.pull(head=head, base=base),
                self.pull(
                    head=head,
                    base=base,
                    state='MERGED',
                    commit=commit,
                ),
            ]
        )
        orchestrator._run = Mock(
            return_value=self.result(returncode=1)
        )
        orchestrator._read_parents = Mock(return_value=(base,))
        orchestrator._write = Mock()

        orchestrator.merge(pull=75, head=head, base=base)

        orchestrator._write.assert_called_once_with({'head': commit})

    def test_rejects_nonzero_merge_without_merged_remote_state(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        head = 'a' * 40
        base = 'b' * 40
        orchestrator._read_pull = Mock(
            side_effect=[
                self.pull(head=head, base=base),
                self.pull(head=head, base=base),
            ]
        )
        orchestrator._run = Mock(
            return_value=self.result(returncode=1)
        )
        orchestrator._write = Mock()

        with self.assertRaises(PullRequestUnavailableError):
            orchestrator.merge(pull=75, head=head, base=base)

        orchestrator._write.assert_not_called()

    def test_rejects_merge_when_squash_parent_is_not_tested_base(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        head = 'a' * 40
        base = 'b' * 40
        commit = 'c' * 40
        orchestrator._read_pull = Mock(
            side_effect=[
                self.pull(head=head, base=base),
                self.pull(
                    head=head,
                    base=base,
                    state='MERGED',
                    commit=commit,
                ),
            ]
        )
        orchestrator._run = Mock(return_value=self.result())
        orchestrator._read_parents = Mock(
            return_value=('d' * 40,)
        )
        orchestrator._write = Mock()

        with self.assertRaises(HeadChangedError):
            orchestrator.merge(pull=75, head=head, base=base)

        orchestrator._write.assert_not_called()

    def test_existing_published_release_prevents_duplicate_draft(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        orchestrator._read_release_by_tag = Mock(
            return_value=self.release(
                identifier=10,
                tag='1.4.5',
                target='a' * 40,
                draft=False,
            )
        )
        orchestrator._api = Mock()

        with self.assertRaisesRegex(RuntimeError, 'already published'):
            orchestrator._create_draft(
                tag='1.4.5',
                target='a' * 40,
                pull=75,
            )

        orchestrator._api.assert_not_called()

    def test_existing_exact_draft_avoids_duplicate_create(self) -> None:
        orchestrator = self.orchestrator()
        target = 'a' * 40
        body = orchestrator._draft_body(target, 75)
        orchestrator._read_release_by_tag = Mock(
            return_value=self.release(
                identifier=10,
                tag='1.4.5',
                target=target,
                draft=True,
                body=body,
            )
        )
        orchestrator._api = Mock()

        self.assertEqual(
            10,
            orchestrator._create_draft(
                tag='1.4.5',
                target=target,
                pull=75,
            ),
        )
        orchestrator._api.assert_not_called()

    def test_recovers_concurrently_created_draft_after_422(self) -> None:
        orchestrator = self.orchestrator()
        target = 'a' * 40
        draft = self.release(
            identifier=10,
            tag='1.4.5',
            target=target,
            draft=True,
            body=orchestrator._draft_body(target, 75),
        )
        orchestrator._read_release_by_tag = Mock(
            side_effect=[None, draft]
        )
        orchestrator._api = Mock(
            return_value=self.result(returncode=1)
        )

        self.assertEqual(
            10,
            orchestrator._create_draft(
                tag='1.4.5',
                target=target,
                pull=75,
            ),
        )

    def test_does_not_publish_when_prepublication_target_changed(self) -> None:
        orchestrator = self.orchestrator()
        orchestrator._read_tag = Mock(
            return_value=Tag(name='1.4.5', target='wrong')
        )
        orchestrator._api = Mock()

        with self.assertRaises(TargetMismatchError):
            orchestrator.publish(
                tag='1.4.5',
                target='expected',
                pull=75,
                draft=10,
            )

        orchestrator._api.assert_not_called()

    def test_returns_release_to_draft_when_postpublication_target_changed(
        self,
    ) -> None:
        orchestrator = self.orchestrator()
        orchestrator._read_tag = Mock(
            side_effect=[
                Tag(name='1.4.5', target='expected'),
                Tag(name='1.4.5', target='wrong'),
            ]
        )
        orchestrator._read_release = Mock(
            side_effect=[
                {'draft': True},
                {'draft': True},
            ]
        )
        orchestrator._validate_draft = Mock(return_value=10)
        orchestrator._api = Mock(
            side_effect=[
                self.result(
                    output=(
                        '{"tag_name":"1.4.5","draft":false,'
                        '"prerelease":false}'
                    )
                ),
                self.result(),
            ]
        )

        with self.assertRaisesRegex(RuntimeError, 'returned to draft'):
            orchestrator.publish(
                tag='1.4.5',
                target='expected',
                pull=75,
                draft=10,
            )

        self.assertEqual(
            call(
                'PATCH',
                'repos/appwrite/docker-base/releases/10',
                (('-F', 'draft=true'),),
                check=False,
            ),
            orchestrator._api.call_args_list[1],
        )
