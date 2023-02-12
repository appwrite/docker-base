FROM php:8.0.18-cli-alpine3.15 as compile

ARG DEBUG=false
ENV DEBUG=$DEBUG

ENV PHP_REDIS_VERSION=5.3.7 \
    PHP_MONGODB_VERSION=1.13.0 \
    PHP_SWOOLE_VERSION=v4.8.10 \
    PHP_IMAGICK_VERSION=3.7.0 \
    PHP_YAML_VERSION=2.2.2 \
    PHP_MAXMINDDB_VERSION=v1.11.0 \
    PHP_ZSTD_VERSION="4504e4186e79b197cfcb75d4d09aa47ef7d92fe9"

RUN \
  apk add --no-cache --virtual .deps \
  make \
  automake \
  autoconf \
  gcc \
  g++ \
  git \
  zlib-dev \
  brotli-dev \
  openssl-dev \
  yaml-dev \
  imagemagick \
  imagemagick-dev \
  libmaxminddb-dev \
  zstd-dev

RUN docker-php-ext-install sockets

FROM compile AS redis
RUN \
  # Redis Extension
  git clone --depth 1 --branch $PHP_REDIS_VERSION https://github.com/phpredis/phpredis.git && \
  cd phpredis && \
  phpize && \
  ./configure && \
  make && make install

## Swoole Extension
FROM compile AS swoole
RUN \
  git clone --depth 1 --branch $PHP_SWOOLE_VERSION https://github.com/swoole/swoole-src.git && \
  cd swoole-src && \
  phpize && \
  ./configure --enable-sockets --enable-http2 --enable-openssl && \
  make && make install && \
  cd ..

## Swoole Debugger setup
RUN if [ "$DEBUG" == "true" ]; then \
    cd /tmp && \
    apk add boost-dev && \
    git clone --depth 1 https://github.com/swoole/yasd && \
    cd yasd && \
    phpize && \
    ./configure && \
    make && make install && \
    cd ..;\
  fi

## Imagick Extension
FROM compile AS imagick
RUN \
  git clone --depth 1 --branch $PHP_IMAGICK_VERSION https://github.com/imagick/imagick && \
  cd imagick && \
  phpize && \
  ./configure && \
  make && make install

## YAML Extension
FROM compile AS yaml
RUN \
  git clone --depth 1 --branch $PHP_YAML_VERSION https://github.com/php/pecl-file_formats-yaml && \
  cd pecl-file_formats-yaml && \
  phpize && \
  ./configure && \
  make && make install

## Maxminddb extension
FROM compile AS maxmind
RUN \
  git clone --depth 1 --branch $PHP_MAXMINDDB_VERSION https://github.com/maxmind/MaxMind-DB-Reader-php.git && \
  cd MaxMind-DB-Reader-php && \
  cd ext && \
  phpize && \
  ./configure && \
  make && make install

# Mongodb Extension
FROM compile as mongodb
RUN \
  git clone --depth 1 --branch $PHP_MONGODB_VERSION https://github.com/mongodb/mongo-php-driver.git && \
  cd mongo-php-driver && \
  git submodule update --init && \
  phpize && \
  ./configure && \
  make && make install

# Zstd Compression
FROM compile as zstd
RUN git clone --recursive -n https://github.com/kjdev/php-ext-zstd.git \
  && cd php-ext-zstd \
  && git checkout $PHP_ZSTD_VERSION \
  && phpize \
  && ./configure --with-libzstd \
  && make && make install


# Rust Extensions Compile Image
FROM php:8.0.18-cli as rust_compile

RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y

ENV PATH=/root/.cargo/bin:$PATH

RUN apt-get update && apt-get install musl-tools build-essential clang-11 git -y
RUN rustup target add $(uname -m)-unknown-linux-musl

# Install ZigBuild for easier cross-compilation
RUN curl https://ziglang.org/builds/zig-linux-$(uname -m)-0.10.0-dev.2674+d980c6a38.tar.xz --output /tmp/zig.tar.xz
RUN tar -xf /tmp/zig.tar.xz -C /tmp/ && cp -r /tmp/zig-linux-$(uname -m)-0.10.0-dev.2674+d980c6a38 /tmp/zig/
ENV PATH=/tmp/zig:$PATH
RUN cargo install cargo-zigbuild
ENV RUSTFLAGS="-C target-feature=-crt-static"

FROM rust_compile as scrypt

WORKDIR /usr/local/lib/php/extensions/

RUN \
  git clone --depth 1 https://github.com/appwrite/php-scrypt.git && \
  cd php-scrypt && \
  cargo zigbuild --workspace --all-targets --target $(uname -m)-unknown-linux-musl --release && \
  mv target/$(uname -m)-unknown-linux-musl/release/libphp_scrypt.so target/libphp_scrypt.so

FROM php:8.0.18-cli-alpine3.15 as final

LABEL maintainer="team@appwrite.io"

ENV DOCKER_CONFIG=${DOCKER_CONFIG:-$HOME/.docker}
ENV DOCKER_COMPOSE_VERSION=v2.5.0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN \
  apk update \
  && apk add --no-cache --virtual .deps \
  make \
  automake \
  autoconf \
  gcc \
  g++ \
  curl-dev \
  && apk add --no-cache \
  libstdc++ \
  certbot \
  brotli-dev \
  yaml-dev \
  imagemagick \
  imagemagick-dev \
  libmaxminddb-dev \
  certbot \
  docker-cli \
  libgomp \
  && docker-php-ext-install sockets opcache pdo_mysql \
  && apk del .deps \
  && rm -rf /var/cache/apk/*

RUN \
  mkdir -p $DOCKER_CONFIG/cli-plugins \
  && ARCH=$(uname -m) && if [ $ARCH == "armv7l" ]; then ARCH="armv7"; fi \
  && curl -SL https://github.com/docker/compose/releases/download/$DOCKER_COMPOSE_VERSION/docker-compose-linux-$ARCH -o $DOCKER_CONFIG/cli-plugins/docker-compose \
  && chmod +x $DOCKER_CONFIG/cli-plugins/docker-compose

WORKDIR /usr/src/code

COPY --from=swoole /usr/local/lib/php/extensions/no-debug-non-zts-20200930/swoole.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/yasd.so* /usr/local/lib/php/extensions/no-debug-non-zts-20200930/
COPY --from=redis /usr/local/lib/php/extensions/no-debug-non-zts-20200930/redis.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/
COPY --from=imagick /usr/local/lib/php/extensions/no-debug-non-zts-20200930/imagick.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/
COPY --from=yaml /usr/local/lib/php/extensions/no-debug-non-zts-20200930/yaml.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/
COPY --from=maxmind /usr/local/lib/php/extensions/no-debug-non-zts-20200930/maxminddb.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/
COPY --from=mongodb /usr/local/lib/php/extensions/no-debug-non-zts-20200930/mongodb.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/
COPY --from=scrypt  /usr/local/lib/php/extensions/php-scrypt/target/libphp_scrypt.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/
COPY --from=zstd /usr/local/lib/php/extensions/no-debug-non-zts-20200930/zstd.so /usr/local/lib/php/extensions/no-debug-non-zts-20200930/

# Enable Extensions
RUN echo extension=swoole.so >> /usr/local/etc/php/conf.d/swoole.ini
RUN echo extension=redis.so >> /usr/local/etc/php/conf.d/redis.ini
RUN echo extension=imagick.so >> /usr/local/etc/php/conf.d/imagick.ini
RUN echo extension=yaml.so >> /usr/local/etc/php/conf.d/yaml.ini
RUN echo extension=maxminddb.so >> /usr/local/etc/php/conf.d/maxminddb.ini
RUN echo extension=libphp_scrypt.so >> /usr/local/etc/php/conf.d/libphp_scrypt.ini
RUN echo extension=zstd.so >> /usr/local/etc/php/conf.d/zstd.ini

EXPOSE 80

CMD [ "tail", "-f", "/dev/null" ]