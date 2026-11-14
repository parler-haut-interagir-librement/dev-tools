# The image of the benchmarks that measure a PHP extension: FrankenPHP on Debian, with the thread-safe (ZTS) PHP 8.5
# FrankenPHP runs, intl and GD, and PIE to install the extensions a benchmark compares with the pure PHP path.
#
# It is built on demand only — `phil-dev bench:worker --ext` builds it the first time, or again with --build — and
# never by the continuous integration of each commit: an extension written in Rust or C++ compiles for minutes. The
# image of resources/php stays the pure PHP path the tests run on.
#
#     PHIL_DEV_BENCH_EXT_PIE="vendor/extension vendor/other:^1.2" phil-dev bench:worker --ext --build
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1-php8.5
FROM ${FRANKENPHP_IMAGE}

# intl and GD, which the image has not: GD reads and writes JPEG, PNG, WebP and AVIF, as in resources/php.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libwebp-dev libavif-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif; \
    docker-php-ext-install -j"$(nproc)" intl gd; \
    rm -rf /var/lib/apt/lists/*

# PIE, and the extensions the build argument names, separated by spaces, each as PIE takes it: a Composer package name
# with an optional constraint. None by default, so that the image builds in a minute when no extension is compared.
COPY --from=ghcr.io/php/pie:bin /pie /usr/local/bin/pie
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ARG PIE_EXTENSIONS=""
RUN set -eux; \
    if [ -n "${PIE_EXTENSIONS}" ]; then \
        apt-get update; \
        apt-get install -y --no-install-recommends ${PHPIZE_DEPS}; \
        for extension in ${PIE_EXTENSIONS}; do pie install --no-interaction "${extension}"; done; \
        rm -rf /var/lib/apt/lists/*; \
    fi

# The settings of production, which a benchmark measures: assertions compiled out, OPcache on.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app
