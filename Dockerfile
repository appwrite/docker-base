ARG BASEIMAGE="php:8.4.8-cli-alpine3.22"

FROM $BASEIMAGE AS compile

ENV PHP_REDIS_VERSION="6.2.0" \
    PHP_SWOOLE_VERSION="v6.0.2" \
    PHP_IMAGICK_VERSION="3.8.0" \
    PHP_MONGODB_VERSION="1.20.1" \
    PHP_YAML_VERSION="2.2.4" \
    PHP_MAXMINDDB_VERSION="v1.12.0" \
    PHP_SCRYPT_VERSION="2.0.1" \
    PHP_ZSTD_VERSION="0.14.0" \
    PHP_BROTLI_VERSION="0.15.2" \
    PHP_SNAPPY_VERSION="0.2.2" \
    PHP_LZ4_VERSION="0.4.4" \
    PHP_XDEBUG_VERSION="3.4.3" \
    PHP_OPENTELEMETRY_VERSION="1.1.3" \
    PHP_PROTOBUF_VERSION="4.29.3"

RUN apk update && apk upgrade && apk add --no-cache --virtual .deps \
  linux-headers \
  make \
  automake \
  autoconf \
  gcc \
  g++ \
  git \
  zlib-dev \
  openssl-dev \
  yaml-dev \
  imagemagick \
  imagemagick-dev \
  libjpeg-turbo-dev \
  jpeg-dev \
  libpng-dev \
  libjxl-dev \
  libmaxminddb-dev \
  zstd-dev \
  brotli-dev \
  lz4-dev \
  curl-dev

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
  ./configure --enable-sockets --enable-http2 --enable-openssl --enable-swoole-curl && \
  make && make install && \
  cd ..

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
FROM compile AS mongodb
RUN \
  git clone --depth 1 --branch $PHP_MONGODB_VERSION https://github.com/mongodb/mongo-php-driver.git && \
  cd mongo-php-driver && \
  git submodule update --init && \
  phpize && \
  ./configure && \
  make && make install

# Zstd Compression
FROM compile AS zstd
RUN git clone --recursive -n https://github.com/kjdev/php-ext-zstd.git \
  && cd php-ext-zstd \
  && git checkout $PHP_ZSTD_VERSION \
  && phpize \
  && ./configure --with-libzstd \
  && make && make install

## Brotli Extension
FROM compile AS brotli
RUN git clone https://github.com/kjdev/php-ext-brotli.git \
  && cd php-ext-brotli \
  && git reset --hard $PHP_BROTLI_VERSION \
  && phpize \
  && ./configure --with-libbrotli \
  && make && make install

## LZ4 Extension
FROM compile AS lz4
RUN git clone --recursive https://github.com/kjdev/php-ext-lz4.git \
  && cd php-ext-lz4 \
  && git reset --hard $PHP_LZ4_VERSION \
  && phpize \
  && ./configure --with-lz4-includedir=/usr \
  && make && make install

## Snappy Extension
FROM compile AS snappy
RUN git clone --recursive https://github.com/kjdev/php-ext-snappy.git \
  && cd php-ext-snappy \
  && git reset --hard $PHP_SNAPPY_VERSION \
  && phpize \
  && ./configure \
  && make && make install

## Scrypt Extension
FROM compile AS scrypt
RUN git clone --depth 1 https://github.com/DomBlack/php-scrypt.git  \
  && cd php-scrypt  \
  && git reset --hard $PHP_SCRYPT_VERSION  \
  && phpize  \
  && ./configure --enable-scrypt  \
  && make && make install

## XDebug Extension
FROM compile AS xdebug
RUN \
  git clone --depth 1 --branch $PHP_XDEBUG_VERSION https://github.com/xdebug/xdebug && \
  cd xdebug && \
  phpize && \
  ./configure && \
  make && make install

FROM compile AS opentelemetry
RUN pecl install opentelemetry-${PHP_OPENTELEMETRY_VERSION}

FROM compile AS protobuf
RUN pecl install protobuf-${PHP_PROTOBUF_VERSION}

FROM compile AS gd
RUN docker-php-ext-install gd

FROM $BASEIMAGE AS final

LABEL maintainer="team@appwrite.io"

ENV DOCKER_CONFIG=${DOCKER_CONFIG:-$HOME/.docker}
ENV DOCKER_COMPOSE_VERSION="v2.33.1"

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN \
  apk update \
  && apk add --no-cache --virtual .deps \
  linux-headers \
  make \
  automake \
  autoconf \
  gcc \
  g++ \
  curl-dev \
  && apk add --no-cache \
  libstdc++ \
  rsync \
  brotli-dev \
  lz4-dev \
  yaml-dev \
  imagemagick \
  imagemagick-dev \
  libjpeg-turbo-dev \
  jpeg-dev \
  libjxl-dev \
  libavif \
  libheif \
  imagemagick-heic \
  zlib-dev \
  libpng-dev \
  libmaxminddb-dev \
  certbot \
  docker-cli \
  libgomp \
  git \
  zip \
  postgresql-dev \
  && docker-php-ext-install sockets pdo_mysql pdo_pgsql intl \
  && apk del .deps \
  && rm -rf /var/cache/apk/*

RUN mkdir -p $DOCKER_CONFIG/cli-plugins \
  && ARCH=$(uname -m) && if [ $ARCH == "armv7l" ]; then ARCH="armv7"; fi \
  && curl -SL https://github.com/docker/compose/releases/download/$DOCKER_COMPOSE_VERSION/docker-compose-linux-$ARCH -o $DOCKER_CONFIG/cli-plugins/docker-compose \
  && chmod +x $DOCKER_CONFIG/cli-plugins/docker-compose

WORKDIR /usr/src/code

COPY --from=swoole /usr/local/lib/php/extensions/no-debug-non-zts-20240924/swoole.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=redis /usr/local/lib/php/extensions/no-debug-non-zts-20240924/redis.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=imagick /usr/local/lib/php/extensions/no-debug-non-zts-20240924/imagick.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=yaml /usr/local/lib/php/extensions/no-debug-non-zts-20240924/yaml.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=maxmind /usr/local/lib/php/extensions/no-debug-non-zts-20240924/maxminddb.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=scrypt /usr/local/lib/php/extensions/no-debug-non-zts-20240924/scrypt.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=zstd /usr/local/lib/php/extensions/no-debug-non-zts-20240924/zstd.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=brotli /usr/local/lib/php/extensions/no-debug-non-zts-20240924/brotli.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=lz4 /usr/local/lib/php/extensions/no-debug-non-zts-20240924/lz4.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=snappy /usr/local/lib/php/extensions/no-debug-non-zts-20240924/snappy.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=xdebug /usr/local/lib/php/extensions/no-debug-non-zts-20240924/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=opentelemetry /usr/local/lib/php/extensions/no-debug-non-zts-20240924/opentelemetry.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=protobuf /usr/local/lib/php/extensions/no-debug-non-zts-20240924/protobuf.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=gd /usr/local/lib/php/extensions/no-debug-non-zts-20240924/gd.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/
COPY --from=mongodb /usr/local/lib/php/extensions/no-debug-non-zts-20240924/mongodb.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/

# Enable Extensions
RUN docker-php-ext-enable swoole redis imagick yaml maxminddb scrypt zstd brotli lz4 snappy opentelemetry protobuf gd

EXPOSE 80

CMD [ "tail", "-f", "/dev/null" ]
