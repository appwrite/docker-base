ARG BASE_IMAGE="php:8.5.3-cli-alpine3.23"
ARG PHP_BUILD_DATE="20250925"

FROM $BASE_IMAGE AS compile

ENV PHP_REDIS_VERSION="6.3.0" \
    PHP_SWOOLE_VERSION="6.2.0" \
    PHP_IMAGICK_VERSION="3.8.1" \
    PHP_MONGODB_VERSION="2.2.1" \
    PHP_YAML_VERSION="2.3.0" \
    PHP_MAXMINDDB_VERSION="v1.13.1" \
    PHP_SCRYPT_VERSION="2.0.1" \
    PHP_ZSTD_VERSION="0.15.2" \
    PHP_BROTLI_VERSION="0.18.3" \
    PHP_SNAPPY_VERSION="0.2.3" \
    PHP_LZ4_VERSION="0.6.0" \
    PHP_XDEBUG_VERSION="3.5.1" \
    PHP_OPENTELEMETRY_VERSION="1.2.1" \
    PHP_PROTOBUF_VERSION="5.34.0"

RUN \
  apk update && \
  apk upgrade && \
  apk add --no-cache --virtual .deps \
    && apk add --no-cache \
    autoconf \
    automake \
    brotli-dev \
    certbot \
    curl-dev \
    docker-cli \
    docker-cli-compose \
    g++ \
    gcc \
    git \
    imagemagick \
    imagemagick-dev \
    imagemagick-heic \
    jpeg-dev \
    libavif \
    libgomp \
    libheif \
    libjpeg-turbo-dev \
    libjxl-dev \
    libmaxminddb-dev \
    libpng-dev \
    libstdc++ \
    libwebp \
    linux-headers \
    lz4-dev \
    make \
    openssl-dev \
    postgresql-dev \
    rsync \
    yaml-dev \
    zip \
    zlib-dev \
    zstd-dev

# compile from source instals

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

## Maxminddb Extension
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

# PHP PECL installs

FROM compile AS opentelemetry
RUN pecl install opentelemetry-${PHP_OPENTELEMETRY_VERSION}

FROM compile AS protobuf
RUN pecl install protobuf-${PHP_PROTOBUF_VERSION}

# docker-php-extensions installs

FROM compile AS gd
RUN docker-php-ext-install gd

FROM compile AS sockets
RUN docker-php-ext-install sockets

FROM compile AS pdo_mysql
RUN docker-php-ext-install pdo pdo_mysql

FROM compile AS pdo_pgsql
RUN docker-php-ext-install pdo pdo_pgsql

FROM compile AS intl
RUN docker-php-ext-install intl

FROM $BASE_IMAGE AS final

# PAss in ARGS to use as label values and path components

ARG BASE_IMAGE
ARG PHP_BUILD_DATE

LABEL base_image=$BASE_IMAGE
LABEL maintainer="team@appwrite.io"
LABEL php_build_date=$PHP_BUILD_DATE

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
  echo $TZ > /etc/timezone

RUN \
  apk update && \
  apk upgrade && \
  apk add --no-cache --virtual .deps \
    && apk add --no-cache \
    autoconf \
    automake \
    brotli-dev \
    certbot \
    curl-dev \
    docker-cli \
    docker-cli-compose \
    g++ \
    gcc \
    git \
    imagemagick \
    imagemagick-dev \
    imagemagick-heic \
    jpeg-dev \
    libavif \
    libgomp \
    libheif \
    libjpeg-turbo-dev \
    libjxl-dev \
    libmaxminddb-dev \
    libpng-dev \
    libstdc++ \
    libwebp \
    linux-headers \
    lz4-dev \
    make \
    openssl-dev \
    postgresql-dev \
    rsync \
    yaml-dev \
    zip \
    zstd-dev \
  && apk del .deps \
  && rm -rf /var/cache/apk/*

WORKDIR /usr/src/code

COPY --from=brotli /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/brotli.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=gd /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/gd.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=imagick /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/imagick.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=intl /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/intl.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=lz4 /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/lz4.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=maxmind /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/maxminddb.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=mongodb /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/mongodb.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=opentelemetry /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/opentelemetry.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=pdo_mysql /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/pdo_mysql.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=pdo_pgsql /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/pdo_pgsql.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=protobuf /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/protobuf.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=redis /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/redis.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=scrypt /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/scrypt.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=snappy /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/snappy.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=sockets /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/sockets.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=swoole /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/swoole.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=xdebug /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=yaml /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/yaml.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/
COPY --from=zstd /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/zstd.so /usr/local/lib/php/extensions/no-debug-non-zts-$PHP_BUILD_DATE/

# Enable Extensions
RUN docker-php-ext-enable \
  brotli \
  gd \
  imagick \
  lz4 \
  maxminddb \
  mongodb \
  opentelemetry \
  protobuf \
  redis \
  scrypt \
  snappy \
  swoole \
  yaml \
  zstd

EXPOSE 80

CMD [ "tail", "-f", "/dev/null" ]
