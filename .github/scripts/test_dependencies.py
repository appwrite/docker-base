#!/usr/bin/env python3
"""Offline tests for the Dockerfile dependency updater."""

from __future__ import annotations

import ast
import inspect
import re
import sys
import tempfile
import unittest
from dataclasses import FrozenInstanceError
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import dependencies


CURRENT: dict[str, str] = {
    'brotli': '0.18.3',
    'imagick': '3.8.1',
    'lz4': '0.6.0',
    'maxminddb': 'v1.13.1',
    'mongodb': '2.2.1',
    'protobuf': '5.34.0',
    'redis': '6.3.0',
    'scrypt': '2.0.1',
    'snappy': '0.2.3',
    'swoole': 'v6.2.0',
    'xdebug': '3.5.1',
    'yaml': '2.3.0',
    'zstd': '0.15.2',
}
DECLARATIONS: tuple[tuple[str, str], ...] = (
    ('brotli', 'PHP_BROTLI_VERSION'),
    ('imagick', 'PHP_IMAGICK_VERSION'),
    ('lz4', 'PHP_LZ4_VERSION'),
    ('maxminddb', 'PHP_MAXMINDDB_VERSION'),
    ('mongodb', 'PHP_MONGODB_VERSION'),
    ('protobuf', 'PHP_PROTOBUF_VERSION'),
    ('redis', 'PHP_REDIS_VERSION'),
    ('scrypt', 'PHP_SCRYPT_VERSION'),
    ('snappy', 'PHP_SNAPPY_VERSION'),
    ('swoole', 'PHP_SWOOLE_VERSION'),
    ('yaml', 'PHP_YAML_VERSION'),
    ('zstd', 'PHP_ZSTD_VERSION'),
)
EXPECTED_DOCKERFILE_DECLARATIONS = frozenset(
    (
        'BASE_IMAGE',
        'PHP_BROTLI_VERSION',
        'PHP_IMAGICK_VERSION',
        'PHP_LZ4_VERSION',
        'PHP_MAXMINDDB_VERSION',
        'PHP_MONGODB_VERSION',
        'PHP_PROTOBUF_VERSION',
        'PHP_REDIS_VERSION',
        'PHP_SCRYPT_VERSION',
        'PHP_SNAPPY_VERSION',
        'PHP_SWOOLE_VERSION',
        'PHP_XDEBUG_VERSION',
        'PHP_YAML_VERSION',
        'PHP_ZSTD_VERSION',
    )
)
OLD_DIGEST = 'sha256:' + ('1' * 64)
NEW_DIGEST = 'sha256:' + ('2' * 64)


def dockerfile() -> str:
    """Build a representative Dockerfile containing every required pin."""

    lines = [
        f'ARG BASE_IMAGE="{dependencies.BASE_NAME}@{OLD_DIGEST}"',
        '',
        'FROM $BASE_IMAGE AS compile',
        '',
        'ENV \\',
    ]
    for index, (name, variable) in enumerate(DECLARATIONS):
        suffix = ' \\' if index < len(DECLARATIONS) - 1 else ''
        lines.append(
            f'    {variable}="{CURRENT[name]}"{suffix}'
        )
    lines.extend(
        (
            '',
            '# References should never be rewritten:',
            'RUN echo "$PHP_REDIS_VERSION"',
            '',
            'FROM compile AS xdebug-build',
            '',
            f'ENV PHP_XDEBUG_VERSION="{CURRENT["xdebug"]}"',
            '',
        )
    )
    return '\n'.join(lines)


def git_tags(*spellings: str) -> str:
    """Build offline git ls-remote output."""

    return ''.join(
        f'{"a" * 40}\trefs/tags/{spelling}\n'
        for spelling in spellings
    )


def pecl_releases(*releases: tuple[str, str]) -> bytes:
    """Build an offline namespaced PECL allreleases response."""

    entries = ''.join(
        f'<r><v>{version}</v><s>{state}</s></r>'
        for version, state in releases
    )
    return (
        '<?xml version="1.0"?>'
        '<a xmlns="http://pear.php.net/dtd/rest.allreleases">'
        f'<p>protobuf</p>{entries}</a>'
    ).encode()


class Discovery:
    """Injectable, offline replacement for every command and network read."""

    def __init__(
        self,
        *,
        digest: str = OLD_DIGEST,
        releases: dict[str, tuple[str, ...]] | None = None,
        pecl: tuple[tuple[str, str], ...] | None = None,
    ) -> None:
        self.digest = digest
        self.releases = releases or {}
        self.pecl = pecl or ((CURRENT['protobuf'], 'stable'),)
        self.commands: list[tuple[str, ...]] = []
        self.urls: list[str] = []

    def run(self, command: tuple[str, ...]) -> str:
        """Return deterministic output for docker buildx or git."""

        self.commands.append(command)
        if command == (
            'docker',
            'buildx',
            'imagetools',
            'inspect',
            dependencies.BASE_NAME,
        ):
            return (
                f'Name: docker.io/library/{dependencies.BASE_NAME}\n'
                f'Digest: {self.digest}\n'
            )
        if command[:4] == ('git', 'ls-remote', '--tags', '--refs'):
            url = command[4]
            dependency = next(
                item
                for item in dependencies.DEPENDENCIES
                if isinstance(item.source, dependencies.GitSource)
                and item.source.url == url
            )
            spellings = self.releases.get(
                dependency.name,
                (CURRENT[dependency.name],),
            )
            return git_tags(*spellings)
        raise AssertionError(f'Unexpected command: {command}')

    def fetch(self, url: str) -> bytes:
        """Return a deterministic PECL response."""

        self.urls.append(url)
        if url != dependencies.PECL_RELEASES:
            raise AssertionError(f'Unexpected URL: {url}')
        return pecl_releases(*self.pecl)


class SourceTests(unittest.TestCase):
    """Verify the immutable source contract against Dockerfile clone URLs."""

    def test_defines_all_thirteen_extensions(self) -> None:
        self.assertEqual(13, len(dependencies.DEPENDENCIES))
        self.assertEqual(
            set(CURRENT),
            {
                dependency.name
                for dependency in dependencies.DEPENDENCIES
            },
        )

    def test_uses_exact_dockerfile_sources(self) -> None:
        expected = {
            'brotli': 'https://github.com/kjdev/php-ext-brotli.git',
            'imagick': 'https://github.com/imagick/imagick',
            'lz4': 'https://github.com/kjdev/php-ext-lz4.git',
            'maxminddb': (
                'https://github.com/maxmind/MaxMind-DB-Reader-php.git'
            ),
            'mongodb': (
                'https://github.com/mongodb/mongo-php-driver.git'
            ),
            'redis': 'https://github.com/phpredis/phpredis.git',
            'scrypt': 'https://github.com/DomBlack/php-scrypt.git',
            'snappy': 'https://github.com/kjdev/php-ext-snappy.git',
            'swoole': 'https://github.com/swoole/swoole-src.git',
            'xdebug': 'https://github.com/xdebug/xdebug',
            'yaml': 'https://github.com/php/pecl-file_formats-yaml',
            'zstd': 'https://github.com/kjdev/php-ext-zstd.git',
        }
        actual = {
            dependency.name: dependency.source.url
            for dependency in dependencies.DEPENDENCIES
            if isinstance(dependency.source, dependencies.GitSource)
        }
        self.assertEqual(expected, actual)
        protobuf = next(
            dependency
            for dependency in dependencies.DEPENDENCIES
            if dependency.name == 'protobuf'
        )
        self.assertIsInstance(protobuf.source, dependencies.PeclSource)
        self.assertEqual(dependencies.PECL_RELEASES, protobuf.source.url)

    def test_source_definitions_are_immutable(self) -> None:
        source = dependencies.GitSource('https://example.test/repository')
        with self.assertRaises(FrozenInstanceError):
            source.url = 'https://example.test/changed'

    def test_domain_classes_are_in_matching_files(self) -> None:
        classes = (
            dependencies.Change,
            dependencies.CommandRunner,
            dependencies.Dependency,
            dependencies.Fetcher,
            dependencies.GitSource,
            dependencies.PeclSource,
            dependencies.Pin,
            dependencies.Plan,
            dependencies.UpdateError,
            dependencies.Version,
        )
        for domain_class in classes:
            with self.subTest(domain_class=domain_class.__name__):
                path = inspect.getsourcefile(domain_class)
                self.assertIsNotNone(path)
                self.assertEqual(
                    f'{domain_class.__name__}.py',
                    Path(path).name,
                )

        path = Path(dependencies.__file__)
        module = ast.parse(path.read_text(encoding='utf-8'))
        self.assertEqual(
            [],
            [
                node.name
                for node in module.body
                if isinstance(node, ast.ClassDef)
            ],
        )


class VersionTests(unittest.TestCase):
    """Verify stable semantic same-major selection."""

    def test_parses_only_exact_stable_versions(self) -> None:
        self.assertEqual(
            dependencies.Version(1, 2, 3),
            dependencies.parse_version('1.2.3'),
        )
        self.assertEqual(
            dependencies.Version(1, 2, 3),
            dependencies.parse_version('v1.2.3'),
        )
        for spelling in (
            'V1.2.3',
            '01.2.3',
            '1.02.3',
            '1.2.03',
            'v00.0.0',
            '1.2',
            '1.2.3.4',
            'release-1.2.3',
            '1.2.3RC1',
            'v1.2.3-beta.1',
        ):
            with self.subTest(spelling=spelling):
                self.assertIsNone(dependencies.parse_version(spelling))

    def test_selects_semantic_maximum_not_lexical_maximum(self) -> None:
        self.assertEqual(
            '1.10.0',
            dependencies.select_version(
                '1.2.0',
                ('1.2.9', '1.10.0', '1.9.12'),
            ),
        )

    def test_selects_minor_and_patch_updates(self) -> None:
        self.assertEqual(
            '1.3.0',
            dependencies.select_version(
                '1.2.3',
                ('1.2.4', '1.3.0'),
            ),
        )
        self.assertEqual(
            '1.2.4',
            dependencies.select_version('1.2.3', ('1.2.4',)),
        )

    def test_ignores_higher_major(self) -> None:
        self.assertEqual(
            '1.2.3',
            dependencies.select_version(
                '1.2.3',
                ('2.0.0', 'v3.4.5'),
            ),
        )

    def test_ignores_prereleases(self) -> None:
        self.assertEqual(
            '1.2.4',
            dependencies.select_version(
                '1.2.3',
                ('1.2.4RC1', 'v1.3.0-beta.1', '1.2.4'),
            ),
        )

    def test_never_downgrades(self) -> None:
        self.assertEqual(
            '1.5.0',
            dependencies.select_version(
                '1.5.0',
                ('1.4.9', '1.5.0', '2.0.0'),
            ),
        )

    def test_preserves_selected_upstream_prefix(self) -> None:
        self.assertEqual(
            'v1.3.0',
            dependencies.select_version('1.2.3', ('v1.3.0',)),
        )
        self.assertEqual(
            '1.3.0',
            dependencies.select_version('v1.2.3', ('1.3.0',)),
        )

    def test_prefers_current_prefix_for_equivalent_tags(self) -> None:
        releases = ('1.3.0', 'v1.3.0')
        self.assertEqual(
            'v1.3.0',
            dependencies.select_version('v1.2.3', releases),
        )
        self.assertEqual(
            '1.3.0',
            dependencies.select_version('1.2.3', releases),
        )

    def test_rejects_invalid_current_version(self) -> None:
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Invalid current version',
        ):
            dependencies.select_version('1.2.3RC1', ('1.2.4',))


class ReleaseParsingTests(unittest.TestCase):
    """Verify exact git tags and PECL stable-state handling."""

    def test_parses_only_exact_git_version_tags(self) -> None:
        output = (
            git_tags('1.2.3', 'v1.3.0', '1.4.0RC1', 'release-1.5.0')
            + f'{"b" * 40}\trefs/heads/1.6.0\n'
            + 'malformed\n'
        )
        self.assertEqual(
            ('1.2.3', 'v1.3.0'),
            dependencies.parse_git_tags(output),
        )

    def test_filters_pecl_by_stable_state_and_exact_version(self) -> None:
        document = pecl_releases(
            ('5.35.0RC1', 'beta'),
            ('5.35.0', 'stable'),
            ('5.34.2', 'stable'),
            ('5.36.0', 'beta'),
            ('v5.35.1', 'stable'),
            ('5.35.2RC1', 'stable'),
        )
        self.assertEqual(
            ('5.35.0', '5.34.2', 'v5.35.1'),
            dependencies.parse_pecl_releases(document),
        )

    def test_rejects_invalid_pecl_xml(self) -> None:
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Invalid PECL release XML',
        ):
            dependencies.parse_pecl_releases(b'<not-closed>')

    def test_rejects_source_with_no_stable_releases(self) -> None:
        protobuf = next(
            dependency
            for dependency in dependencies.DEPENDENCIES
            if dependency.name == 'protobuf'
        )
        discovery = Discovery(pecl=(('5.35.0RC1', 'beta'),))
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'No exact stable releases found for protobuf',
        ):
            dependencies.discover_releases(
                protobuf,
                discovery.run,
                discovery.fetch,
            )


class DockerfileTests(unittest.TestCase):
    """Verify declaration validation, digest resolution, and updates."""

    def test_reads_every_expected_declaration_once(self) -> None:
        pins = dependencies.read_pins(dockerfile())
        self.assertEqual(14, len(pins))
        self.assertEqual(dependencies.BASE_NAME, pins[0].name)
        self.assertEqual(OLD_DIGEST, pins[0].current)
        self.assertEqual(set(CURRENT), {pin.name for pin in pins[1:]})

    def test_real_dockerfile_declarations_match_independent_contract(
        self,
    ) -> None:
        path = Path(__file__).resolve().parents[2] / 'Dockerfile'
        content = path.read_text(encoding='utf-8')
        declaration = re.compile(
            r'(?m)^[ \t]*(?:(?:ARG|ENV)[ \t]+)?'
            r'((?:BASE_IMAGE|PHP_[A-Z0-9_]+_VERSION))[ \t]*='
        )

        self.assertEqual(
            EXPECTED_DOCKERFILE_DECLARATIONS,
            {
                match.group(1)
                for match in declaration.finditer(content)
            },
        )
        pins = dependencies.read_pins(content)
        self.assertEqual(
            {dependencies.BASE_NAME, *CURRENT},
            {pin.name for pin in pins},
        )
        self.assertRegex(
            pins[0].current,
            r'\Asha256:[0-9a-f]{64}\Z',
        )

    def test_rejects_unknown_extension_declaration(self) -> None:
        multiline = dockerfile().replace(
            'ENV \\\n',
            'ENV \\\n    PHP_UNKNOWN_VERSION="1.2.3" \\\n',
            1,
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Unknown PHP extension version declaration: '
            'PHP_UNKNOWN_VERSION',
        ):
            dependencies.read_pins(multiline)

        same_line = (
            dockerfile()
            + '\nENV PHP_second_VERSION="1.2.3" '
            + 'PHP_THIRD_VERSION="2.3.4"\n'
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Unknown PHP extension version declarations: '
            'PHP_THIRD_VERSION, PHP_second_VERSION',
        ):
            dependencies.read_pins(same_line)

        argument = dockerfile() + '\nARG PHP_ARGUMENT_VERSION\n'
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Unknown PHP extension version declaration: '
            'PHP_ARGUMENT_VERSION',
        ):
            dependencies.read_pins(argument)

    def test_rejects_missing_declaration(self) -> None:
        content = dockerfile().replace(
            f'    PHP_REDIS_VERSION="{CURRENT["redis"]}" \\\n',
            '',
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Expected exactly one PHP_REDIS_VERSION declaration, found 0',
        ):
            dependencies.read_pins(content)

    def test_rejects_duplicate_declaration(self) -> None:
        content = (
            dockerfile()
            + f'\nENV PHP_REDIS_VERSION="{CURRENT["redis"]}"\n'
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Expected exactly one PHP_REDIS_VERSION declaration, found 2',
        ):
            dependencies.read_pins(content)

    def test_rejects_missing_and_duplicate_base_declarations(self) -> None:
        missing = dockerfile().replace(
            f'ARG BASE_IMAGE="{dependencies.BASE_NAME}@{OLD_DIGEST}"\n',
            '',
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Expected exactly one ARG BASE_IMAGE declaration, found 0',
        ):
            dependencies.read_pins(missing)

        duplicate = (
            dockerfile()
            + f'ARG BASE_IMAGE="{dependencies.BASE_NAME}@{OLD_DIGEST}"\n'
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Expected exactly one ARG BASE_IMAGE declaration, found 2',
        ):
            dependencies.read_pins(duplicate)

    def test_rejects_invalid_pin_spelling(self) -> None:
        content = dockerfile().replace(
            f'PHP_YAML_VERSION="{CURRENT["yaml"]}"',
            'PHP_YAML_VERSION="2.4.0RC1"',
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'PHP_YAML_VERSION must be an exact stable',
        ):
            dependencies.read_pins(content)

    def test_rejects_invalid_base_digest(self) -> None:
        content = dockerfile().replace(OLD_DIGEST, 'sha256:ABC')
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'must pin php:8.5-alpine to a lowercase sha256 digest',
        ):
            dependencies.read_pins(content)

    def test_resolves_lowercase_multiarch_digest_through_buildx(self) -> None:
        discovery = Discovery(digest=NEW_DIGEST)
        self.assertEqual(
            NEW_DIGEST,
            dependencies.resolve_digest(discovery.run),
        )
        self.assertEqual(
            [
                (
                    'docker',
                    'buildx',
                    'imagetools',
                    'inspect',
                    dependencies.BASE_NAME,
                )
            ],
            discovery.commands,
        )

    def test_rejects_invalid_or_ambiguous_digest_output(self) -> None:
        invalid = lambda command: 'Digest: sha256:' + ('A' * 64) + '\n'
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Expected one lowercase sha256 digest',
        ):
            dependencies.resolve_digest(invalid)

        ambiguous = lambda command: (
            f'Digest: {OLD_DIGEST}\nDigest: {NEW_DIGEST}\n'
        )
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'Expected one lowercase sha256 digest',
        ):
            dependencies.resolve_digest(ambiguous)

    def test_plans_updates_without_touching_references(self) -> None:
        discovery = Discovery(
            digest=NEW_DIGEST,
            releases={
                'redis': (CURRENT['redis'], '6.3.1', 'v6.3.1', '7.0.0'),
                'swoole': (CURRENT['swoole'], '6.2.1', 'v6.2.1'),
            },
            pecl=(
                (CURRENT['protobuf'], 'stable'),
                ('5.34.1', 'stable'),
                ('5.35.0RC1', 'beta'),
                ('6.0.0', 'stable'),
            ),
        )
        plan = dependencies.plan_update(
            dockerfile(),
            discovery.run,
            discovery.fetch,
        )
        self.assertTrue(plan.changed)
        self.assertIn(
            f'ARG BASE_IMAGE="{dependencies.BASE_NAME}@{NEW_DIGEST}"',
            plan.content,
        )
        self.assertIn('PHP_REDIS_VERSION="6.3.1"', plan.content)
        self.assertIn('PHP_PROTOBUF_VERSION="5.34.1"', plan.content)
        self.assertIn('PHP_SWOOLE_VERSION="v6.2.1"', plan.content)
        self.assertIn('RUN echo "$PHP_REDIS_VERSION"', plan.content)

    def test_noop_plan_preserves_content_exactly(self) -> None:
        content = dockerfile()
        discovery = Discovery()
        plan = dependencies.plan_update(
            content,
            discovery.run,
            discovery.fetch,
        )
        self.assertFalse(plan.changed)
        self.assertEqual(content, plan.content)

    def test_injects_every_external_interaction(self) -> None:
        discovery = Discovery()
        dependencies.plan_update(
            dockerfile(),
            discovery.run,
            discovery.fetch,
        )
        git_sources = sum(
            isinstance(dependency.source, dependencies.GitSource)
            for dependency in dependencies.DEPENDENCIES
        )
        self.assertEqual(1 + git_sources, len(discovery.commands))
        self.assertEqual([dependencies.PECL_RELEASES], discovery.urls)
        for dependency in dependencies.DEPENDENCIES:
            if not isinstance(dependency.source, dependencies.GitSource):
                continue
            self.assertIn(
                (
                    'git',
                    'ls-remote',
                    '--tags',
                    '--refs',
                    dependency.source.url,
                ),
                discovery.commands,
            )

    def test_dry_run_does_not_mutate_dockerfile_or_siblings(self) -> None:
        discovery = Discovery(
            digest=NEW_DIGEST,
            releases={'redis': (CURRENT['redis'], '6.3.1')},
        )
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            path = root / 'Dockerfile'
            sibling = root / 'keep.txt'
            original = dockerfile()
            path.write_text(original, encoding='utf-8')
            sibling.write_text('unchanged', encoding='utf-8')

            plan = dependencies.update(
                path,
                dry_run=True,
                runner=discovery.run,
                fetcher=discovery.fetch,
            )

            self.assertTrue(plan.changed)
            self.assertEqual(original, path.read_text(encoding='utf-8'))
            self.assertEqual(
                'unchanged',
                sibling.read_text(encoding='utf-8'),
            )

    def test_update_mutates_only_dockerfile(self) -> None:
        discovery = Discovery(digest=NEW_DIGEST)
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            path = root / 'Dockerfile'
            sibling = root / 'keep.txt'
            path.write_text(dockerfile(), encoding='utf-8')
            sibling.write_text('unchanged', encoding='utf-8')

            plan = dependencies.update(
                path,
                runner=discovery.run,
                fetcher=discovery.fetch,
            )

            self.assertEqual(plan.content, path.read_text(encoding='utf-8'))
            self.assertEqual(
                'unchanged',
                sibling.read_text(encoding='utf-8'),
            )

    def test_rejects_non_dockerfile_target(self) -> None:
        with self.assertRaisesRegex(
            dependencies.UpdateError,
            'target must be named Dockerfile',
        ):
            dependencies.update(Path('/tmp/not-a-dockerfile'))


class ReportTests(unittest.TestCase):
    """Verify Markdown output for updates and no-op runs."""

    def test_renders_markdown_update_report(self) -> None:
        discovery = Discovery(
            digest=NEW_DIGEST,
            releases={'redis': (CURRENT['redis'], '6.3.1')},
        )
        plan = dependencies.plan_update(
            dockerfile(),
            discovery.run,
            discovery.fetch,
        )
        report = dependencies.render_report(plan)
        self.assertTrue(report.startswith('## Dependency update report\n'))
        self.assertIn(
            '| Dependency | Current | Selected | Result |',
            report,
        )
        self.assertIn(
            f'| {dependencies.BASE_NAME} | `{OLD_DIGEST}` '
            f'| `{NEW_DIGEST}` | Updated |',
            report,
        )
        self.assertIn(
            f'| redis | `{CURRENT["redis"]}` | `6.3.1` | Updated |',
            report,
        )
        self.assertIn('**Updates:** 2', report)
        self.assertTrue(report.endswith('Dockerfile pins were updated.'))

    def test_renders_explicit_noop_report(self) -> None:
        discovery = Discovery()
        plan = dependencies.plan_update(
            dockerfile(),
            discovery.run,
            discovery.fetch,
        )
        report = dependencies.render_report(plan)
        self.assertIn('**Updates:** 0', report)
        self.assertIn('No dependency updates were found.', report)
        self.assertNotIn('| Updated |', report)


if __name__ == '__main__':
    unittest.main()
