"""Test configuration and fixtures for the docker-base project."""
import os
import pytest


@pytest.fixture
def project_root():
    """Return the absolute path to the project root directory."""
    return os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))


@pytest.fixture
def dockerfile_path(project_root):
    """Return the path to the Dockerfile."""
    return os.path.join(project_root, 'Dockerfile')


@pytest.fixture
def dockerignore_path(project_root):
    """Return the path to the .dockerignore file."""
    return os.path.join(project_root, '.dockerignore')


@pytest.fixture
def gitignore_path(project_root):
    """Return the path to the .gitignore file."""
    return os.path.join(project_root, '.gitignore')


@pytest.fixture
def tests_yaml_path(project_root):
    """Return the path to the tests.yaml file."""
    return os.path.join(project_root, 'tests.yaml')


@pytest.fixture
def pr_scan_workflow_path(project_root):
    """Return the path to the PR scan workflow."""
    return os.path.join(project_root, '.github', 'workflows', 'pr-scan.yml')


@pytest.fixture
def trivy_workflow_path(project_root):
    """Return the path to the Trivy workflow."""
    return os.path.join(project_root, '.github', 'workflows', 'trivy.yml')


@pytest.fixture
def readme_path(project_root):
    """Return the path to the README.md file."""
    return os.path.join(project_root, 'README.md')


@pytest.fixture
def changes_path(project_root):
    """Return the path to the CHANGES.md file."""
    return os.path.join(project_root, 'CHANGES.md')