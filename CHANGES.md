# CHANGELOG

## Version 2.1.0

### Add

* Weekly dependency automation (`.github/workflows/dependencies.yml`). A scheduled job resolves the newest upstream release for every pinned Dockerfile source, rewrites the pins, opens a pull request, waits for the exact CI runs for that head, approves and merges it, then tags, builds, and publishes the release. A `recover` step resumes a run that died between merge and publish, so a half-finished release is completed rather than duplicated.
* PHP automation domain under `.github/scripts` — `Dependency` (catalog, resolvers, Dockerfile pin rewriting, reporting), `Automation` (release orchestration, version selection, merge and target validation, recovery), `Command`, and `Parity`. Entry points are `bin/dependencies.php`, `bin/orchestrator.php`, and `bin/parity.php`.
* Composer tooling for the automation: `lint` (Pint), `check` (PHPStan), `test` (PHPUnit), `parity` (asserts every source class has covering tests), and `verify` to run all four. CI runs `composer verify` before touching any dependency.
* `verify.yml` runs `composer validate --strict`, `composer check-platform-reqs`, and `composer verify` on every push, so the automation is gated at pull-request time rather than only on the Monday run that uses it.
* Dependabot now tracks the `composer` ecosystem. The automation is only as trustworthy as the Pint, PHPStan, and PHPUnit versions gating it.

### Add

* Downstream base bump. After a base release publishes, the weekly job opens a pull request in `appwrite/appwrite` rewriting every `appwrite/base:<version>` reference in its `Dockerfile`, waits for that pull request's checks to conclude, merges it, and tags the merge commit `cl-{APP_VERSION_STABLE}-{n}` — reading the application version from `app/init/constants.php` and taking the next unused sub-version for it. Lives in `.github/scripts/src/Downstream`, driven by `bin/downstream.php`. The wait reads the downstream branch's required status-check contexts and holds until every one of them has concluded, rather than inferring completeness from whichever checks happen to be visible. Downstream CI expands a dynamic matrix into dozens of checks that register minutes apart, so a visible-checks heuristic can never tell a finished run from one that has not started; a declared required set can. A branch with no required contexts is refused outright, because `--admin` bypasses branch protection and an unverifiable merge would otherwise proceed. A release that merged but never got its tag is recovered on the next run, bounded to the downstream tip so a superseded merge is not resurrected. Requires a `DOWNSTREAM_TOKEN` secret with admin rights on the downstream repository, because `main` there requires an approving review and GitHub forbids self-approval; the merge bypasses that review requirement but never the checks.

### Fix

* The updater rewrote `PHP_*_VERSION` and left `PHP_*_COMMIT` / `PHP_*_CHECKSUM` at the superseded release. Protobuf failed loudly on the checksum, but the git-sourced extensions did not: the build fetched the old commit and shipped, say, brotli 0.20.0 in an image labelled 0.21.0. `Dockerfile::pins()` only ever located the version variable, so no companion reference was ever a candidate for replacement. Every dependency now carries its reference variable through the catalog, resolver, selector, and rewriter, and both move together or neither does.
* `git ls-remote --tags --refs` returns the *annotated tag object*, not the commit it points at — `refs/tags/6.3.0` on phpredis is `aa4302d`, while the commit is `df4fab2`. Resolving references from that output would have replaced correct commit pins with tag-object SHAs. The resolver now reads the peeled `^{}` entry when a tag is annotated and falls back to the object for lightweight tags.
* The release step could never succeed. `createDraft` asks GitHub for `generate_release_notes=true`, so the returned body is the automation's body *plus* the generated changelog — and both `assertDraft` and `validateDraft` then required the body to equal what was sent. Every run died at `Draft release <id> is unsafe` after tagging and drafting. Both checks now require the body to *open with* the automation markers, which is what the safety property actually depends on; `RecoverySelector::matches` already worked this way.
* `Draft release <id> is unsafe` named none of the six fields it compared, so diagnosing it needed the API and the source side by side. It now says which ones mismatched.
* Recovery treated *any* automation merge without a tag as an unfinished release, and `mergedPullRequests()` paginates the entire closed-PR history — so an abandoned release stayed recoverable forever. A merge whose release was deliberately dropped would be re-tagged and published on the next run, from a commit main had already moved past. An untagged automation merge is now recoverable only while it is still the tip of `main`; once main has moved on, the release was abandoned, not interrupted. The head lookup is lazy, so recovering an already-tagged release never depends on it.

* A reference that has drifted from its version is now corrected on the next run even when the version itself is unchanged, so a hand-edited or stale pin self-heals instead of persisting. Every reference is resolved from upstream unconditionally — the peeled commit for git, a fresh hash of the selected tarball for PECL — rather than carrying forward whatever the file already held. A pinned tag that upstream no longer publishes now fails the run instead of passing silently.

* Duplicate release builds — `build-and-push.yml` no longer triggers on `release: published`. Tag pushes already trigger it, so publishing a release rebuilt and repushed the same image a second time.
* The XDebug `container-structure-test` step ran `plexsystems/container-structure-test-action@v0.1.0` — a mutable tag two minor versions behind the production step directly above it, and the only action reference in the repo not pinned to a commit SHA. Both steps now pin the same `v0.3.0` commit.

## Version 1.4.5

### Fix

* Publish the production image instead of the XDebug variant. Every workflow built with `docker image build ... .` and no `--target`, and Docker defaults to the *last* stage — which is `xdebug`. So `appwrite/base` has shipped XDebug in the production image since the variant was introduced in 1.2.0, and Trivy and dive were measuring the wrong image too. All four build workflows now pass an explicit `--target`. The stage order cannot be fixed instead: `xdebug` is `FROM final`, so it must be declared after `final`, which necessarily makes it last — `--target` is the only reliable control.

### Add

* Publish the XDebug variant under `-xdebug` tags (`<sha>-xdebug`, `<tag>-xdebug`, per-arch and manifest). It was documented as a build target since 1.2.0 but never published, so consumers that want XDebug — such as Appwrite's `development` image, which supplies an ini expecting `xdebug.so` to already exist — now have a real image to pin.
* Wire `tests-xdebug.yaml` into the structure-test workflow. It had existed since 1.2.0 with no workflow consuming it.
* `tests.yaml` assertion that XDebug is absent. `tests.yaml` only ever asserted module *presence*, so the XDebug image satisfied it and CI stayed green while shipping the wrong image.

## Version 1.3.2

### Security

* Ship a hardened ImageMagick `policy.xml` in the final image to mitigate image-decompression-bomb DoS via the Appwrite storage/avatars preview pipeline. A crafted image (small on disk, huge dimensions) previously decoded unbounded, spilling ImageMagick's pixel cache to disk and filling the volume — killing MongoDB or the container. The policy caps `disk` (4GiB spill limit — the primary control; sized for a 150MP Q16-HDRI photo) plus generous `width`/`height` backstops (50KP, well above any real camera so legitimate high-res photos are never rejected), sets `memory`/`map`/`area`/`thread` limits, and disables coders/modules never used for previews (PS/EPS/PDF/XPS/MSL/MVG/HTTP/etc., plus SVG/SVGZ/MSVG via a `module` deny that also closes the SVG SSRF/XXE delegate route). Installed into ImageMagick's configure dir (discovered at build time); the build fails if the policy does not load. Added a `tests.yaml` assertion verifying the policy is active.

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
