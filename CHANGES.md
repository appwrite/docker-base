# CHANGELOG

## Version 1.3.1

### Fix

* Run `apk upgrade` in the final image to pull patched `musl` and `xz-libs` — resolves CVE-2025-26519 (musl `qsort` stack corruption), the musl `iconv` GB18030 DoS, and the `xz` index-decoding buffer overflow (CVE-2026-34743, fixed in `xz-libs` 5.8.3-r0). The compile stage already ran `apk upgrade`, but the runtime stage didn't, so the published image was shipping unpatched libs from the base.

## Version 1.3.0

### Change

* Pin Swoole base image to `phpswoole/swoole:6.2.0-php8.5-alpine` (released 6.2.0, was previously tracking nightly `php8.5-alpine`) for reproducible builds
* `tests.yaml` PHP assertion bumped to 8.5.4 and Swoole assertion pinned to 6.2.0 to match the pinned base

### Fix

* Manifest workflow tag reference — `manifest_build_and_push_on_tag` now uses `github.ref_name` instead of `github.event.release.tag_name`, which is empty on plain tag-push events and broke the `1.2.2` tag run with `docker manifest create: invalid reference format`

## Version 1.2.2

### Remove

* PHP `opentelemetry` extension — its observer hooks override `zend_execute_ex` and disable opcache JIT on PHP 8.5

## Version 1.2.1

### Fix

* Restore `git` in final image — unintentionally dropped from runtime apk install in 1.2.0; required by VCS-dependent services

### Add

* container-structure-test for `git` command

### Change

* `tests.yaml` PHP assertion bumped to 8.5.5 (upstream `phpswoole/swoole:php8.5-alpine` update)
* `tests.yaml` Swoole assertion bumped to 6.2.1

## Version 1.2.0

### Add

* container-structure-test checks for PHP GD supported formats
* PHP GD compiled with AVIF, FreeType, JPEG, PNG, and WebP support
* tests-xdebug.yaml for testing the XDebug variant
* XDebug optional build variant — build with `--target xdebug`

### Change

* `core-extensions` build stage compiles gd, intl, pdo_mysql, pdo_pgsql, sockets
* Final image now uses runtime-only packages (no `-dev` packages or build tools)
* PHP extension `.so` files stripped of debug symbols to reduce size
* PHP extensions compiled in isolated build stages and copied into final image
* PHP version bumped to 8.5.4

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
