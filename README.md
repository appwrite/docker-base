# Docker Base

[![Build Status](https://img.shields.io/travis/com/appwrite/docker-base?style=flat-square)](https://travis-ci.com/appwrite/docker-base)
[![Discord](https://img.shields.io/discord/564160730845151244?label=discord&style=flat-square)](https://appwrite.io/discord)
[![Docker Pulls](https://img.shields.io/docker/pulls/appwrite/base?color=f02e65&style=flat-square)](https://hub.docker.com/r/appwrite/base)
[![Follow Appwrite on StackShare](https://img.shields.io/badge/follow%20on-stackshare-blue?style=flat-square)](https://stackshare.io/appwrite)
[![Twitter Account](https://img.shields.io/twitter/follow/appwrite?color=00acee&label=twitter&style=flat-square)](https://twitter.com/appwrite)

[Appwrite](https://appwrite.io) base docker image with applications and extensions built and installed.

## Getting Started

These instructions will cover usage information to help your run Appwrite's base docker container.

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

Typical building.

```shell
docker buildx build --tag appwrite/base:latest .
```

Multi-arch building.

```shell
docker buildx build --platform linux/amd64,linux/arm64/v8,linux/ppc64le --push --tag appwrite/base:latest .
```

## Scan

```shell
trivy image --format json --pkg-types  os,library --severity  CRITICAL,HIGH --output trivy-image-results.json appwrite/base:latest
```

## Test

```bash
container-structure-test test --config tests.yaml --image appwrite/base:latest
```

## Run

```shell
docker run appwrite/base:latest
```

## Push

```bash
docker push appwrite/base:latest
```

## Find Us

* [GitHub](https://github.com/appwrite)
* [Discord](https://appwrite.io/discord)
* [Twitter](https://twitter.com/appwrite)

## Copyright and license

The MIT License (MIT) [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
