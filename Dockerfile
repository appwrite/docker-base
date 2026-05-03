# Pin php:8.5-alpine by multi-arch index digest. Bump with:
#   docker buildx imagetools inspect php:8.5-alpine | head -2
ARG BASE_IMAGE="php:8.5-alpine@sha256:dccc3abcf3d37a6bb081477a66ed4344716784a6ef5107625ae6ba9ec52df778"

FROM $BASE_IMAGE AS compile

ENV \
    PHP_BROTLI_VERSION="0.18.3" \
    PHP_IMAGICK_VERSION="3.8.1" \
    PHP_LZ4_VERSION="0.6.0" \
    PHP_MAXMINDDB_VERSION="v1.13.1" \
    PHP_MONGODB_VERSION="2.2.1" \
    PHP_PROTOBUF_VERSION="5.34.0" \
    PHP_REDIS_VERSION="6.3.0" \
    PHP_SCRYPT_VERSION="2.0.1" \
    PHP_SNAPPY_VERSION="0.2.3" \
    PHP_SWOOLE_VERSION="v6.2.0" \
    PHP_YAML_VERSION="2.3.0" \
    PHP_ZSTD_VERSION="0.15.2"

RUN \
  apk update && \
  apk add --no-cache \
    autoconf \
    automake \
    brotli-dev \
    c-ares-dev \
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
  git clone --depth 1 --branch $PHP_REDIS_VERSION https://github.com/phpredis/phpredis.git && \
  cd phpredis && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/redis.so /artifacts/ && \
  strip /artifacts/redis.so

FROM compile AS imagick
RUN \
  git clone --depth 1 --branch $PHP_IMAGICK_VERSION https://github.com/imagick/imagick && \
  cd imagick && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/imagick.so /artifacts/ && \
  strip /artifacts/imagick.so

FROM compile AS yaml
RUN \
  git clone --depth 1 --branch $PHP_YAML_VERSION https://github.com/php/pecl-file_formats-yaml && \
  cd pecl-file_formats-yaml && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/yaml.so /artifacts/ && \
  strip /artifacts/yaml.so

FROM compile AS maxmind
RUN \
  git clone --depth 1 --branch $PHP_MAXMINDDB_VERSION https://github.com/maxmind/MaxMind-DB-Reader-php.git && \
  cd MaxMind-DB-Reader-php && \
  cd ext && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/maxminddb.so /artifacts/ && \
  strip /artifacts/maxminddb.so

FROM compile AS mongodb
RUN \
  git clone --depth 1 --branch $PHP_MONGODB_VERSION https://github.com/mongodb/mongo-php-driver.git && \
  cd mongo-php-driver && \
  git submodule update --init && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/mongodb.so /artifacts/ && \
  strip /artifacts/mongodb.so

FROM compile AS zstd
RUN \
  git clone --recursive https://github.com/kjdev/php-ext-zstd.git && \
  cd php-ext-zstd && \
  git reset --hard $PHP_ZSTD_VERSION && \
  phpize && \
  ./configure --with-libzstd && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/zstd.so /artifacts/ && \
  strip /artifacts/zstd.so

FROM compile AS brotli
RUN \
  git clone https://github.com/kjdev/php-ext-brotli.git && \
  cd php-ext-brotli && \
  git reset --hard $PHP_BROTLI_VERSION && \
  phpize && \
  ./configure --with-libbrotli && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/brotli.so /artifacts/ && \
  strip /artifacts/brotli.so

FROM compile AS lz4
RUN \
  git clone --recursive https://github.com/kjdev/php-ext-lz4.git && \
  cd php-ext-lz4 && \
  git reset --hard $PHP_LZ4_VERSION && \
  phpize && \
  ./configure --with-lz4-includedir=/usr && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/lz4.so /artifacts/ && \
  strip /artifacts/lz4.so

FROM compile AS snappy
RUN \
  git clone --recursive https://github.com/kjdev/php-ext-snappy.git && \
  cd php-ext-snappy && \
  git reset --hard $PHP_SNAPPY_VERSION && \
  phpize && \
  ./configure && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/snappy.so /artifacts/ && \
  strip /artifacts/snappy.so

FROM compile AS scrypt
RUN \
  git clone https://github.com/DomBlack/php-scrypt.git && \
  cd php-scrypt && \
  git reset --hard $PHP_SCRYPT_VERSION && \
  phpize && \
  ./configure --enable-scrypt && \
  make -j"$(nproc)" && make install && \
  cp $(php-config --extension-dir)/scrypt.so /artifacts/ && \
  strip /artifacts/scrypt.so

FROM compile AS protobuf
RUN MAKEFLAGS="-j$(nproc)" pecl install protobuf-${PHP_PROTOBUF_VERSION} && \
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
  git clone --depth 1 --branch $PHP_SWOOLE_VERSION https://github.com/swoole/swoole-src.git && \
  cd swoole-src && \
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

ENV PHP_XDEBUG_VERSION="3.5.1"

RUN \
  git clone --depth 1 --branch $PHP_XDEBUG_VERSION https://github.com/xdebug/xdebug && \
  cd xdebug && \
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
