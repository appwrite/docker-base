"""Tests for YAML configuration files."""
import os
import yaml
import pytest


class TestWorkflowYAML:
    """Tests for GitHub Actions workflow YAML files."""

    def test_pr_scan_workflow_valid_yaml(self, pr_scan_workflow_path):
        """Test that pr-scan.yml is valid YAML."""
        assert os.path.exists(pr_scan_workflow_path), "PR scan workflow file should exist"
        with open(pr_scan_workflow_path, 'r') as f:
            data = yaml.safe_load(f)
        assert data is not None, "YAML should parse successfully"

    def test_pr_scan_workflow_structure(self, pr_scan_workflow_path):
        """Test that pr-scan.yml has required structure."""
        with open(pr_scan_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        assert 'name' in data, "Workflow should have a name"
        assert data['name'] == 'PR Security Scan', "Workflow name should be 'PR Security Scan'"
        # 'on' is a YAML keyword that gets parsed as boolean True
        assert 'on' in data or True in data, "Workflow should have triggers"
        triggers = data.get('on', data.get(True))
        assert 'pull_request_target' in triggers, "Workflow should trigger on pull_request_target"
        assert 'jobs' in data, "Workflow should have jobs"
        assert 'scan' in data['jobs'], "Workflow should have a scan job"

    def test_pr_scan_workflow_permissions(self, pr_scan_workflow_path):
        """Test that pr-scan.yml has correct permissions."""
        with open(pr_scan_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        assert 'permissions' in data, "Workflow should define permissions"
        assert data['permissions']['contents'] == 'read', "Should have read contents permission"

        scan_job = data['jobs']['scan']
        assert 'permissions' in scan_job, "Scan job should define permissions"
        assert scan_job['permissions']['contents'] == 'read', "Scan job should have read contents permission"
        assert scan_job['permissions']['security-events'] == 'write', "Scan job should have write security-events permission"

    def test_pr_scan_workflow_steps(self, pr_scan_workflow_path):
        """Test that pr-scan.yml has required steps."""
        with open(pr_scan_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        steps = data['jobs']['scan']['steps']
        assert len(steps) >= 3, "Scan job should have at least 3 steps"

        step_names = [step['name'] for step in steps]
        assert 'Checkout code' in step_names, "Should have checkout step"
        assert 'Build an image from Dockerfile' in step_names, "Should have Docker build step"
        assert 'Run Trivy vulnerability scanner' in step_names, "Should have Trivy scan step"
        assert 'Upload Trivy scan results to GitHub Security tab' in step_names, "Should upload SARIF results"

    def test_pr_scan_trivy_action_version(self, pr_scan_workflow_path):
        """Test that Trivy action uses a specific version."""
        with open(pr_scan_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        steps = data['jobs']['scan']['steps']
        trivy_step = next(s for s in steps if s['name'] == 'Run Trivy vulnerability scanner')
        assert 'uses' in trivy_step, "Trivy step should use an action"
        assert 'aquasecurity/trivy-action@0.35.0' in trivy_step['uses'], "Should use trivy-action v0.35.0"

    def test_pr_scan_trivy_configuration(self, pr_scan_workflow_path):
        """Test that Trivy scanner is configured correctly."""
        with open(pr_scan_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        steps = data['jobs']['scan']['steps']
        trivy_step = next(s for s in steps if s['name'] == 'Run Trivy vulnerability scanner')

        assert 'with' in trivy_step, "Trivy step should have configuration"
        config = trivy_step['with']
        assert 'image-ref' in config, "Should specify image reference"
        assert 'format' in config and config['format'] == 'template', "Should use template format"
        assert 'output' in config and config['output'] == 'trivy-results.sarif', "Should output to SARIF file"
        assert 'severity' in config and config['severity'] == 'CRITICAL,HIGH', "Should scan for CRITICAL,HIGH severity"

    def test_trivy_workflow_valid_yaml(self, trivy_workflow_path):
        """Test that trivy.yml is valid YAML."""
        assert os.path.exists(trivy_workflow_path), "Trivy workflow file should exist"
        with open(trivy_workflow_path, 'r') as f:
            data = yaml.safe_load(f)
        assert data is not None, "YAML should parse successfully"

    def test_trivy_workflow_structure(self, trivy_workflow_path):
        """Test that trivy.yml has required structure."""
        with open(trivy_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        assert 'name' in data, "Workflow should have a name"
        assert data['name'] == 'trivy', "Workflow name should be 'trivy'"
        # 'on' is a YAML keyword that gets parsed as boolean True
        assert 'on' in data or True in data, "Workflow should have triggers"
        triggers = data.get('on', data.get(True))
        assert 'push' in triggers, "Workflow should trigger on push"
        assert 'pull_request' in triggers, "Workflow should trigger on pull_request"
        assert 'schedule' in triggers, "Workflow should have scheduled runs"

    def test_trivy_workflow_schedule(self, trivy_workflow_path):
        """Test that trivy.yml has correct schedule."""
        with open(trivy_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        # 'on' is a YAML keyword that gets parsed as boolean True
        triggers = data.get('on', data.get(True))
        schedule = triggers['schedule']
        assert len(schedule) > 0, "Should have at least one scheduled run"
        assert 'cron' in schedule[0], "Schedule should use cron syntax"
        assert schedule[0]['cron'] == '43 11 * * 6', "Should run at 11:43 on Saturdays"

    def test_trivy_workflow_branches(self, trivy_workflow_path):
        """Test that trivy.yml targets correct branches."""
        with open(trivy_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        # 'on' is a YAML keyword that gets parsed as boolean True
        triggers = data.get('on', data.get(True))
        assert 'main' in triggers['push']['branches'], "Push trigger should include main branch"
        assert 'main' in triggers['pull_request']['branches'], "PR trigger should include main branch"

    def test_trivy_workflow_permissions(self, trivy_workflow_path):
        """Test that trivy.yml has correct permissions."""
        with open(trivy_workflow_path, 'r') as f:
            data = yaml.safe_load(f)

        assert 'permissions' in data, "Workflow should define permissions"
        assert data['permissions']['contents'] == 'read', "Should have read contents permission"

        build_job = data['jobs']['build']
        assert 'permissions' in build_job, "Build job should define permissions"
        assert build_job['permissions']['contents'] == 'read', "Build job should have read contents permission"
        assert build_job['permissions']['security-events'] == 'write', "Build job should have write security-events permission"

    def test_both_workflows_use_same_trivy_version(self, pr_scan_workflow_path, trivy_workflow_path):
        """Test that both workflows use the same Trivy action version."""
        with open(pr_scan_workflow_path, 'r') as f:
            pr_scan_data = yaml.safe_load(f)
        with open(trivy_workflow_path, 'r') as f:
            trivy_data = yaml.safe_load(f)

        pr_scan_steps = pr_scan_data['jobs']['scan']['steps']
        trivy_steps = trivy_data['jobs']['build']['steps']

        pr_scan_trivy = next(s for s in pr_scan_steps if 'trivy-action' in s.get('uses', ''))
        trivy_trivy = next(s for s in trivy_steps if 'trivy-action' in s.get('uses', ''))

        assert pr_scan_trivy['uses'] == trivy_trivy['uses'], "Both workflows should use the same Trivy version"

    def test_both_workflows_use_same_docker_image_tag(self, pr_scan_workflow_path, trivy_workflow_path):
        """Test that both workflows use consistent Docker image tagging."""
        with open(pr_scan_workflow_path, 'r') as f:
            pr_scan_data = yaml.safe_load(f)
        with open(trivy_workflow_path, 'r') as f:
            trivy_data = yaml.safe_load(f)

        pr_scan_steps = pr_scan_data['jobs']['scan']['steps']
        trivy_steps = trivy_data['jobs']['build']['steps']

        pr_scan_build = next(s for s in pr_scan_steps if s['name'] == 'Build an image from Dockerfile')
        trivy_build = next(s for s in trivy_steps if s['name'] == 'Build an image from Dockerfile')

        assert 'appwrite/docker-base:${{ github.sha }}' in pr_scan_build['run'], "PR scan should tag with github.sha"
        assert 'appwrite/docker-base:${{ github.sha }}' in trivy_build['run'], "Trivy workflow should tag with github.sha"


class TestContainerStructureTestYAML:
    """Tests for the container structure test configuration."""

    def test_tests_yaml_valid_yaml(self, tests_yaml_path):
        """Test that tests.yaml is valid YAML."""
        assert os.path.exists(tests_yaml_path), "tests.yaml file should exist"
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)
        assert data is not None, "YAML should parse successfully"

    def test_tests_yaml_schema_version(self, tests_yaml_path):
        """Test that tests.yaml declares correct schema version."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        assert 'schemaVersion' in data, "Should declare schema version"
        assert data['schemaVersion'] == '2.0.0', "Should use schema version 2.0.0"

    def test_tests_yaml_has_command_tests(self, tests_yaml_path):
        """Test that tests.yaml defines command tests."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        assert 'commandTests' in data, "Should have command tests"
        assert len(data['commandTests']) > 0, "Should have at least one command test"

    def test_tests_yaml_imagemagick_version(self, tests_yaml_path):
        """Test that ImageMagick version test matches expected version."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        imagemagick_test = next(t for t in data['commandTests'] if t['name'] == 'Imagemagick command')
        assert imagemagick_test['command'] == 'magick', "Should test magick command"
        assert '--version' in imagemagick_test['args'], "Should check version"
        assert any('7.1.2.15' in output for output in imagemagick_test['expectedOutput']), "Should expect ImageMagick 7.1.2.15"

    def test_tests_yaml_php_modules_test(self, tests_yaml_path):
        """Test that PHP modules test is comprehensive."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        php_test = next(t for t in data['commandTests'] if t['name'] == 'PHP modules')
        expected_modules = [
            'swoole', 'redis', 'imagick', 'yaml', 'maxminddb',
            'brotli', 'lz4', 'zstd', 'snappy', 'scrypt',
            'opentelemetry', 'protobuf', 'gd'
        ]

        for module in expected_modules:
            assert module in php_test['expectedOutput'], f"Should test for {module} module"

    def test_tests_yaml_all_tests_have_required_fields(self, tests_yaml_path):
        """Test that all command tests have required fields."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        for test in data['commandTests']:
            assert 'name' in test, "Each test should have a name"
            assert 'command' in test, "Each test should have a command"
            assert 'args' in test or 'expectedOutput' in test, "Each test should have args or expectedOutput"
            assert 'expectedOutput' in test, "Each test should have expectedOutput"

    def test_tests_yaml_critical_commands(self, tests_yaml_path):
        """Test that critical system commands are tested."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        test_names = [t['name'] for t in data['commandTests']]
        critical_tests = [
            'Imagemagick command',
            'rsync command',
            'Certbot command',
            'Docker command',
            'Docker Compose command',
            'PHP modules'
        ]

        for critical_test in critical_tests:
            assert critical_test in test_names, f"Should test {critical_test}"

    def test_tests_yaml_webp_support(self, tests_yaml_path):
        """Test that ImageMagick WEBP support is verified."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        webp_test = next(t for t in data['commandTests'] if t['name'] == 'ImageMagick supported formats')
        assert 'WEBP' in str(webp_test['expectedOutput']), "Should verify WEBP support in ImageMagick"

    def test_tests_yaml_no_duplicate_test_names(self, tests_yaml_path):
        """Test that there are no duplicate test names."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        test_names = [t['name'] for t in data['commandTests']]
        assert len(test_names) == len(set(test_names)), "Test names should be unique"

    def test_tests_yaml_expected_output_not_empty(self, tests_yaml_path):
        """Test that all tests have non-empty expected output."""
        with open(tests_yaml_path, 'r') as f:
            data = yaml.safe_load(f)

        for test in data['commandTests']:
            expected = test.get('expectedOutput', [])
            assert len(expected) > 0, f"Test '{test['name']}' should have expected output"