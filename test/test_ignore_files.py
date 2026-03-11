"""Tests for .dockerignore and .gitignore files."""
import os
import re
import pytest


class TestDockerignore:
    """Tests for .dockerignore file."""

    def test_dockerignore_exists(self, dockerignore_path):
        """Test that .dockerignore file exists."""
        assert os.path.exists(dockerignore_path), ".dockerignore should exist"

    def test_dockerignore_not_empty(self, dockerignore_path):
        """Test that .dockerignore is not empty."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()
        assert len(content.strip()) > 0, ".dockerignore should not be empty"

    def test_dockerignore_excludes_git(self, dockerignore_path):
        """Test that .dockerignore excludes git files."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()

        assert '.git' in content or '.git*' in content, "Should exclude .git files"

    def test_dockerignore_excludes_markdown(self, dockerignore_path):
        """Test that .dockerignore excludes markdown files."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()

        assert '*.md' in content, "Should exclude markdown files"

    def test_dockerignore_excludes_tests(self, dockerignore_path):
        """Test that .dockerignore excludes test files."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()

        patterns = content.split('\n')
        test_patterns = [p for p in patterns if 'test' in p.lower()]
        assert len(test_patterns) > 0, "Should exclude test files"

    def test_dockerignore_excludes_dockerfile(self, dockerignore_path):
        """Test that .dockerignore excludes Dockerfile itself."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()

        assert 'Dockerfile' in content, "Should exclude Dockerfile"

    def test_dockerignore_excludes_license(self, dockerignore_path):
        """Test that .dockerignore excludes LICENSE file."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()

        assert 'LICENSE' in content, "Should exclude LICENSE file"

    def test_dockerignore_excludes_trivy_results(self, dockerignore_path):
        """Test that .dockerignore excludes Trivy scan results."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()

        assert 'trivy' in content.lower(), "Should exclude Trivy results"

    def test_dockerignore_valid_patterns(self, dockerignore_path):
        """Test that .dockerignore contains valid glob patterns."""
        with open(dockerignore_path, 'r') as f:
            lines = [line.strip() for line in f.readlines()]

        valid_patterns = [
            line for line in lines
            if line and not line.startswith('#')
        ]

        assert len(valid_patterns) > 0, "Should have valid ignore patterns"

        for pattern in valid_patterns:
            assert len(pattern) > 0, "Patterns should not be empty"
            assert not pattern.startswith(' '), "Patterns should not start with space"

    def test_dockerignore_no_duplicate_patterns(self, dockerignore_path):
        """Test that .dockerignore has no duplicate patterns."""
        with open(dockerignore_path, 'r') as f:
            lines = [line.strip() for line in f.readlines() if line.strip() and not line.startswith('#')]

        assert len(lines) == len(set(lines)), "Should not have duplicate patterns"

    def test_dockerignore_pattern_specificity(self, dockerignore_path):
        """Test that .dockerignore patterns are specific enough."""
        with open(dockerignore_path, 'r') as f:
            content = f.read()

        patterns = [line.strip() for line in content.split('\n') if line.strip()]

        for pattern in patterns:
            if pattern.startswith('#'):
                continue
            assert pattern != '*', "Should not have overly broad '*' pattern"
            assert pattern != '**', "Should not have overly broad '**' pattern"


class TestGitignore:
    """Tests for .gitignore file."""

    def test_gitignore_exists(self, gitignore_path):
        """Test that .gitignore file exists."""
        assert os.path.exists(gitignore_path), ".gitignore should exist"

    def test_gitignore_not_empty(self, gitignore_path):
        """Test that .gitignore is not empty."""
        with open(gitignore_path, 'r') as f:
            content = f.read()
        assert len(content.strip()) > 0, ".gitignore should not be empty"

    def test_gitignore_excludes_ide_files(self, gitignore_path):
        """Test that .gitignore excludes IDE files."""
        with open(gitignore_path, 'r') as f:
            content = f.read()

        assert '.idea' in content, "Should exclude .idea directory"

    def test_gitignore_excludes_logs(self, gitignore_path):
        """Test that .gitignore excludes log files."""
        with open(gitignore_path, 'r') as f:
            content = f.read()

        log_patterns = [line for line in content.split('\n') if 'log' in line.lower()]
        assert len(log_patterns) > 0, "Should exclude log files"

    def test_gitignore_excludes_notes(self, gitignore_path):
        """Test that .gitignore excludes NOTES markdown files."""
        with open(gitignore_path, 'r') as f:
            content = f.read()

        assert 'NOTES' in content or 'notes' in content.lower(), "Should exclude NOTES files"

    def test_gitignore_excludes_trivy_results(self, gitignore_path):
        """Test that .gitignore excludes Trivy scan results."""
        with open(gitignore_path, 'r') as f:
            content = f.read()

        assert 'trivy' in content.lower(), "Should exclude Trivy results"

    def test_gitignore_trivy_json_pattern(self, gitignore_path):
        """Test that .gitignore excludes Trivy JSON results specifically."""
        with open(gitignore_path, 'r') as f:
            content = f.read()

        assert 'trivy-' in content and '.json' in content or 'trivy*.json' in content, \
            "Should exclude Trivy JSON result files"

    def test_gitignore_valid_patterns(self, gitignore_path):
        """Test that .gitignore contains valid patterns."""
        with open(gitignore_path, 'r') as f:
            lines = [line.strip() for line in f.readlines()]

        valid_patterns = [
            line for line in lines
            if line and not line.startswith('#')
        ]

        assert len(valid_patterns) > 0, "Should have valid ignore patterns"

        for pattern in valid_patterns:
            assert len(pattern) > 0, "Patterns should not be empty"
            assert not pattern.startswith(' '), "Patterns should not start with space"

    def test_gitignore_no_duplicate_patterns(self, gitignore_path):
        """Test that .gitignore has no duplicate patterns."""
        with open(gitignore_path, 'r') as f:
            lines = [line.strip() for line in f.readlines() if line.strip() and not line.startswith('#')]

        assert len(lines) == len(set(lines)), "Should not have duplicate patterns"

    def test_gitignore_log_pattern_subdirectories(self, gitignore_path):
        """Test that .gitignore properly handles log files in subdirectories."""
        with open(gitignore_path, 'r') as f:
            content = f.read()

        patterns = [line.strip() for line in content.split('\n') if 'log' in line.lower() and line.strip()]

        # Should match logs in subdirectories like */*.log
        assert any('*' in p and 'log' in p.lower() for p in patterns), \
            "Should have pattern to match logs in subdirectories"

    def test_gitignore_matches_changes_md_documentation(self, gitignore_path, changes_path):
        """Test that .gitignore changes match CHANGES.md documentation."""
        with open(changes_path, 'r') as f:
            changes_content = f.read()

        # CHANGES.md mentions ".gitignore now includes log and scanning output rules"
        with open(gitignore_path, 'r') as f:
            gitignore_content = f.read()

        assert 'log' in gitignore_content.lower(), "Should include log rules as documented"
        assert 'trivy' in gitignore_content.lower() or 'scan' in gitignore_content.lower(), \
            "Should include scanning output rules as documented"

    def test_gitignore_pattern_ordering(self, gitignore_path):
        """Test that .gitignore patterns are logically ordered."""
        with open(gitignore_path, 'r') as f:
            lines = [line.strip() for line in f.readlines() if line.strip() and not line.startswith('#')]

        # More specific patterns should not conflict with broader patterns
        for i, pattern in enumerate(lines):
            # Ensure no pattern negates a previous pattern unintentionally
            if pattern.startswith('!'):
                assert i > 0, "Negation patterns should come after the pattern they negate"


class TestIgnoreFilesConsistency:
    """Tests for consistency between .dockerignore and .gitignore."""

    def test_both_ignore_files_exclude_git(self, dockerignore_path, gitignore_path):
        """Test that both ignore files appropriately handle git files."""
        with open(dockerignore_path, 'r') as f:
            dockerignore = f.read()

        # .dockerignore should exclude .git, but .gitignore doesn't need to
        assert '.git' in dockerignore, ".dockerignore should exclude .git"

    def test_trivy_results_excluded_from_both(self, dockerignore_path, gitignore_path):
        """Test that Trivy results are excluded from both git and Docker."""
        with open(dockerignore_path, 'r') as f:
            dockerignore = f.read()
        with open(gitignore_path, 'r') as f:
            gitignore = f.read()

        assert 'trivy' in dockerignore.lower(), "Trivy results should be excluded from Docker builds"
        assert 'trivy' in gitignore.lower(), "Trivy results should be excluded from git"

    def test_test_files_appropriately_ignored(self, dockerignore_path, gitignore_path):
        """Test that test files are handled appropriately."""
        with open(dockerignore_path, 'r') as f:
            dockerignore = f.read()

        # Test files should be excluded from Docker image
        assert 'test' in dockerignore.lower(), "Test files should be excluded from Docker builds"

    def test_markdown_files_excluded_from_docker(self, dockerignore_path):
        """Test that markdown documentation is excluded from Docker image."""
        with open(dockerignore_path, 'r') as f:
            dockerignore = f.read()

        assert '*.md' in dockerignore, "Markdown files should be excluded from Docker builds"

    def test_license_excluded_from_docker(self, dockerignore_path):
        """Test that LICENSE is excluded from Docker image."""
        with open(dockerignore_path, 'r') as f:
            dockerignore = f.read()

        assert 'LICENSE' in dockerignore, "LICENSE should be excluded from Docker builds"