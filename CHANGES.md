# CHANGELOG

## Version 0.2.0

### Add

* .dockerignore
* .github/workflows/pr-scan.yml to scan all commit pushes for vulnerabilities
* base_image and php_build_date to containber labels
* container image build action to publish image using commit sha

### Change

* .github/*.yml steps updated to latest versions
* .gitignore now includes log and scanning output rules
* Better document use of `docker-buildx build ...` for local builds
* Better noted and organized the different build processes for PHP extensions
* Date component of PHP extension shared objects directory now a build argument
* Dockerfile compile and final stage system packages aligned
* ImageMagick version bumped to 7.1.2.15, tests.yaml aligned to ensure new version
* PHP version bumped to 8.5.3
* Swoole version bumped to 6.2.0

### Fixes

* README.md usage instructions more detailed

### Miscellaneous

### Removed

* Multi-arch builds due to slow build times, the trade off is improved build times
