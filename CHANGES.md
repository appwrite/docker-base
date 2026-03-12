# CHANGELOG

## Version 1.1.0

### Add

* .dockerignore
* .github/workflows/pr-scan.yml to scan all commit pushes for vulnerabilities
* base_image and php_build_date to container labels
* container image build action to publish image using commit sha
* container-structure-test to check PHP version (currently set to 8.5.3)
* container-structure-test to check swoole version (currently set to 6.2.0)
* SECURITY.md to align with appwrite/appwrite

### Change

* .github/*.yml steps updated to latest versions
* Better document use of `docker buildx ...` for local builds
* Better noted and organized the different build processes for PHP extensions
* Date component of PHP extension shared objects directory now a build argument
* Dockerfile compile and final stage system packages aligned
* Github action for container-structure-test now uses a marketplace action
* Github action runners pinned to Ubuntu 24.04
* ImageMagick version bumped to 7.1.2.15, tests.yaml aligned to ensure new version
* PHP version bumped to 8.5.3
* Refactored multi-arch build process to prevent cross-arch builds requiring long wait times
* Swoole version bumped to 6.2.0

### Fixes

* README.md usage instructions more detailed

### Miscellaneous

### Removed

* Build tools from final stage of Dockerfile
* Github action to Setup QEMU as GitHub now provides native ARM runners
