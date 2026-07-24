import subprocess
import sys
import unittest
from pathlib import Path
from unittest.mock import Mock, call


sys.path.insert(0, str(Path(__file__).parent))

from automation import Tag, TargetMismatchError  # noqa: E402
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
