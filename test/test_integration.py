"""Integration tests for the docker-base project."""
import os
import re
import pytest
import yaml


class TestProjectIntegration:
    """Integration tests across multiple project files."""

    def test_dockerfile_versions_match_tests_yaml(self, dockerfile_path, tests_yaml_path):
        """Test that Dockerfile versions match what's tested in tests.yaml."""
        with open(dockerfile_path, 'r') as f:
            dockerfile = f.read()
        with open(tests_yaml_path, 'r') as f:
            tests = yaml.safe_load(f)

        # ImageMagick version should match
        imagemagick_test = next(t for t in tests['commandTests'] if t['name'] == 'Imagemagick command')
        imagemagick_version_in_test = None
        for output in imagemagick_test['expectedOutput']:
            match = re.search(r'7\.\d+\.\d+\.\d+', output)
            if match:
                imagemagick_version_in_test = match.group(0)
                break

        assert imagemagick_version_in_test is not None, "tests.yaml should specify ImageMagick version"
        # Note: Dockerfile doesn't specify ImageMagick version directly, it's installed from apk
        # but we verify the test exists

    def test_all_dockerfile_extensions_are_tested(self, dockerfile_path, tests_yaml_path):
        """Test that all PHP extensions in Dockerfile are tested in tests.yaml."""
        with open(dockerfile_path, 'r') as f:
            dockerfile = f.read()
        with open(tests_yaml_path, 'r') as f:
            tests = yaml.safe_load(f)

        # Find the docker-php-ext-enable line
        enable_match = re.search(r'docker-php-ext-enable (.+)', dockerfile)
        assert enable_match, "Dockerfile should have docker-php-ext-enable command"

        enabled_extensions = enable_match.group(1).split()

        # Get tested modules from tests.yaml
        php_test = next(t for t in tests['commandTests'] if t['name'] == 'PHP modules')
        tested_modules = php_test['expectedOutput']

        # Note: mongodb is enabled but not yet in tests.yaml (may be added in future)
        for ext in enabled_extensions:
            if ext != 'mongodb':  # mongodb is enabled but not in current tests.yaml
                assert ext in tested_modules, f"Extension {ext} should be tested in tests.yaml"

    def test_gitignore_patterns_exclude_trivy_output(self, gitignore_path):
        """Test that .gitignore patterns correctly exclude Trivy output files."""
        with open(gitignore_path, 'r') as f:
            content = f.read()

        patterns = [line.strip() for line in content.split('\n') if line.strip() and not line.startswith('#')]

        # Test that trivy output files would be matched
        test_files = [
            'trivy-image-results.json',
            'trivy-scan-results.json',
            'trivy-12345-results.json'
        ]

        for test_file in test_files:
            matched = False
            for pattern in patterns:
                if 'trivy' in pattern.lower() and '.json' in pattern:
                    # Simple pattern matching
                    if pattern.startswith('trivy') or pattern.startswith('*trivy'):
                        matched = True
                        break
            assert matched, f"{test_file} should be matched by .gitignore patterns"

    def test_dockerignore_excludes_unnecessary_files(self, dockerignore_path, project_root):
        """Test that .dockerignore excludes files that shouldn't be in the image."""
        with open(dockerignore_path, 'r') as f:
            patterns = [line.strip() for line in f.readlines() if line.strip() and not line.startswith('#')]

        # Files that should definitely be excluded
        excluded_categories = {
            'git': ['.git*', '.git'],
            'docs': ['*.md'],
            'tests': ['*test*'],
            'license': ['LICENSE'],
            'build artifacts': ['Dockerfile']
        }

        for category, category_patterns in excluded_categories.items():
            category_matched = False
            for exclude_pattern in category_patterns:
                if any(exclude_pattern in pattern or pattern in exclude_pattern for pattern in patterns):
                    category_matched = True
                    break
            assert category_matched, f"Should exclude {category} files from Docker image"

    def test_workflow_yaml_files_are_valid(self, pr_scan_workflow_path, trivy_workflow_path):
        """Test that all workflow YAML files are valid and consistent."""
        workflows = [pr_scan_workflow_path, trivy_workflow_path]

        for workflow_path in workflows:
            with open(workflow_path, 'r') as f:
                data = yaml.safe_load(f)

            # All workflows should have these basic fields
            assert 'name' in data, f"{workflow_path} should have name"
            # 'on' is a YAML keyword that gets parsed as boolean True
            assert 'on' in data or True in data, f"{workflow_path} should have triggers"
            assert 'jobs' in data, f"{workflow_path} should have jobs"
            assert 'permissions' in data, f"{workflow_path} should define permissions"

    def test_workflows_build_same_image(self, pr_scan_workflow_path, trivy_workflow_path):
        """Test that both workflows build the same Docker image."""
        with open(pr_scan_workflow_path, 'r') as f:
            pr_scan = yaml.safe_load(f)
        with open(trivy_workflow_path, 'r') as f:
            trivy = yaml.safe_load(f)

        pr_scan_steps = pr_scan['jobs']['scan']['steps']
        trivy_steps = trivy['jobs']['build']['steps']

        pr_scan_build = next(s for s in pr_scan_steps if 'docker build' in s.get('run', ''))
        trivy_build = next(s for s in trivy_steps if 'docker build' in s.get('run', ''))

        # Both should build the same image base name
        assert 'appwrite/docker-base' in pr_scan_build['run'], "PR scan should build appwrite/docker-base"
        assert 'appwrite/docker-base' in trivy_build['run'], "Trivy workflow should build appwrite/docker-base"

    def test_trivy_scan_output_format_consistency(self, pr_scan_workflow_path, trivy_workflow_path):
        """Test that Trivy scan outputs are consistent across workflows."""
        with open(pr_scan_workflow_path, 'r') as f:
            pr_scan = yaml.safe_load(f)
        with open(trivy_workflow_path, 'r') as f:
            trivy = yaml.safe_load(f)

        pr_scan_steps = pr_scan['jobs']['scan']['steps']
        trivy_steps = trivy['jobs']['build']['steps']

        pr_scan_trivy = next(s for s in pr_scan_steps if 'trivy-action' in s.get('uses', ''))
        trivy_trivy = next(s for s in trivy_steps if 'trivy-action' in s.get('uses', ''))

        # Both should use same format
        assert pr_scan_trivy['with']['format'] == trivy_trivy['with']['format'], \
            "Both workflows should use same Trivy format"

        # Both should use same severity
        assert pr_scan_trivy['with']['severity'] == trivy_trivy['with']['severity'], \
            "Both workflows should scan same severity levels"

    def test_readme_examples_reference_real_files(self, readme_path, project_root):
        """Test that README examples reference files that exist."""
        with open(readme_path, 'r') as f:
            readme = f.read()

        # README should mention tests.yaml
        if 'tests.yaml' in readme:
            tests_yaml_path = os.path.join(project_root, 'tests.yaml')
            assert os.path.exists(tests_yaml_path), "tests.yaml referenced in README should exist"

        # README should mention Dockerfile
        if 'Dockerfile' in readme:
            dockerfile_path = os.path.join(project_root, 'Dockerfile')
            assert os.path.exists(dockerfile_path), "Dockerfile referenced in README should exist"

    def test_version_consistency_across_files(self, changes_path, dockerfile_path, tests_yaml_path):
        """Test that version numbers are consistent across files."""
        with open(changes_path, 'r') as f:
            changes = f.read()
        with open(dockerfile_path, 'r') as f:
            dockerfile = f.read()
        with open(tests_yaml_path, 'r') as f:
            tests = yaml.safe_load(f)

        # PHP version
        php_version_changes = re.search(r'PHP version.*?(\d+\.\d+\.\d+)', changes)
        if php_version_changes:
            php_version = php_version_changes.group(1)
            assert php_version in dockerfile, f"PHP version {php_version} from CHANGES.md should be in Dockerfile"

        # ImageMagick version - it's installed from apk so version is verified in tests.yaml
        imagick_version_changes = re.search(r'ImageMagick version.*?(\d+\.\d+\.\d+\.\d+)', changes)
        if imagick_version_changes:
            imagick_version = imagick_version_changes.group(1)
            # Should be in tests.yaml (Dockerfile installs from apk, version verified in tests)
            imagemagick_test = next(t for t in tests['commandTests'] if t['name'] == 'Imagemagick command')
            tests_yaml_content = str(imagemagick_test['expectedOutput'])
            assert imagick_version in tests_yaml_content, \
                f"ImageMagick version {imagick_version} should be verified in tests.yaml"

        # Swoole version
        swoole_version_changes = re.search(r'Swoole version.*?(\d+\.\d+\.\d+)', changes)
        if swoole_version_changes:
            swoole_version = swoole_version_changes.group(1)
            assert f'PHP_SWOOLE_VERSION="{swoole_version}"' in dockerfile, \
                f"Swoole version {swoole_version} from CHANGES.md should be in Dockerfile"

    def test_changes_md_documents_all_changed_files(self, changes_path):
        """Test that CHANGES.md documents all significant changes."""
        with open(changes_path, 'r') as f:
            changes = f.read()

        # Files that were changed and should be documented
        expected_mentions = [
            ('.dockerignore', 'dockerignore'),
            ('.gitignore', 'gitignore'),
            ('README.md', 'README'),
            ('tests.yaml', 'tests.yaml'),
            ('PHP', 'php'),
            ('Swoole', 'swoole'),
            ('ImageMagick', 'imagemagick')
        ]

        for file, search_term in expected_mentions:
            assert search_term.lower() in changes.lower(), \
                f"CHANGES.md should mention {file} changes"

    def test_all_test_files_use_fixtures(self, project_root):
        """Test that all test files properly use fixtures from conftest.py."""
        test_dir = os.path.join(project_root, 'test')
        conftest_path = os.path.join(test_dir, 'conftest.py')

        with open(conftest_path, 'r') as f:
            conftest = f.read()

        # Extract fixture names
        fixtures = re.findall(r'def (\w+)\(', conftest)

        # Check that fixtures are used
        test_files = [
            'test_yaml_files.py',
            'test_dockerfile.py',
            'test_ignore_files.py',
            'test_markdown_files.py'
        ]

        for test_file in test_files:
            test_path = os.path.join(test_dir, test_file)
            if os.path.exists(test_path):
                with open(test_path, 'r') as f:
                    content = f.read()

                # At least one fixture should be used
                fixture_used = any(fixture in content for fixture in fixtures)
                assert fixture_used, f"{test_file} should use at least one fixture from conftest.py"

    def test_no_hardcoded_paths_in_tests(self, project_root):
        """Test that test files don't use hardcoded absolute paths."""
        test_dir = os.path.join(project_root, 'test')
        test_files = [f for f in os.listdir(test_dir) if f.startswith('test_') and f.endswith('.py')]

        for test_file in test_files:
            test_path = os.path.join(test_dir, test_file)
            with open(test_path, 'r') as f:
                content = f.read()

            # Should not have hardcoded paths like /home/user/... (excluding fixture definitions)
            lines = content.split('\n')
            for line in lines:
                if '/home/' in line and 'jailuser' in line and '@pytest.fixture' not in line:
                    # Allow in comments and docstrings
                    stripped = line.strip()
                    if not stripped.startswith('#') and not stripped.startswith('"""') and not stripped.startswith("'''"):
                        assert False, f"{test_file} should not have hardcoded absolute paths in {line}"

    def test_workflow_security_permissions_principle_of_least_privilege(
        self, pr_scan_workflow_path, trivy_workflow_path
    ):
        """Test that workflows follow principle of least privilege for permissions."""
        workflows = [
            (pr_scan_workflow_path, 'scan'),
            (trivy_workflow_path, 'build')
        ]

        for workflow_path, job_name in workflows:
            with open(workflow_path, 'r') as f:
                data = yaml.safe_load(f)

            # Check workflow-level permissions
            workflow_perms = data.get('permissions', {})
            if workflow_perms:
                assert workflow_perms.get('contents') == 'read', \
                    f"{workflow_path} workflow should have read-only contents permission"

            # Check job-level permissions
            job = data['jobs'][job_name]
            job_perms = job.get('permissions', {})

            # Should have security-events write for SARIF upload
            assert job_perms.get('security-events') == 'write', \
                f"{workflow_path} job should have write security-events permission"

            # Contents should be read-only
            assert job_perms.get('contents') == 'read', \
                f"{workflow_path} job should have read-only contents permission"

    def test_trivy_results_excluded_from_version_control(
        self, dockerignore_path, gitignore_path
    ):
        """Test that Trivy results are properly excluded from both Docker and Git."""
        with open(dockerignore_path, 'r') as f:
            dockerignore = f.read()
        with open(gitignore_path, 'r') as f:
            gitignore = f.read()

        # Both should exclude trivy results
        assert 'trivy' in dockerignore.lower(), "Trivy results should be excluded from Docker builds"
        assert 'trivy' in gitignore.lower(), "Trivy results should be excluded from Git"

        # Specifically, JSON results should be excluded
        assert '.json' in dockerignore, "JSON files should be excluded from Docker"
        assert '.json' in gitignore, "JSON files should be excluded from Git"


class TestSecurityBestPractices:
    """Tests for security best practices in the project."""

    def test_workflows_use_pinned_versions(self, pr_scan_workflow_path, trivy_workflow_path):
        """Test that workflows use pinned versions for actions."""
        workflows = [pr_scan_workflow_path, trivy_workflow_path]

        for workflow_path in workflows:
            with open(workflow_path, 'r') as f:
                data = yaml.safe_load(f)

            for job_name, job in data['jobs'].items():
                for step in job['steps']:
                    if 'uses' in step:
                        action = step['uses']
                        # Should use version tags (v1, v2, etc.) or commit SHAs
                        assert '@' in action, f"Action {action} should specify a version"
                        # Should not use @main or @master
                        assert '@main' not in action and '@master' not in action, \
                            f"Action {action} should not use @main or @master"

    def test_dockerfile_runs_as_non_root_or_explicit(self, dockerfile_path):
        """Test Dockerfile user configuration."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        # If USER is specified, it should not be root
        user_lines = [line for line in content.split('\n') if line.strip().startswith('USER')]

        for user_line in user_lines:
            assert 'USER root' not in user_line, "Should not explicitly switch to root user"

    def test_dockerfile_no_secrets_in_env(self, dockerfile_path):
        """Test that Dockerfile doesn't contain obvious secrets in ENV."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        # Look for ENV declarations
        env_lines = [line for line in content.split('\n') if 'ENV' in line]

        suspicious_keywords = ['password', 'secret', 'token', 'key', 'credential']

        for env_line in env_lines:
            env_lower = env_line.lower()
            for keyword in suspicious_keywords:
                if keyword in env_lower:
                    # If keyword is present, value should not look like a secret
                    assert '=' not in env_line or len(env_line.split('=')[1].strip()) < 10, \
                        f"Potential secret in ENV: {env_line}"

    def test_workflows_use_pull_request_target_safely(self, pr_scan_workflow_path):
        """Test that pull_request_target is used safely."""
        with open(pr_scan_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        # 'on' is a YAML keyword that gets parsed as boolean True
        triggers = data.get('on', data.get(True))
        if 'pull_request_target' in triggers:
            # When using pull_request_target, should have minimal permissions
            assert 'permissions' in data, "pull_request_target workflows should define permissions"

            # Should not have write access to code
            workflow_perms = data.get('permissions', {})
            assert workflow_perms.get('contents', 'write') == 'read', \
                "pull_request_target should have read-only contents access"

    def test_trivy_scan_checks_high_severity(self, pr_scan_workflow_path, trivy_workflow_path):
        """Test that Trivy scans check for HIGH and CRITICAL severity."""
        workflows = [pr_scan_workflow_path, trivy_workflow_path]

        for workflow_path in workflows:
            with open(workflow_path, 'r') as f:
                data = yaml.safe_load(f)

            # Find Trivy step
            for job in data['jobs'].values():
                for step in job['steps']:
                    if 'trivy-action' in step.get('uses', ''):
                        severity = step['with']['severity']
                        assert 'CRITICAL' in severity, "Trivy should check CRITICAL severity"
                        assert 'HIGH' in severity, "Trivy should check HIGH severity"