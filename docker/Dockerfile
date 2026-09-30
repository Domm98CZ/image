# Debian distro packages instead of php:*-cli + PECL: builds without PECL/GitHub access, and matches
# the system libgd most Debian/Ubuntu production servers run.
FROM debian:trixie-slim

ARG PHP_VERSION=8.4
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        composer \
        fonts-dejavu-core \
        git \
        unzip \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-imagick \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        pngquant \
        # jpegtran/cjpeg (lossless JPEG re-encode), cwebp (lossy WebP re-encode), gifsicle (GIF re-encode).
        libjpeg-turbo-progs \
        webp \
        gifsicle \
        # ImageMagick reads AVIF out of the box but needs this AV1 encoder plugin to write it (split out since libheif 1.16).
        libheif-plugin-aomenc \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
