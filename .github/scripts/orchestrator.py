"""GitHub adapter for the weekly dependency release workflow."""

from __future__ import annotations

import json
import os
import subprocess
import sys
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Sequence


sys.path.insert(0, str(Path(__file__).parent))

from automation import (  # noqa: E402
    Deadline,
    Merge,
    Recovery,
    Release,
    Run,
    Tag,
    Version,
    WorkflowState,
    validate_release_target,
    validate_tag_target,
    workflow_state,
)


class Orchestrator:
    """Run GitHub operations behind exact, testable domain invariants."""

    def __init__(self) -> None:
        self.repository = os.environ['GITHUB_REPOSITORY']
        self.version = os.environ['GITHUB_API_VERSION']
        self.header = f'X-GitHub-Api-Version: {self.version}'

    def execute(self, arguments: Sequence[str]) -> None:
        """Dispatch one workflow operation."""
        if not arguments:
            raise ValueError('An orchestration operation is required')

        operation, *values = arguments
        if operation == 'recover' and not values:
            self.recover()
            return
        if operation == 'prepare' and len(values) == 4:
            tag, target, pull, draft = values
            self.prepare(
                tag=tag or None,
                target=target,
                pull=int(pull),
                draft=int(draft) if draft else None,
            )
            return
        if operation == 'wait' and len(values) == 2:
            self.wait(tag=values[0], target=values[1])
            return
        if operation == 'publish' and len(values) == 4:
            self.publish(
                tag=values[0],
                target=values[1],
                pull=int(values[2]),
                draft=int(values[3]),
            )
            return
        raise ValueError(f'Invalid {operation!r} arguments')

    def _run(
        self,
        arguments: Sequence[str],
        *,
        check: bool = True,
    ) -> subprocess.CompletedProcess[str]:
        return subprocess.run(
            arguments,
            check=check,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )

    def _api(
        self,
        method: str,
        endpoint: str,
        fields: Sequence[tuple[str, str]] = (),
        *,
        check: bool = True,
    ) -> subprocess.CompletedProcess[str]:
        arguments = [
            'gh',
            'api',
            '-X',
            method,
            endpoint,
            '-H',
            self.header,
        ]
        for kind, value in fields:
            arguments.extend((kind, value))
        return self._run(arguments, check=check)

    def _pages(self, endpoint: str) -> list[dict[str, Any]]:
        result = self._run(
            (
                'gh',
                'api',
                '--paginate',
                '--slurp',
                '-X',
                'GET',
                endpoint,
                '-H',
                self.header,
                '-f',
                'per_page=100',
            )
        )
        pages = json.loads(result.stdout)
        return [
            item
            for page in pages
            for item in page
            if isinstance(item, dict)
        ]

    def _write(self, values: dict[str, str]) -> None:
        with open(
            os.environ['GITHUB_OUTPUT'],
            'a',
            encoding='utf-8',
        ) as output:
            for name, value in values.items():
                print(f'{name}={value}', file=output)

    def _tags(self) -> tuple[Tag, ...]:
        prefix = 'refs/tags/'
        return tuple(
            Tag(
                name=str(item.get('ref', '')).removeprefix(prefix),
                target=str(item['object']['sha']),
            )
            for item in self._pages(
                f'repos/{self.repository}/git/matching-refs/tags/'
            )
            if str(item.get('ref', '')).startswith(prefix)
            and isinstance(item.get('object'), dict)
            and item['object'].get('type') == 'commit'
        )

    def _releases(self) -> tuple[Recovery, ...]:
        return tuple(
            Recovery(
                identifier=int(item['id']),
                tag=str(item['tag_name']),
                target=str(item['target_commitish']),
                pull=0,
                draft=bool(item['draft']),
                prerelease=bool(item['prerelease']),
                body=str(item.get('body') or ''),
            )
            for item in self._pages(f'repos/{self.repository}/releases')
        )

    def _merges(self, tags: Sequence[Tag]) -> tuple[Merge, ...]:
        merges: list[Merge] = []
        for target in {tag.target for tag in tags}:
            pulls = self._pages(
                f'repos/{self.repository}/commits/{target}/pulls'
            )
            for pull in pulls:
                number = int(pull['number'])
                files = tuple(
                    str(item['filename'])
                    for item in self._pages(
                        f'repos/{self.repository}/pulls/{number}/files'
                    )
                )
                head = pull.get('head')
                base = pull.get('base')
                merged = (
                    pull.get('merged_at') is not None
                    and pull.get('merge_commit_sha') == target
                )
                merges.append(
                    Merge(
                        number=number,
                        target=target,
                        base=(
                            str(base.get('ref', ''))
                            if isinstance(base, dict)
                            else ''
                        ),
                        branch=(
                            str(head.get('ref', ''))
                            if isinstance(head, dict)
                            else ''
                        ),
                        body=str(pull.get('body') or ''),
                        files=files,
                        state='merged' if merged else str(pull.get('state')),
                    )
                )
        return tuple(merges)

    def _read_tag(self, name: str) -> Tag | None:
        result = self._api(
            'GET',
            f'repos/{self.repository}/git/ref/tags/{name}',
            check=False,
        )
        if result.returncode != 0:
            return None
        payload = json.loads(result.stdout)
        target = payload.get('object')
        if not isinstance(target, dict) or target.get('type') != 'commit':
            raise RuntimeError(f'Tag {name} is not lightweight')
        return Tag(
            name=str(payload.get('ref', '')).removeprefix('refs/tags/'),
            target=str(target.get('sha')),
        )

    def _read_release(self, identifier: int) -> dict[str, Any]:
        result = self._api(
            'GET',
            f'repos/{self.repository}/releases/{identifier}',
        )
        payload = json.loads(result.stdout)
        if not isinstance(payload, dict):
            raise RuntimeError(f'Release {identifier} is invalid')
        return payload

    def _draft_body(self, target: str, pull: int) -> str:
        return (
            '<!-- dependency-automation:v1 -->\n'
            f'<!-- dependency-target:{target} -->\n'
            f'<!-- dependency-pull:{pull} -->\n\n'
            'Automated weekly dependency release.'
        )

    def _validate_draft(
        self,
        payload: dict[str, Any],
        *,
        tag: str,
        target: str,
        pull: int,
    ) -> int:
        draft = Recovery(
            identifier=int(payload['id']),
            tag=str(payload['tag_name']),
            target=str(payload['target_commitish']),
            pull=pull,
            draft=bool(payload['draft']),
            prerelease=bool(payload['prerelease']),
            body=str(payload.get('body') or ''),
        )
        merge = Merge(
            number=pull,
            target=target,
            base='main',
            branch='automation/dependencies-recovery',
            body=Merge.marker,
            files=('Dockerfile',),
            state='merged',
        )
        if draft.tag != tag or not draft.matches(merge):
            raise RuntimeError(f'Draft release {draft.identifier} is unsafe')
        return draft.identifier

    def recover(self) -> None:
        """Recover only state proven to originate from this automation."""
        releases = self._releases()
        published = {
            release.tag
            for release in releases
            if not release.draft
        }
        threshold = Version.stable(published)
        latest = threshold[-1] if threshold else None
        tags = tuple(
            tag
            for tag in self._tags()
            if (
                (version := Version.parse(tag.name)) is not None
                and tag.name not in published
                and (latest is None or version > latest)
            )
        )
        candidate = Recovery.select(
            tags,
            releases,
            self._merges(tags),
        )
        if candidate is None:
            self._write({'pending': 'false'})
            return

        self._write(
            {
                'pending': 'true',
                'tag': candidate.tag,
                'head': candidate.target,
                'pull': str(candidate.pull),
                'draft': (
                    str(candidate.draft)
                    if candidate.draft is not None
                    else ''
                ),
            }
        )

    def _create_tag(self, target: str) -> str:
        available = tuple(tag.name for tag in self._tags())
        candidate = str(Version.next(available))
        while True:
            result = self._api(
                'POST',
                f'repos/{self.repository}/git/refs',
                (
                    ('-f', f'ref=refs/tags/{candidate}'),
                    ('-f', f'sha={target}'),
                ),
                check=False,
            )
            if result.returncode == 0:
                break

            existing = self._read_tag(candidate)
            if existing is not None and existing.target == target:
                break
            if existing is None:
                sys.stderr.write(result.stderr)
                raise RuntimeError(f'Failed to create tag {candidate}')
            available = tuple(tag.name for tag in self._tags())
            candidate = str(
                Version.after_collision(available, candidate)
            )

        tag = self._read_tag(candidate)
        if tag is None:
            raise RuntimeError(f'Tag {candidate} is missing after creation')
        validate_tag_target(
            tag,
            expected_name=candidate,
            expected_target=target,
        )
        return candidate

    def _create_draft(self, tag: str, target: str, pull: int) -> int:
        body = self._draft_body(target, pull)
        result = self._api(
            'POST',
            f'repos/{self.repository}/releases',
            (
                ('-f', f'tag_name={tag}'),
                ('-f', f'target_commitish={target}'),
                ('-f', f'name={tag}'),
                ('-f', f'body={body}'),
                ('-F', 'draft=true'),
                ('-F', 'prerelease=false'),
                ('-F', 'generate_release_notes=true'),
            ),
            check=False,
        )
        if result.returncode == 0:
            payload = json.loads(result.stdout)
            return self._validate_draft(
                payload,
                tag=tag,
                target=target,
                pull=pull,
            )

        matches = [
            release
            for release in self._releases()
            if release.tag == tag
        ]
        if len(matches) != 1:
            sys.stderr.write(result.stderr)
            raise RuntimeError(f'Failed to create draft release {tag}')
        return self._validate_draft(
            self._read_release(matches[0].identifier),
            tag=tag,
            target=target,
            pull=pull,
        )

    def prepare(
        self,
        *,
        tag: str | None,
        target: str,
        pull: int,
        draft: int | None,
    ) -> None:
        """Create or recover an exact lightweight tag and draft release."""
        name = tag if tag is not None else self._create_tag(target)
        existing = self._read_tag(name)
        if existing is None:
            raise RuntimeError(f'Tag {name} does not exist')
        validate_tag_target(
            existing,
            expected_name=name,
            expected_target=target,
        )

        identifier = (
            self._validate_draft(
                self._read_release(draft),
                tag=name,
                target=target,
                pull=pull,
            )
            if draft is not None
            else self._create_draft(name, target, pull)
        )
        current = self._read_tag(name)
        if current is None:
            raise RuntimeError(f'Tag {name} disappeared during preparation')
        validate_tag_target(
            current,
            expected_name=name,
            expected_target=target,
        )
        self._write(
            {
                'tag': name,
                'head': target,
                'pull': str(pull),
                'draft': str(identifier),
            }
        )

    def _runs(self, tag: str, target: str) -> tuple[Run, ...]:
        result = self._api(
            'GET',
            (
                f'repos/{self.repository}/actions/workflows/'
                'build-and-push.yml/runs'
            ),
            (
                ('-f', f'branch={tag}'),
                ('-f', f'head_sha={target}'),
                ('-f', 'event=push'),
                ('-f', 'per_page=100'),
            ),
        )
        payload = json.loads(result.stdout)
        return tuple(
            Run(
                identifier=int(item['id']),
                workflow=str(item['name']),
                event=str(item['event']),
                head=str(item['head_sha']),
                branch=str(item['head_branch']),
                created=datetime.fromisoformat(
                    str(item['created_at']).replace('Z', '+00:00')
                ),
                attempt=int(item['run_attempt']),
                status=str(item['status']),
                conclusion=(
                    str(item['conclusion'])
                    if item['conclusion'] is not None
                    else None
                ),
            )
            for item in payload['workflow_runs']
        )

    def wait(self, *, tag: str, target: str) -> None:
        """Require the exact tag-push Build and Push run to succeed."""
        boundary = datetime(1970, 1, 1, tzinfo=timezone.utc)
        deadline = Deadline.after(
            datetime.now(timezone.utc),
            timedelta(minutes=120),
        )
        previous = None
        while True:
            current = self._read_tag(tag)
            if current is None:
                raise RuntimeError(f'Tag {tag} disappeared during its build')
            validate_tag_target(
                current,
                expected_name=tag,
                expected_target=target,
            )
            now = datetime.now(timezone.utc)
            state = workflow_state(
                self._runs(tag, target),
                workflow='Build and Push',
                event='push',
                head=target,
                branch=tag,
                created=boundary,
                deadline=deadline,
                now=now,
            )
            if state is not previous:
                print(f'Build and Push tag run: {state.value}', flush=True)
                previous = state
            if state is WorkflowState.SUCCEEDED:
                return
            if state in {
                WorkflowState.CANCELLED,
                WorkflowState.FAILED,
                WorkflowState.TIMED_OUT,
            }:
                raise RuntimeError(
                    f'Tag Build and Push did not succeed: {state.value}'
                )
            remaining = deadline.remaining(
                datetime.now(timezone.utc)
            ).total_seconds()
            time.sleep(min(20, max(remaining, 0)))

    def publish(
        self,
        *,
        tag: str,
        target: str,
        pull: int,
        draft: int,
    ) -> None:
        """Publish only an exact draft and re-draft any wrong target."""
        current = self._read_tag(tag)
        if current is None:
            raise RuntimeError(f'Tag {tag} disappeared before publication')
        validate_tag_target(
            current,
            expected_name=tag,
            expected_target=target,
        )
        self._validate_draft(
            self._read_release(draft),
            tag=tag,
            target=target,
            pull=pull,
        )

        result = self._api(
            'PATCH',
            f'repos/{self.repository}/releases/{draft}',
            (
                ('-F', 'draft=false'),
                ('-F', 'prerelease=false'),
                ('-f', 'make_latest=true'),
            ),
            check=False,
        )
        payload = (
            json.loads(result.stdout)
            if result.returncode == 0 and result.stdout
            else self._read_release(draft)
        )
        final = self._read_tag(tag)
        valid = (
            final is not None
            and final.name == tag
            and final.target == target
            and payload.get('tag_name') == tag
            and payload.get('draft') is False
            and payload.get('prerelease') is False
        )
        if not valid:
            rollback = self._api(
                'PATCH',
                f'repos/{self.repository}/releases/{draft}',
                (('-F', 'draft=true'),),
                check=False,
            )
            rolled_back = self._read_release(draft)
            if rolled_back.get('draft') is not True:
                sys.stderr.write(rollback.stderr)
                raise RuntimeError(
                    f'Release {tag} has an unsafe public target and '
                    'could not be returned to draft'
                )
            raise RuntimeError(
                f'Release {tag} target changed during publication; '
                'the release was returned to draft'
            )

        validate_tag_target(
            final,
            expected_name=tag,
            expected_target=target,
        )
        validate_release_target(
            Release(tag=str(payload['tag_name']), target=final.target),
            expected_tag=tag,
            expected_target=target,
        )


if __name__ == '__main__':
    Orchestrator().execute(sys.argv[1:])
