"""Tests for Dockerfile configuration and structure."""
import os
import re
import pytest


class TestDockerfile:
    """Tests for Dockerfile structure and best practices."""

    def test_dockerfile_exists(self, dockerfile_path):
        """Test that Dockerfile exists."""
        assert os.path.exists(dockerfile_path), "Dockerfile should exist"

    def test_dockerfile_not_empty(self, dockerfile_path):
        """Test that Dockerfile is not empty."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()
        assert len(content.strip()) > 0, "Dockerfile should not be empty"

    def test_dockerfile_base_image(self, dockerfile_path):
        """Test that Dockerfile uses correct base image."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'php:8.5.3-cli-alpine3.23' in content, "Should use PHP 8.5.3 as base image"
        assert 'ARG BASEIMAGE=' in content, "Should use ARG for base image"

    def test_dockerfile_multi_stage_build(self, dockerfile_path):
        """Test that Dockerfile uses multi-stage build."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        from_statements = re.findall(r'^FROM .* AS (\w+)', content, re.MULTILINE)
        assert len(from_statements) > 1, "Should use multi-stage build"

        expected_stages = [
            'compile', 'redis', 'swoole', 'imagick', 'yaml',
            'maxmind', 'mongodb', 'zstd', 'brotli', 'lz4',
            'snappy', 'scrypt', 'xdebug', 'opentelemetry', 'protobuf', 'gd', 'final'
        ]

        for stage in expected_stages:
            assert stage in from_statements, f"Should have {stage} build stage"

    def test_dockerfile_php_extensions(self, dockerfile_path):
        """Test that Dockerfile installs required PHP extensions."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        required_extensions = [
            'swoole', 'redis', 'imagick', 'yaml', 'maxminddb',
            'scrypt', 'zstd', 'brotli', 'lz4', 'snappy',
            'opentelemetry', 'protobuf', 'gd', 'mongodb'
        ]

        for extension in required_extensions:
            assert extension in content.lower(), f"Should install {extension} extension"

    def test_dockerfile_extension_versions(self, dockerfile_path):
        """Test that Dockerfile specifies extension versions."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        version_patterns = {
            'PHP_REDIS_VERSION': '6.3.0',
            'PHP_SWOOLE_VERSION': '6.2.0',
            'PHP_IMAGICK_VERSION': '3.8.1',
            'PHP_MONGODB_VERSION': '2.2.1',
            'PHP_YAML_VERSION': '2.3.0',
            'PHP_MAXMINDDB_VERSION': 'v1.13.1',
            'PHP_SCRYPT_VERSION': '2.0.1',
            'PHP_ZSTD_VERSION': '0.15.2',
            'PHP_BROTLI_VERSION': '0.18.3',
            'PHP_SNAPPY_VERSION': '0.2.3',
            'PHP_LZ4_VERSION': '0.6.0',
            'PHP_XDEBUG_VERSION': '3.5.1',
            'PHP_OPENTELEMETRY_VERSION': '1.2.1',
            'PHP_PROTOBUF_VERSION': '5.34.0',
        }

        for var_name, version in version_patterns.items():
            pattern = f'{var_name}="{version}"'
            assert pattern in content, f"Should define {var_name} as {version}"

    def test_dockerfile_swoole_version_matches_changes(self, dockerfile_path):
        """Test that Swoole version matches what's documented in CHANGES.md."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'PHP_SWOOLE_VERSION="6.2.0"' in content, "Swoole version should be 6.2.0 as per CHANGES.md"

    def test_dockerfile_php_version_matches_changes(self, dockerfile_path):
        """Test that PHP version matches what's documented in CHANGES.md."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'php:8.5.3-cli-alpine' in content, "PHP version should be 8.5.3 as per CHANGES.md"

    def test_dockerfile_uses_alpine(self, dockerfile_path):
        """Test that Dockerfile uses Alpine Linux for smaller image size."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'alpine' in content.lower(), "Should use Alpine Linux base image"

    def test_dockerfile_has_label(self, dockerfile_path):
        """Test that Dockerfile has maintainer label."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'LABEL maintainer=' in content, "Should have maintainer label"
        assert 'team@appwrite.io' in content, "Maintainer should be team@appwrite.io"

    def test_dockerfile_exposes_port(self, dockerfile_path):
        """Test that Dockerfile exposes port 80."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'EXPOSE 80' in content, "Should expose port 80"

    def test_dockerfile_sets_workdir(self, dockerfile_path):
        """Test that Dockerfile sets working directory."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'WORKDIR /usr/src/code' in content, "Should set working directory to /usr/src/code"

    def test_dockerfile_copies_extensions(self, dockerfile_path):
        """Test that Dockerfile copies all built extensions."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        extensions = [
            'swoole.so', 'redis.so', 'imagick.so', 'yaml.so',
            'maxminddb.so', 'scrypt.so', 'zstd.so', 'brotli.so',
            'lz4.so', 'snappy.so', 'xdebug.so', 'opentelemetry.so',
            'protobuf.so', 'gd.so', 'mongodb.so'
        ]

        for extension in extensions:
            assert f'COPY --from=.*{extension}' in content or extension in content, \
                f"Should copy {extension} from build stage"

    def test_dockerfile_enables_extensions(self, dockerfile_path):
        """Test that Dockerfile enables all PHP extensions."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        enable_line = [line for line in content.split('\n') if 'docker-php-ext-enable' in line]
        assert len(enable_line) > 0, "Should have docker-php-ext-enable command"

        extensions_to_enable = [
            'swoole', 'redis', 'imagick', 'yaml', 'maxminddb',
            'scrypt', 'zstd', 'brotli', 'lz4', 'snappy',
            'opentelemetry', 'protobuf', 'gd', 'mongodb'
        ]

        for extension in extensions_to_enable:
            assert extension in content, f"Should enable {extension} extension"

    def test_dockerfile_installs_system_packages(self, dockerfile_path):
        """Test that Dockerfile installs required system packages."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        system_packages = [
            'imagemagick', 'rsync', 'certbot', 'docker-cli',
            'docker-cli-compose', 'git', 'zip'
        ]

        for package in system_packages:
            assert package in content, f"Should install {package} system package"

    def test_dockerfile_cleans_cache(self, dockerfile_path):
        """Test that Dockerfile cleans package cache."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'rm -rf /var/cache/apk/*' in content, "Should clean apk cache"

    def test_dockerfile_removes_build_dependencies(self, dockerfile_path):
        """Test that Dockerfile removes build dependencies in final stage."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'apk del .deps' in content, "Should remove build dependencies"

    def test_dockerfile_swoole_configuration(self, dockerfile_path):
        """Test that Swoole is configured with required features."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert '--enable-sockets' in content, "Swoole should enable sockets"
        assert '--enable-http2' in content, "Swoole should enable HTTP/2"
        assert '--enable-openssl' in content, "Swoole should enable OpenSSL"
        assert '--enable-swoole-curl' in content, "Swoole should enable curl"

    def test_dockerfile_uses_git_clone_depth(self, dockerfile_path):
        """Test that Dockerfile uses shallow git clones for efficiency."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        git_clones = re.findall(r'git clone.*', content)
        depth_clones = [clone for clone in git_clones if '--depth 1' in clone or '--depth 1' in clone]

        assert len(depth_clones) > 0, "Should use shallow git clones (--depth 1) for efficiency"

    def test_dockerfile_no_latest_tags(self, dockerfile_path):
        """Test that Dockerfile doesn't use 'latest' tags."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        from_statements = re.findall(r'^FROM (.+)', content, re.MULTILINE)
        for from_stmt in from_statements:
            if not from_stmt.startswith('$'):  # Skip variables
                assert ':latest' not in from_stmt, "Should not use 'latest' tag in FROM statements"

    def test_dockerfile_extension_path_consistency(self, dockerfile_path):
        """Test that all extensions use consistent PHP extension path."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        extension_path = '/usr/local/lib/php/extensions/no-debug-non-zts-20240924/'
        copy_statements = re.findall(r'COPY --from=\w+ (.+) (.+)', content)

        for source, dest in copy_statements:
            if '.so' in source:
                assert extension_path in source, f"Extension source should use consistent path: {source}"
                assert extension_path in dest, f"Extension destination should use consistent path: {dest}"

    def test_dockerfile_has_cmd(self, dockerfile_path):
        """Test that Dockerfile has a CMD instruction."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'CMD' in content, "Should have CMD instruction"
        # CMD can be in exec form (JSON array) or shell form
        assert 'tail' in content and '/dev/null' in content, "CMD should keep container running"

    def test_dockerfile_imagemagick_dev_packages(self, dockerfile_path):
        """Test that Dockerfile includes ImageMagick development packages."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'imagemagick-dev' in content, "Should include imagemagick-dev package"
        assert 'libjpeg-turbo-dev' in content, "Should include libjpeg-turbo-dev"
        assert 'jpeg-dev' in content, "Should include jpeg-dev"
        assert 'libpng-dev' in content, "Should include libpng-dev"

    def test_dockerfile_image_format_support(self, dockerfile_path):
        """Test that Dockerfile includes support for various image formats."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        formats = ['libjxl', 'libavif', 'libheif', 'libwebp', 'imagemagick-heic']
        for fmt in formats:
            assert fmt in content, f"Should include {fmt} for image format support"

    def test_dockerfile_database_support(self, dockerfile_path):
        """Test that Dockerfile includes database extensions."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        assert 'pdo_mysql' in content, "Should include MySQL PDO support"
        assert 'pdo_pgsql' in content, "Should include PostgreSQL PDO support"
        assert 'postgresql-dev' in content, "Should include PostgreSQL development files"

    def test_dockerfile_compression_libraries(self, dockerfile_path):
        """Test that Dockerfile includes compression libraries."""
        with open(dockerfile_path, 'r') as f:
            content = f.read()

        compression = ['brotli', 'lz4', 'zstd']
        for lib in compression:
            assert f'{lib}-dev' in content, f"Should include {lib} development library"