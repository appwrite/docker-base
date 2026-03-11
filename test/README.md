# Test Suite for docker-base

This directory contains comprehensive tests for the docker-base project.

## Running Tests

Install dependencies:
```bash
pip install -r test/requirements.txt
```

Run all tests:
```bash
pytest test/ -v
```

Run with coverage:
```bash
pytest test/ --cov=. --cov-report=html
```

## Test Structure

- `conftest.py` - Pytest configuration and fixtures
- `test_dockerfile.py` - Tests for Dockerfile structure, syntax, and best practices
- `test_yaml_files.py` - Tests for YAML configuration files (workflows, tests.yaml)
- `test_ignore_files.py` - Tests for .dockerignore and .gitignore patterns
- `test_markdown_files.py` - Tests for markdown documentation (README.md, CHANGES.md)
- `test_integration.py` - Integration tests across multiple files and security best practices

## Coverage

The test suite covers:
- **Dockerfile validation**: Multi-stage builds, PHP extensions, Alpine packages, security
- **YAML syntax and structure**: GitHub workflows, container structure tests
- **Ignore file patterns**: Docker and Git exclusion rules
- **Documentation**: README and CHANGES content validation
- **Integration**: Version consistency, workflow configuration, security policies
- **Security best practices**: Permission models, pinned versions, safe workflows