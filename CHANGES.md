# CHANGELOG

## Version 1.1.1

### Add

* XDebug optional build variant — build with `--target xdebug`

### Change

* Final image now uses runtime-only packages (no `-dev` packages or build tools)
* PHP extension `.so` files stripped of debug symbols to reduce size
* PHP extensions compiled in isolated build stages and copied into final image
* `core-extensions` build stage compiles gd, intl, pdo_mysql, pdo_pgsql, sockets

### Fix

* .github/workflows/build-and-push.yml manifest_build_and_push_on_feature no longer triggers on tag creation
* .github/workflows/build-and-push.yml manifest_build_and_push_on_tag now correctly builds on tag creation

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
* Dockerfile base now based on `phpswoole/swoole:php8.5-alpine`
* Dockerfile compile and final stage system packages aligned
* GitHub action for container-structure-test now uses a marketplace action
* GitHub action runners pinned to Ubuntu 24.04
* ImageMagick version bumped to 7.1.2.15 via APK
* PHP version bumped to 8.5
* Refactored multi-arch build process to prevent cross-arch builds requiring long wait times

### Fix

* README.md usage instructions more detailed

### Miscellaneous

### Remove

* Build tools from final stage of Dockerfile
* GitHub action to Setup QEMU as GitHub now provides native ARM runners
