# Pin php:8.5-alpine by multi-arch index digest. Bump with:
#   docker buildx imagetools inspect php:8.5-alpine | head -2
ARG BASE_IMAGE="php:8.5-alpine@sha256:0554eb53778b5316f6b9a3447c9dfa3cf2141c0c02ff816c42cdc9aa240a34aa"

FROM $BASE_IMAGE AS compile

# Every source is fetched by immutable reference — a commit SHA for git, a
# tarball checksum for PECL — because a tag can be repointed at any time. The
# version beside each reference is the release it was resolved from; the
# weekly updater rewrites both together.
ENV \
    PHP_BROTLI_VERSION="0.21.0" \
    PHP_BROTLI_COMMIT="ced23f5b6f52ef58a3a96d4731db4bee40a82736" \
    PHP_IMAGICK_VERSION="3.8.1" \
    PHP_IMAGICK_COMMIT="70087bab33eab913e99ac77d64d04d1a2fd0b7b0" \
    PHP_LZ4_VERSION="0.7.1" \
    PHP_LZ4_COMMIT="065a57d8fe237924d74efa3baedf231b7f837c3a" \
    PHP_MAXMINDDB_VERSION="v1.13.1" \
    PHP_MAXMINDDB_COMMIT="2194f58d0f024ce923e685cdf92af3daf9951908" \
    PHP_MONGODB_VERSION="2.4.1" \
    PHP_MONGODB_COMMIT="3d7e69fd9ed9ed3893b5a3fcdc204c6864ef2241" \
    PHP_PROTOBUF_VERSION="5.36.0" \
    PHP_PROTOBUF_CHECKSUM="bbf710ddc3b7ff53acfc327a7c0644d3632590c152567ff57e5d23f11bb8eba7" \
    PHP_REDIS_VERSION="6.3.0" \
    PHP_REDIS_COMMIT="df4fab2de7fc327c54c94a13af2b9542e4fbd720" \
    PHP_SCRYPT_VERSION="2.0.2" \
    PHP_SCRYPT_COMMIT="5a14bc766423dac3f868792fa8c41f85f47263ec" \
    PHP_SNAPPY_VERSION="0.2.3" \
    PHP_SNAPPY_COMMIT="d31b77d63955dbbf1a302ca13c4795292f91d140" \
    PHP_SWOOLE_VERSION="v6.2.2" \
    PHP_SWOOLE_COMMIT="8e8c49915ca5f9dcb9ee654f9e336a9c88dd375e" \
    PHP_YAML_VERSION="2.3.0" \
    PHP_YAML_COMMIT="c1f0d8ba5ef3884846261bbdb91c2ab0b07db44c" \
    PHP_ZSTD_VERSION="0.18.0" \
    PHP_ZSTD_COMMIT="c2593a4ce2457b23e7fa7f81ddf0dd9bbdd89b47"

RUN \
  apk update && \
  apk upgrade --no-cache && \
  apk add --no-cache \
    autoconf \
    automake \
    brotli-dev \
    c-ares-dev \
    curl \
    curl-dev \
    g++ \
    gcc \
    git \
    freetype-dev \
    imagemagick-dev \
    icu-dev \
    libavif-dev \
    libjpeg-turbo-dev \
    libjxl-dev \
    libwebp-dev \
    libmaxminddb-dev \
    libpng-dev \
    linux-headers \
    lz4-dev \
    make \
    openssl-dev \
    postgresql-dev \
    yaml-dev \
    zlib-dev \
    zstd-dev && \
  mkdir -p /artifacts && \
  docker-php-ext-install -j"$(nproc)" sockets

# Each builder stage emits a stripped .so to /artifacts/<name>.so so the
# final image doesn't need to know PHP's module-API date directory.

FROM compile AS redis
RUN \
  git init redis && \
  cd redis && \
  git fetch --depth 1 https://github.com/phpredis/phpredis.git $PHP_REDIS_COMMIT && \
  git checkout FETCH_HEAD && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/redis.so /artifacts/ && \
  strip /artifacts/redis.so

FROM compile AS imagick
RUN \
  git init imagick && \
  cd imagick && \
  git fetch --depth 1 https://github.com/imagick/imagick $PHP_IMAGICK_COMMIT && \
  git checkout FETCH_HEAD && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/imagick.so /artifacts/ && \
  strip /artifacts/imagick.so

FROM compile AS yaml
RUN \
  git init yaml && \
  cd yaml && \
  git fetch --depth 1 https://github.com/php/pecl-file_formats-yaml $PHP_YAML_COMMIT && \
  git checkout FETCH_HEAD && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/yaml.so /artifacts/ && \
  strip /artifacts/yaml.so

FROM compile AS maxmind
RUN \
  git init maxminddb && \
  cd maxminddb && \
  git fetch --depth 1 https://github.com/maxmind/MaxMind-DB-Reader-php.git $PHP_MAXMINDDB_COMMIT && \
  git checkout FETCH_HEAD && \
  cd ext && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/maxminddb.so /artifacts/ && \
  strip /artifacts/maxminddb.so

FROM compile AS mongodb
RUN \
  git init mongodb && \
  cd mongodb && \
  git fetch --depth 1 https://github.com/mongodb/mongo-php-driver.git $PHP_MONGODB_COMMIT && \
  git checkout FETCH_HEAD && \
  git submodule update --init && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/mongodb.so /artifacts/ && \
  strip /artifacts/mongodb.so

FROM compile AS zstd
RUN \
  git init zstd && \
  cd zstd && \
  git fetch --depth 1 https://github.com/kjdev/php-ext-zstd.git $PHP_ZSTD_COMMIT && \
  git checkout FETCH_HEAD && \
  git submodule update --init --recursive && \
  phpize && \
  ./configure --with-libzstd && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/zstd.so /artifacts/ && \
  strip /artifacts/zstd.so

FROM compile AS brotli
RUN \
  git init brotli && \
  cd brotli && \
  git fetch --depth 1 https://github.com/kjdev/php-ext-brotli.git $PHP_BROTLI_COMMIT && \
  git checkout FETCH_HEAD && \
  phpize && \
  ./configure --with-libbrotli && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/brotli.so /artifacts/ && \
  strip /artifacts/brotli.so

FROM compile AS lz4
RUN \
  git init lz4 && \
  cd lz4 && \
  git fetch --depth 1 https://github.com/kjdev/php-ext-lz4.git $PHP_LZ4_COMMIT && \
  git checkout FETCH_HEAD && \
  git submodule update --init --recursive && \
  phpize && \
  ./configure --with-lz4-includedir=/usr && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/lz4.so /artifacts/ && \
  strip /artifacts/lz4.so

FROM compile AS snappy
RUN \
  git init snappy && \
  cd snappy && \
  git fetch --depth 1 https://github.com/kjdev/php-ext-snappy.git $PHP_SNAPPY_COMMIT && \
  git checkout FETCH_HEAD && \
  git submodule update --init --recursive && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/snappy.so /artifacts/ && \
  strip /artifacts/snappy.so

FROM compile AS scrypt
RUN \
  git init scrypt && \
  cd scrypt && \
  git fetch --depth 1 https://github.com/DomBlack/php-scrypt.git $PHP_SCRYPT_COMMIT && \
  git checkout FETCH_HEAD && \
  phpize && \
  ./configure --enable-scrypt && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/scrypt.so /artifacts/ && \
  strip /artifacts/scrypt.so

FROM compile AS protobuf
RUN curl -fsSL -o protobuf.tgz \
      https://pecl.php.net/get/protobuf-${PHP_PROTOBUF_VERSION}.tgz && \
    echo "${PHP_PROTOBUF_CHECKSUM}  protobuf.tgz" | sha256sum -c - && \
    MAKEFLAGS="-j$(nproc)" pecl install protobuf.tgz && \
    cp $(php-config --extension-dir)/protobuf.so /artifacts/ && \
    strip /artifacts/protobuf.so

FROM compile AS core-extensions
RUN docker-php-ext-configure gd \
      --with-avif \
      --with-freetype \
      --with-jpeg \
      --with-webp && \
    docker-php-ext-install -j"$(nproc)" gd intl pdo_mysql pdo_pgsql && \
    cp \
      $(php-config --extension-dir)/gd.so \
      $(php-config --extension-dir)/intl.so \
      $(php-config --extension-dir)/pdo_mysql.so \
      $(php-config --extension-dir)/pdo_pgsql.so \
      $(php-config --extension-dir)/sockets.so \
      /artifacts/ && \
    strip \
      /artifacts/gd.so \
      /artifacts/intl.so \
      /artifacts/pdo_mysql.so \
      /artifacts/pdo_pgsql.so \
      /artifacts/sockets.so

# Built without --enable-swoole-stdext: stdext registers user opcode
# handlers, which makes opcache's JIT refuse to enable in downstream images.
FROM compile AS swoole
RUN \
  git init swoole && \
  cd swoole && \
  git fetch --depth 1 https://github.com/swoole/swoole-src.git $PHP_SWOOLE_COMMIT && \
  git checkout FETCH_HEAD && \
  phpize && \
  ./configure \
    --enable-brotli \
    --enable-cares \
    --enable-mysqlnd \
    --enable-openssl \
    --enable-sockets \
    --enable-swoole-curl \
    --enable-swoole-pgsql \
    --enable-zstd \
    --with-openssl-dir=/usr && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/swoole.so /artifacts/ && \
  strip /artifacts/swoole.so

FROM $BASE_IMAGE AS final

ARG BASE_IMAGE

LABEL base_image=$BASE_IMAGE
LABEL maintainer="team@appwrite.io"

RUN apk update && \
  apk upgrade --no-cache && \
  apk add --no-cache \
    brotli \
    c-ares \
    certbot \
    freetype \
    docker-cli \
    docker-cli-compose \
    git \
    icu-libs \
    imagemagick \
    imagemagick-heic \
    libavif \
    libgomp \
    libheif \
    libjpeg-turbo \
    libjxl \
    libmaxminddb \
    libpng \
    libpq \
    libstdc++ \
    libwebp \
    lz4-libs \
    rsync \
    yaml \
    zip \
    zstd-libs \
  && rm -rf /var/cache/apk/*

# Hardened ImageMagick policy — bounds per-decode width/height/disk so a crafted
# "image bomb" (small on disk, enormous when decoded) cannot exhaust memory or
# fill the disk and take down the container or its neighbours. Installed into
# ImageMagick's configure dir, discovered at build time so it survives package
# path changes; the build fails loudly if the policy does not load.
COPY policy.xml /tmp/policy.xml
RUN set -eux; \
    POLICY_DIR="$(identify -list configure | awk '/^CONFIGURE_PATH/ {print $2}' | cut -d: -f1)"; \
    cp /tmp/policy.xml "${POLICY_DIR%/}/policy.xml"; \
    rm /tmp/policy.xml; \
    identify -list policy | grep -q '50KP'

WORKDIR /usr/src/code

COPY --from=core-extensions /artifacts/ /tmp/exts/
COPY --from=brotli   /artifacts/ /tmp/exts/
COPY --from=imagick  /artifacts/ /tmp/exts/
COPY --from=lz4      /artifacts/ /tmp/exts/
COPY --from=maxmind  /artifacts/ /tmp/exts/
COPY --from=mongodb  /artifacts/ /tmp/exts/
COPY --from=protobuf /artifacts/ /tmp/exts/
COPY --from=redis    /artifacts/ /tmp/exts/
COPY --from=scrypt   /artifacts/ /tmp/exts/
COPY --from=snappy   /artifacts/ /tmp/exts/
COPY --from=swoole   /artifacts/ /tmp/exts/
COPY --from=yaml     /artifacts/ /tmp/exts/
COPY --from=zstd     /artifacts/ /tmp/exts/

RUN cp /tmp/exts/*.so $(php-config --extension-dir)/ && \
    rm -rf /tmp/exts && \
    docker-php-ext-enable \
      brotli \
      gd \
      imagick \
      intl \
      lz4 \
      maxminddb \
      mongodb \
      pdo_mysql \
      pdo_pgsql \
      protobuf \
      redis \
      scrypt \
      snappy \
      sockets \
      swoole \
      yaml \
      zstd

EXPOSE 80

CMD [ "tail", "-f", "/dev/null" ]

# XDebug variant — build with: docker build --target xdebug -t appwrite/base:XYZ-xdebug .
FROM compile AS xdebug-build

ENV \
    PHP_XDEBUG_VERSION="3.5.3" \
    PHP_XDEBUG_COMMIT="127bbcb980400752221cfaa54bdc1420e6ef3c12"

RUN \
  git init xdebug && \
  cd xdebug && \
  git fetch --depth 1 https://github.com/xdebug/xdebug $PHP_XDEBUG_COMMIT && \
  git checkout FETCH_HEAD && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/xdebug.so /artifacts/ && \
  strip /artifacts/xdebug.so

FROM final AS xdebug

COPY --from=xdebug-build /artifacts/xdebug.so /tmp/

RUN cp /tmp/xdebug.so $(php-config --extension-dir)/ && \
    rm /tmp/xdebug.so && \
    docker-php-ext-enable xdebug
