# A PHP of the version matrix (config/matrix.json) for the tests of PHIL and Tilttek packages: `phil-dev php <version>`
# builds it from the image the matrix names, and runs a command in it with the project mounted at /app and the
# neighbouring directories its path repositories name mounted read-only beside it.
ARG PHP_IMAGE=php:8.5-cli

# liblouis, built from its source: the integration tests of a braille translator need 3.39.0 or later, the oldest
# release the translator accepts, and the Debian package of the base image is older. The source is a released tag,
# fetched alone and checked against the commit pinned here, like the coverage driver below; the build tools stay in
# this stage, and only what `make install` writes under /usr/local reaches the image.
FROM ${PHP_IMAGE} AS liblouis
ARG LIBLOUIS_TAG=v3.39.0
ARG LIBLOUIS_COMMIT=2e775aca674cd2910e272eed7736acceb4c2e4a2
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git ca-certificates automake libtool pkg-config texinfo; \
    git init -q /tmp/liblouis; \
    git -C /tmp/liblouis fetch -q --depth 1 https://github.com/liblouis/liblouis.git "refs/tags/$LIBLOUIS_TAG:refs/tags/$LIBLOUIS_TAG"; \
    test "$(git -C /tmp/liblouis rev-parse "$LIBLOUIS_TAG^{commit}")" = "$LIBLOUIS_COMMIT"; \
    git -C /tmp/liblouis -c advice.detachedHead=false checkout -q "$LIBLOUIS_COMMIT"; \
    cd /tmp/liblouis; \
    ./autogen.sh; \
    ./configure --prefix=/usr/local --disable-static; \
    make -j"$(nproc)"; \
    make install DESTDIR=/opt/liblouis

FROM ${PHP_IMAGE}

# The extensions the packages need, besides those the image builds in (openssl, sodium, pdo_sqlite): GD reads and
# writes JPEG, PNG, WebP and AVIF; pcntl for the tests that send a worker a signal. git and unzip for Composer; the
# sqlite3 command for the tests that inspect a database file. `phil-dev php` builds the image again when this file
# changes.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip sqlite3 libicu-dev libonig-dev libzip-dev libpq-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libavif-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif; \
    docker-php-ext-install -j"$(nproc)" intl mbstring zip pdo_mysql pdo_pgsql gd pcntl; \
    rm -rf /var/lib/apt/lists/*

# A coverage driver for Infection, built from its source since the newer images have no PECL: pcov, or Xdebug in
# coverage mode on a PHP pcov does not build on yet (8.6 removed the INI_* macros it uses). Each source is a released
# tag, fetched alone and checked against the commit pinned here, so that a moved tag or a new commit on a default
# branch never changes the image.
ARG PCOV_TAG=v1.0.9
ARG PCOV_COMMIT=a2f930e98c9f5380af82f218916427b3a61f6e81
ARG XDEBUG_TAG=3.6.0alpha1
ARG XDEBUG_COMMIT=653e0443683790877b99e4e7a8adedf84b8e298b
RUN set -eux; \
    fetch() { \
        git init -q "$1"; \
        git -C "$1" fetch -q --depth 1 "$2" "refs/tags/$3:refs/tags/$3"; \
        test "$(git -C "$1" rev-parse "$3^{commit}")" = "$4"; \
        git -C "$1" -c advice.detachedHead=false checkout -q "$4"; \
    }; \
    fetch /tmp/pcov https://github.com/krakjoe/pcov.git "$PCOV_TAG" "$PCOV_COMMIT"; \
    cd /tmp/pcov; \
    if phpize && ./configure --enable-pcov && make -j"$(nproc)" && make install; then \
        docker-php-ext-enable pcov; \
    else \
        fetch /tmp/xdebug https://github.com/xdebug/xdebug.git "$XDEBUG_TAG" "$XDEBUG_COMMIT"; \
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

# lou_translate, the other programs and the tables of liblouis, from the stage above. The build fails when the
# installed lou_translate reports a version older than 3.39.0 or the tables are missing.
COPY --from=liblouis /opt/liblouis/usr/local/ /usr/local/
RUN set -eux; \
    ldconfig; \
    version="$(lou_translate --version | sed -n '1s/^.* \([0-9][0-9.]*\)$/\1/p')"; \
    dpkg --compare-versions "$version" ge 3.39.0; \
    test -f /usr/local/share/liblouis/tables/en-ueb-g2.ctb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
