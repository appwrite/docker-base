# TODO

- [add dive analysis to project](https://github.com/marketplace/actions/dive-action)
- audit system packages in Dockerfile -> final
- can we get pre-compiled extensions *.so
- can we merge checkout, login, setup qemu, setup buildx in build-and-push.yml
- capture build logs via ` | tee "build-$(date +%s).log"`
- changelog aligning with appwrite/appwrite
- DOCKER_BUILDKIT=1 + buildx to parallel build the PHP extensions
- install gd and run stage should be separate
- use Swoole base image
- xdebug as separate image `appwrite/base-xdebug`
