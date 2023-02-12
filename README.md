# Docker Base

Appwrite base docker image with applications and extensions built and installed.

## Testing

We use [Container Structure Test](https://github.com/GoogleContainerTools/container-structure-test) to run test for the docker image. In order to run test first install Container strucutre test using the following command.

```bash
curl -LO https://storage.googleapis.com/container-structure-test/latest/container-structure-test-linux-amd64 && chmod +x container-structure-test-linux-amd64 && sudo mv container-structure-test-linux-amd64 /usr/local/bin/container-structure-test
```

### Run Test

First build and tag the docker image and then run the test using the configuration file.

```bash
docker build -t appwrite-base-test .
container-structure-test test --config tests.yaml --image appwrite-base-test
```