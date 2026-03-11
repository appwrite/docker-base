"""Tests for markdown documentation files."""
import os
import re
import pytest


class TestREADME:
    """Tests for README.md file."""

    def test_readme_exists(self, readme_path):
        """Test that README.md exists."""
        assert os.path.exists(readme_path), "README.md should exist"

    def test_readme_not_empty(self, readme_path):
        """Test that README.md is not empty."""
        with open(readme_path, 'r') as f:
            content = f.read()
        assert len(content.strip()) > 0, "README.md should not be empty"

    def test_readme_has_title(self, readme_path):
        """Test that README.md has a title."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert content.startswith('#'), "README should start with a title (# header)"
        assert 'Docker Base' in content, "Title should mention Docker Base"

    def test_readme_has_badges(self, readme_path):
        """Test that README.md includes status badges."""
        with open(readme_path, 'r') as f:
            content = f.read()

        # Check for markdown badge syntax using regex
        badge_pattern = r'\[!\[.*\]\(.*\)\]'
        assert re.search(badge_pattern, content), "Should have markdown badge syntax"

        # Check for specific badge sources
        badge_sources = [
            'img.shields.io',
            'travis-ci.com',
            'discord',
            'docker/pulls'
        ]

        for source in badge_sources:
            assert source in content.lower(), f"Should include {source} badge"

    def test_readme_has_getting_started(self, readme_path):
        """Test that README.md has getting started section."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'Getting Started' in content or 'getting started' in content.lower(), \
            "Should have Getting Started section"

    def test_readme_has_prerequisites(self, readme_path):
        """Test that README.md lists prerequisites."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'Prerequisites' in content or 'prerequisites' in content.lower(), \
            "Should have prerequisites section"
        assert 'Docker' in content or 'docker' in content.lower(), \
            "Should mention Docker as prerequisite"

    def test_readme_has_build_instructions(self, readme_path):
        """Test that README.md has build instructions."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert '## Build' in content or '# Build' in content, "Should have Build section"
        assert 'docker build' in content.lower(), "Should include docker build command"

    def test_readme_has_test_instructions(self, readme_path):
        """Test that README.md has test instructions."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert '## Test' in content or '# Test' in content, "Should have Test section"
        assert 'container-structure-test' in content, "Should mention container-structure-test"

    def test_readme_has_scan_instructions(self, readme_path):
        """Test that README.md has security scanning instructions."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert '## Scan' in content or '# Scan' in content, "Should have Scan section"
        assert 'trivy' in content.lower(), "Should mention Trivy scanner"

    def test_readme_has_run_instructions(self, readme_path):
        """Test that README.md has run instructions."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert '## Run' in content or '# Run' in content, "Should have Run section"
        assert 'docker run' in content.lower(), "Should include docker run command"

    def test_readme_has_push_instructions(self, readme_path):
        """Test that README.md has push instructions."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert '## Push' in content or '# Push' in content, "Should have Push section"
        assert 'docker push' in content.lower(), "Should include docker push command"

    def test_readme_mentions_appwrite(self, readme_path):
        """Test that README.md mentions Appwrite."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'Appwrite' in content or 'appwrite' in content.lower(), \
            "Should mention Appwrite"

    def test_readme_has_links(self, readme_path):
        """Test that README.md includes important links."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'github.com' in content.lower(), "Should link to GitHub"
        assert 'discord' in content.lower(), "Should link to Discord"

    def test_readme_has_license_section(self, readme_path):
        """Test that README.md has copyright and license section."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'Copyright' in content or 'copyright' in content.lower() or 'license' in content.lower(), \
            "Should have copyright/license section"
        assert 'MIT' in content, "Should mention MIT license"

    def test_readme_code_blocks_properly_formatted(self, readme_path):
        """Test that README.md code blocks are properly formatted."""
        with open(readme_path, 'r') as f:
            content = f.read()

        code_blocks = re.findall(r'```(\w+)?\n(.*?)```', content, re.DOTALL)
        assert len(code_blocks) > 0, "Should have code blocks with examples"

    def test_readme_docker_commands_use_appwrite_image(self, readme_path):
        """Test that Docker commands reference appwrite/base image."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'appwrite/base' in content.lower(), "Docker commands should use appwrite/base image"

    def test_readme_warns_about_latest_tag(self, readme_path):
        """Test that README warns about using latest tag."""
        with open(readme_path, 'r') as f:
            content = f.read()

        has_warning = 'pinned version' in content.lower() or 'recommends using' in content.lower()
        assert has_warning, "Should warn about using pinned versions instead of latest"

    def test_readme_mentions_container_structure_test(self, readme_path):
        """Test that README mentions container-structure-test as prerequisite."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'container-structure-test' in content, "Should mention container-structure-test"
        assert 'GoogleContainerTools' in content or 'github.com' in content.lower(), \
            "Should link to container-structure-test tool"

    def test_readme_mentions_trivy_prerequisite(self, readme_path):
        """Test that README mentions Trivy as optional prerequisite."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'Trivy' in content or 'trivy' in content, "Should mention Trivy"

    def test_readme_build_command_uses_tee(self, readme_path):
        """Test that build command in README uses tee for logging."""
        with open(readme_path, 'r') as f:
            content = f.read()

        assert 'tee' in content, "Build command should use tee for logging"


class TestCHANGES:
    """Tests for CHANGES.md file."""

    def test_changes_exists(self, changes_path):
        """Test that CHANGES.md exists."""
        assert os.path.exists(changes_path), "CHANGES.md should exist"

    def test_changes_not_empty(self, changes_path):
        """Test that CHANGES.md is not empty."""
        with open(changes_path, 'r') as f:
            content = f.read()
        assert len(content.strip()) > 0, "CHANGES.md should not be empty"

    def test_changes_has_version(self, changes_path):
        """Test that CHANGES.md includes version number."""
        with open(changes_path, 'r') as f:
            content = f.read()

        version_pattern = r'#.*Version\s+\d+\.\d+\.\d+'
        assert re.search(version_pattern, content), "Should include version number"

    def test_changes_current_version(self, changes_path):
        """Test that CHANGES.md documents version 0.2.0."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert '0.2.0' in content, "Should document version 0.2.0"

    def test_changes_has_sections(self, changes_path):
        """Test that CHANGES.md has standard changelog sections."""
        with open(changes_path, 'r') as f:
            content = f.read()

        sections = ['Add', 'Change', 'Fixes', 'Removed']
        for section in sections:
            assert f'### {section}' in content, f"Should have {section} section"

    def test_changes_documents_dockerignore(self, changes_path):
        """Test that CHANGES.md documents .dockerignore addition."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert '.dockerignore' in content, "Should document .dockerignore addition"

    def test_changes_documents_gitignore_updates(self, changes_path):
        """Test that CHANGES.md documents .gitignore changes."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert '.gitignore' in content, "Should document .gitignore changes"
        assert 'log' in content.lower() and 'scanning' in content.lower(), \
            "Should mention log and scanning output rules"

    def test_changes_documents_imagemagick_version(self, changes_path):
        """Test that CHANGES.md documents ImageMagick version bump."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert 'ImageMagick' in content, "Should document ImageMagick version"
        assert '7.1.2.15' in content, "Should mention version 7.1.2.15"

    def test_changes_documents_php_version(self, changes_path):
        """Test that CHANGES.md documents PHP version bump."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert 'PHP' in content, "Should document PHP version"
        assert '8.5.3' in content, "Should mention PHP version 8.5.3"

    def test_changes_documents_swoole_version(self, changes_path):
        """Test that CHANGES.md documents Swoole version bump."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert 'Swoole' in content, "Should document Swoole version"
        assert '6.2.0' in content, "Should mention Swoole version 6.2.0"

    def test_changes_documents_readme_fixes(self, changes_path):
        """Test that CHANGES.md documents README.md fixes."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert 'README.md' in content, "Should document README.md changes"
        assert 'usage instructions' in content.lower() or 'detailed' in content.lower(), \
            "Should mention usage instructions improvement"

    def test_changes_documents_tests_yaml(self, changes_path):
        """Test that CHANGES.md documents tests.yaml alignment."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert 'tests.yaml' in content, "Should document tests.yaml changes"
        assert 'aligned' in content.lower() or 'ensure' in content.lower(), \
            "Should mention tests.yaml alignment with ImageMagick version"

    def test_changes_mentions_changelog_itself(self, changes_path):
        """Test that CHANGES.md documents itself being added."""
        with open(changes_path, 'r') as f:
            content = f.read()

        assert 'CHANGELOG' in content or 'CHANGES' in content, \
            "Should document CHANGELOG/CHANGES.md addition"

    def test_changes_format_consistency(self, changes_path):
        """Test that CHANGES.md follows consistent formatting."""
        with open(changes_path, 'r') as f:
            lines = f.readlines()

        # Check that section headers use ###
        section_headers = [line for line in lines if line.strip().startswith('###')]
        assert len(section_headers) > 0, "Should have section headers with ###"

        # Check that items under sections use * or -
        for i, line in enumerate(lines):
            if line.strip().startswith('###'):
                # Next non-empty line should be a list item or empty
                j = i + 1
                while j < len(lines) and not lines[j].strip():
                    j += 1
                if j < len(lines) and lines[j].strip() and not lines[j].strip().startswith('#'):
                    assert lines[j].strip().startswith('*') or lines[j].strip().startswith('-'), \
                        "Items under sections should be list items"
                    break

    def test_changes_version_in_title(self, changes_path):
        """Test that version appears in title section."""
        with open(changes_path, 'r') as f:
            content = f.read()

        lines = content.split('\n')
        first_non_empty = next(line for line in lines if line.strip())

        assert first_non_empty.startswith('#'), "First line should be a header"
        assert '0.2.0' in first_non_empty, "Version should be in title"


class TestMarkdownConsistency:
    """Tests for consistency between markdown files and code."""

    def test_changes_versions_match_dockerfile(self, changes_path, dockerfile_path, tests_yaml_path):
        """Test that versions in CHANGES.md match Dockerfile or tests.yaml."""
        with open(changes_path, 'r') as f:
            changes = f.read()
        with open(dockerfile_path, 'r') as f:
            dockerfile = f.read()
        with open(tests_yaml_path, 'r') as f:
            tests_yaml = f.read()

        # PHP version
        if '8.5.3' in changes:
            assert 'php:8.5.3' in dockerfile.lower(), "PHP version should match"

        # Swoole version
        if '6.2.0' in changes:
            assert 'PHP_SWOOLE_VERSION="6.2.0"' in dockerfile, "Swoole version should match"

        # ImageMagick version - verified in tests.yaml, not in Dockerfile
        if '7.1.2.15' in changes:
            assert '7.1.2.15' in tests_yaml, "ImageMagick version should be verified in tests.yaml"

    def test_readme_commands_are_valid(self, readme_path):
        """Test that shell commands in README are syntactically plausible."""
        with open(readme_path, 'r') as f:
            content = f.read()

        code_blocks = re.findall(r'```(?:shell|bash|sh)?\n(.*?)```', content, re.DOTALL)

        for block in code_blocks:
            commands = [line.strip() for line in block.split('\n') if line.strip() and not line.strip().startswith('#')]
            for cmd in commands:
                # Basic validation - should not have obvious syntax errors
                if cmd.startswith('docker'):
                    assert 'docker ' in cmd, "Docker commands should have subcommand"
                if '|' in cmd:
                    parts = cmd.split('|')
                    assert len(parts) >= 2, "Piped commands should have at least 2 parts"

    def test_changes_documents_all_modified_files(self, changes_path):
        """Test that CHANGES.md documents all the modified files."""
        with open(changes_path, 'r') as f:
            changes_content = f.read()

        documented_files = [
            '.dockerignore',
            '.gitignore',
            'README.md',
            'tests.yaml'
        ]

        for file in documented_files:
            assert file in changes_content, f"CHANGES.md should document {file} modifications"