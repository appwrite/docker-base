# Docker Base

[![Build Status](https://img.shields.io/travis/com/appwrite/docker-base?style=flat-square)](https://travis-ci.com/appwrite/docker-base)
[![Discord](https://img.shields.io/discord/564160730845151244?label=discord&style=flat-square)](https://appwrite.io/discord)
[![Docker Pulls](https://img.shields.io/docker/pulls/appwrite/base?color=f02e65&style=flat-square)](https://hub.docker.com/r/appwrite/base)
[![Follow Appwrite on StackShare](https://img.shields.io/badge/follow%20on-stackshare-blue?style=flat-square)](https://stackshare.io/appwrite)
[![Twitter Account](https://img.shields.io/twitter/follow/appwrite?color=00acee&label=twitter&style=flat-square)](https://twitter.com/appwrite)

[Appwrite](https://appwrite.io) base docker image with applications and extensions built and installed.

## Getting Started

This project contains Appwrite's PHP base container image.

### NOTE

* For example usage `latest` is stated in the commands. The Appwrite team recommends using pinned version releases outside of development.
* We use `Docker` but you may use any compatible container runtime in its place.

## Prerequisites

In order to run this container you'll need the Docker runtime installed.

**Docker**

* [Linux](https://docs.docker.com/linux/started/)
* [OS X](https://docs.docker.com/mac/started/)
* [Windows](https://docs.docker.com/windows/started)

* [Docker buildx](https://github.com/docker/buildx)

**Optional**

* [GoogleContainerTools/container-structure-test](https://github.com/GoogleContainerTools/container-structure-test) for testing
* [Trivy](https://trivy.dev/) for CVE scanning

## Build

`--target` is required. The XDebug variant derives from `final`, so it has to be
declared after it, which makes it the last stage — and Docker defaults to the
last stage. Omitting `--target` therefore builds XDebug, not production.

```shell
# Default (production) image
docker build --no-cache --target final --tag appwrite/base:latest .

# XDebug variant
docker build --no-cache --target xdebug --tag appwrite/base:latest-xdebug .
# exit code 0
```

## Scan

```shell
trivy image --format json --pkg-types  os,library --severity  CRITICAL,HIGH --output trivy-image-results.json appwrite/base:latest
# success is a zero exit code
```

## Test

```bash
# Production image
container-structure-test test --config tests.yaml --image appwrite/base:latest
# PASS

# XDebug variant
container-structure-test test --config tests-xdebug.yaml --image appwrite/base:latest-xdebug
# PASS
CI=true dive --config .dive-ci.yml appwrite/base:latest
# Results:
#   PASS: highestUserWastedPercent
#   PASS: highestWastedBytes
#   PASS: lowestEfficiency
# Result:PASS [Total:3] [Passed:3] [Failed:0] [Warn:0] [Skipped:0]
```

## Run

```shell
docker run appwrite/base:latest php -m
# ...
# yaml
# Zend OPcache
# zlib
# zstd
# 
# [Zend Modules]
# Zend OPcache
```

## Push

Pushing a built image to a repository should be handle by automation.

```bash
docker push appwrite/base:latest | tee "push-$(date +%s).log"
```

## Automation

Dependency updates and releases are automated. `.github/workflows/dependencies.yml`
runs every Monday (and on manual dispatch): it resolves the newest stable
same-major release for the PHP base digest and each pinned extension, rewrites
the pins in `Dockerfile`, opens a pull request, waits for that exact head's CI,
merges it, then tags, builds, and publishes the release. A run that dies between
merge and publish is resumed on the next run rather than duplicated.

The logic lives in `.github/scripts` as PHP and is exercised by its own suite.

```bash
composer install
composer verify
# pint, phpstan, phpunit, parity
```

`composer verify` also runs on every push via `.github/workflows/verify.yml`, and
gates the Monday job before it touches any dependency.

## Find Us

* [GitHub](https://github.com/appwrite)
* [Discord](https://appwrite.io/discord)
* [Twitter](https://twitter.com/appwrite)

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
