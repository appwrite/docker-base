#!/usr/bin/env python3
"""Update the Dockerfile's pinned base image and PHP extension releases."""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
import urllib.request
import xml.etree.ElementTree as ElementTree
from collections.abc import Iterable, Sequence
from pathlib import Path

from dependency.Change import Change
from dependency.CommandRunner import CommandRunner
from dependency.Dependency import Dependency
from dependency.Fetcher import Fetcher
from dependency.GitSource import GitSource
from dependency.PeclSource import PeclSource
from dependency.Pin import Pin
from dependency.Plan import Plan
from dependency.Source import Source
from dependency.UpdateError import UpdateError
from dependency.Version import Version


BASE_NAME = 'php:8.5-alpine'
BASE_PATTERN = re.compile(
    rf'{re.escape(BASE_NAME)}@(sha256:[0-9a-f]{{64}})'
)
DECLARATION_LINE_PATTERN = re.compile(
    r'(?m)^[ \t]*(?:(?:ARG|ENV)[ \t]+[^\r\n]*|'
    r'PHP_[A-Za-z0-9_]+_VERSION[^\r\n]*)$'
)
DECLARATION_PATTERN = re.compile(
    r'(?<![$A-Za-z0-9_])'
    r'(PHP_[A-Za-z0-9_]+_VERSION)'
    r'(?=[ \t]*(?:=|$))'
)
DIGEST_PATTERN = re.compile(r'sha256:[0-9a-f]{64}')
VERSION_COMPONENT = r'(0|[1-9][0-9]*)'
VERSION_PATTERN = re.compile(
    rf'v?{VERSION_COMPONENT}\.{VERSION_COMPONENT}\.{VERSION_COMPONENT}'
)
PECL_RELEASES = 'https://pecl.php.net/rest/r/protobuf/allreleases.xml'

DEPENDENCIES: tuple[Dependency, ...] = (
    Dependency(
        'brotli',
        'PHP_BROTLI_VERSION',
        GitSource('https://github.com/kjdev/php-ext-brotli.git'),
    ),
    Dependency(
        'imagick',
        'PHP_IMAGICK_VERSION',
        GitSource('https://github.com/imagick/imagick'),
    ),
    Dependency(
        'lz4',
        'PHP_LZ4_VERSION',
        GitSource('https://github.com/kjdev/php-ext-lz4.git'),
    ),
    Dependency(
        'maxminddb',
        'PHP_MAXMINDDB_VERSION',
        GitSource(
            'https://github.com/maxmind/MaxMind-DB-Reader-php.git'
        ),
    ),
    Dependency(
        'mongodb',
        'PHP_MONGODB_VERSION',
        GitSource('https://github.com/mongodb/mongo-php-driver.git'),
    ),
    Dependency(
        'protobuf',
        'PHP_PROTOBUF_VERSION',
        PeclSource(PECL_RELEASES),
    ),
    Dependency(
        'redis',
        'PHP_REDIS_VERSION',
        GitSource('https://github.com/phpredis/phpredis.git'),
    ),
    Dependency(
        'scrypt',
        'PHP_SCRYPT_VERSION',
        GitSource('https://github.com/DomBlack/php-scrypt.git'),
    ),
    Dependency(
        'snappy',
        'PHP_SNAPPY_VERSION',
        GitSource('https://github.com/kjdev/php-ext-snappy.git'),
    ),
    Dependency(
        'swoole',
        'PHP_SWOOLE_VERSION',
        GitSource('https://github.com/swoole/swoole-src.git'),
    ),
    Dependency(
        'xdebug',
        'PHP_XDEBUG_VERSION',
        GitSource('https://github.com/xdebug/xdebug'),
    ),
    Dependency(
        'yaml',
        'PHP_YAML_VERSION',
        GitSource('https://github.com/php/pecl-file_formats-yaml'),
    ),
    Dependency(
        'zstd',
        'PHP_ZSTD_VERSION',
        GitSource('https://github.com/kjdev/php-ext-zstd.git'),
    ),
)


def parse_version(spelling: str) -> Version | None:
    """Parse only an exact stable v?MAJOR.MINOR.PATCH spelling."""

    match = VERSION_PATTERN.fullmatch(spelling)
    if match is None:
        return None
    return Version(*(int(part) for part in match.groups()))


def select_version(current: str, releases: Iterable[str]) -> str:
    """Select the newest non-downgrading release in the current major."""

    current_version = parse_version(current)
    if current_version is None:
        raise UpdateError(f'Invalid current version: {current}')

    parsed = tuple(
        (version, release)
        for release in releases
        if (version := parse_version(release)) is not None
        and version.major == current_version.major
        and version > current_version
    )
    if not parsed:
        return current

    latest_version = max(version for version, _ in parsed)
    spellings = tuple(
        release
        for version, release in parsed
        if version == latest_version
    )
    current_has_prefix = current.startswith('v')
    matching = tuple(
        spelling
        for spelling in spellings
        if spelling.startswith('v') == current_has_prefix
    )
    return min(matching or spellings)


def parse_git_tags(output: str) -> tuple[str, ...]:
    """Extract exact tag names from git ls-remote output."""

    prefix = 'refs/tags/'
    tags: list[str] = []
    for line in output.splitlines():
        fields = line.split()
        if len(fields) != 2 or not fields[1].startswith(prefix):
            continue
        tag = fields[1][len(prefix) :]
        if parse_version(tag) is not None:
            tags.append(tag)
    return tuple(tags)


def parse_pecl_releases(document: bytes) -> tuple[str, ...]:
    """Extract exact stable releases from a PECL allreleases document."""

    try:
        root = ElementTree.fromstring(document)
    except ElementTree.ParseError as error:
        raise UpdateError(f'Invalid PECL release XML: {error}') from error

    releases: list[str] = []
    for release in root:
        if _local_name(release.tag) != 'r':
            continue
        fields = {
            _local_name(child.tag): (child.text or '').strip()
            for child in release
        }
        spelling = fields.get('v', '')
        if (
            fields.get('s', '').lower() == 'stable'
            and parse_version(spelling) is not None
        ):
            releases.append(spelling)
    return tuple(releases)


def _local_name(name: str) -> str:
    return name.rsplit('}', 1)[-1]


def run_command(command: tuple[str, ...]) -> str:
    """Run one discovery command and return stdout."""

    try:
        completed = subprocess.run(
            command,
            check=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
        )
    except (OSError, subprocess.CalledProcessError) as error:
        detail = getattr(error, 'stderr', '') or str(error)
        raise UpdateError(
            f'Command failed: {" ".join(command)}: {detail.strip()}'
        ) from error
    return completed.stdout


def fetch(url: str) -> bytes:
    """Fetch one authoritative release feed."""

    try:
        with urllib.request.urlopen(url, timeout=30) as response:
            return response.read()
    except OSError as error:
        raise UpdateError(f'Failed to fetch {url}: {error}') from error


def resolve_digest(runner: CommandRunner = run_command) -> str:
    """Resolve and validate the base image's multi-architecture digest."""

    output = runner(
        ('docker', 'buildx', 'imagetools', 'inspect', BASE_NAME)
    )
    matches = tuple(
        match.group(1)
        for match in re.finditer(
            r'(?m)^Digest:[ \t]*(sha256:[0-9a-f]{64})[ \t]*$',
            output,
        )
    )
    if len(matches) != 1 or DIGEST_PATTERN.fullmatch(matches[0]) is None:
        raise UpdateError(
            f'Expected one lowercase sha256 digest for {BASE_NAME}'
        )
    return matches[0]


def discover_releases(
    dependency: Dependency,
    runner: CommandRunner = run_command,
    fetcher: Fetcher = fetch,
) -> tuple[str, ...]:
    """Read stable release spellings from the configured typed source."""

    if isinstance(dependency.source, GitSource):
        output = runner(
            (
                'git',
                'ls-remote',
                '--tags',
                '--refs',
                dependency.source.url,
            )
        )
        releases = parse_git_tags(output)
    else:
        releases = parse_pecl_releases(fetcher(dependency.source.url))

    if not releases:
        raise UpdateError(
            f'No exact stable releases found for {dependency.name}'
        )
    return releases


def read_pins(content: str) -> tuple[Pin, ...]:
    """Validate and locate every expected Dockerfile declaration once."""

    expected = {dependency.variable for dependency in DEPENDENCIES}
    declared = {
        match.group(1)
        for line in DECLARATION_LINE_PATTERN.finditer(content)
        for match in DECLARATION_PATTERN.finditer(line.group(0))
    }
    unknown = tuple(sorted(declared - expected))
    if unknown:
        raise UpdateError(
            'Unknown PHP extension version '
            f'declaration{"s" if len(unknown) != 1 else ""}: '
            f'{", ".join(unknown)}'
        )

    image_expression = re.compile(
        r'(?m)^[ \t]*ARG[ \t]+BASE_IMAGE="([^"\r\n]+)"[ \t]*$'
    )
    image_match = _single_match(
        image_expression,
        content,
        'ARG BASE_IMAGE',
    )
    image_value = image_match.group(1)
    value_match = BASE_PATTERN.fullmatch(image_value)
    if value_match is None:
        raise UpdateError(
            f'ARG BASE_IMAGE must pin {BASE_NAME} to a lowercase sha256 digest'
        )
    digest_start = image_match.start(1) + value_match.start(1)
    pins: list[Pin] = [
        Pin(
            BASE_NAME,
            value_match.group(1),
            digest_start,
            digest_start + len(value_match.group(1)),
        )
    ]

    for dependency in DEPENDENCIES:
        expression = re.compile(
            rf'(?m)^[ \t]*(?:ENV[ \t]+)?'
            rf'{re.escape(dependency.variable)}='
            r'"([^"\r\n]+)"[ \t]*(?:\\)?[ \t]*$'
        )
        match = _single_match(
            expression,
            content,
            dependency.variable,
        )
        current = match.group(1)
        if parse_version(current) is None:
            raise UpdateError(
                f'{dependency.variable} must be an exact stable '
                'v?MAJOR.MINOR.PATCH version'
            )
        pins.append(
            Pin(
                dependency.name,
                current,
                match.start(1),
                match.end(1),
            )
        )

    return tuple(pins)


def _single_match(
    expression: re.Pattern[str],
    content: str,
    declaration: str,
) -> re.Match[str]:
    matches = tuple(expression.finditer(content))
    if len(matches) != 1:
        raise UpdateError(
            f'Expected exactly one {declaration} declaration, '
            f'found {len(matches)}'
        )
    return matches[0]


def plan_update(
    content: str,
    runner: CommandRunner = run_command,
    fetcher: Fetcher = fetch,
) -> Plan:
    """Resolve all releases before constructing an in-memory update."""

    pins = read_pins(content)
    latest = [resolve_digest(runner)]
    for dependency, pin in zip(DEPENDENCIES, pins[1:], strict=True):
        releases = discover_releases(dependency, runner, fetcher)
        latest.append(select_version(pin.current, releases))

    changes = tuple(
        Change(pin.name, pin.current, selected)
        for pin, selected in zip(pins, latest, strict=True)
    )
    updated = content
    for pin, selected in reversed(
        tuple(zip(pins, latest, strict=True))
    ):
        updated = updated[: pin.start] + selected + updated[pin.end :]
    return Plan(updated, changes)


def render_report(plan: Plan) -> str:
    """Render the complete update result as Markdown."""

    changed = sum(change.changed for change in plan.changes)
    rows = [
        '## Dependency update report',
        '',
        '| Dependency | Current | Selected | Result |',
        '| --- | --- | --- | --- |',
    ]
    rows.extend(
        '| {name} | `{current}` | `{latest}` | {result} |'.format(
            name=change.name,
            current=change.current,
            latest=change.latest,
            result='Updated' if change.changed else 'Current',
        )
        for change in plan.changes
    )
    rows.extend(
        (
            '',
            f'**Updates:** {changed}',
            '',
            (
                'Dockerfile pins were updated.'
                if changed
                else 'No dependency updates were found.'
            ),
        )
    )
    return '\n'.join(rows)


def update(
    dockerfile: Path,
    *,
    dry_run: bool = False,
    runner: CommandRunner = run_command,
    fetcher: Fetcher = fetch,
) -> Plan:
    """Plan updates and optionally mutate only the requested Dockerfile."""

    if dockerfile.name != 'Dockerfile':
        raise UpdateError('The update target must be named Dockerfile')
    content = dockerfile.read_text(encoding='utf-8')
    plan = plan_update(content, runner, fetcher)
    if plan.changed and not dry_run:
        dockerfile.write_text(plan.content, encoding='utf-8')
    return plan


def main(arguments: Sequence[str] | None = None) -> int:
    """Run the dependency updater."""

    root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        '--dockerfile',
        type=Path,
        default=root / 'Dockerfile',
        help='Dockerfile to inspect and update',
    )
    parser.add_argument(
        '--dry-run',
        action='store_true',
        help='report updates without modifying the Dockerfile',
    )
    options = parser.parse_args(arguments)

    try:
        plan = update(
            options.dockerfile,
            dry_run=options.dry_run,
        )
    except (OSError, UpdateError) as error:
        print(f'Error: {error}', file=sys.stderr)
        return 1
    print(render_report(plan))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
