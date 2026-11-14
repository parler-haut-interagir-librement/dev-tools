# A PHP of the version matrix (config/matrix.json) for the tests of PHIL and Tilttek packages: `phil-dev php <version>`
# builds it from the image the matrix names, and runs a command in it with the project mounted at /app and the
# neighbouring directories its path repositories name mounted read-only beside it.
ARG PHP_IMAGE=php:8.5-cli
FROM ${PHP_IMAGE}

# The extensions the packages need, besides those the image builds in (openssl, sodium, pdo_sqlite): GD reads and
# writes JPEG, PNG, WebP and AVIF. git and unzip for Composer; the sqlite3 command for the tests that inspect a
# database file; lou_translate and the tables of liblouis for the integration tests of a braille translator.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip sqlite3 libicu-dev libonig-dev libzip-dev libpq-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libavif-dev liblouis-bin liblouis-data; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif; \
    docker-php-ext-install -j"$(nproc)" intl mbstring zip pdo_mysql pdo_pgsql gd; \
    rm -rf /var/lib/apt/lists/*

# A coverage driver for Infection, built from its source since the newer images have no PECL: pcov, or Xdebug in
# coverage mode on a PHP pcov does not build on yet (8.6 removed the INI_* macros it uses).
RUN set -eux; \
    git clone --depth 1 https://github.com/krakjoe/pcov.git /tmp/pcov; \
    cd /tmp/pcov; \
    if phpize && ./configure --enable-pcov && make -j"$(nproc)" && make install; then \
        docker-php-ext-enable pcov; \
    else \
        git clone --depth 1 https://github.com/xdebug/xdebug.git /tmp/xdebug; \
        cd /tmp/xdebug; phpize; ./configure; make -j"$(nproc)"; make install; \
        docker-php-ext-enable xdebug; \
        echo 'xdebug.mode = coverage' > "$PHP_INI_DIR/conf.d/xdebug-coverage.ini"; \
    fi; \
    cd /; rm -rf /tmp/pcov /tmp/xdebug

# Every deprecation is reported, so that a deprecation of the next PHP fails the tests.
RUN { \
        echo 'error_reporting = E_ALL'; \
        echo 'display_errors = On'; \
        echo 'memory_limit = -1'; \
        echo 'zend.assertions = 1'; \
    } > "$PHP_INI_DIR/conf.d/phil-dev.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
