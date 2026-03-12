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

```shell
docker-buildx build --no-cache --tag appwrite/base:latest .
# exit code 0
```

## Scan

```shell
trivy image --format json --pkg-types  os,library --severity  CRITICAL,HIGH --output trivy-image-results.json appwrite/base:latest
# success is a zero exit code
```

## Test

```bash
container-structure-test test --config tests.yaml --image appwrite/base:latest
# PASS
CI=true dive appwrite/base:latest
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

Note: Build of the image and push to the registry shoudl be handle by automation.

```bash
docker push appwrite/base:latest | tee "push-$(date +%s).log"
```

## Find Us

* [GitHub](https://github.com/appwrite)
* [Discord](https://appwrite.io/discord)
* [Twitter](https://twitter.com/appwrite)

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
