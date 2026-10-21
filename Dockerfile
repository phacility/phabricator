FROM php:8.4.27-fpm-alpine3.23 AS base

# Used for some file patchs in the image. MUST match the Docker image.
ENV PHPSHORT=84

LABEL maintainer="dkl@mozilla.com"

# From https://github.com/marco-c/risk-analysis-addon/releases
ENV RISK_ANALYSIS_VERSION=v0.6.0

# These are unlikely to change from version to version of the container
EXPOSE 9000
EXPOSE 9003
ENTRYPOINT ["/usr/local/bin/dumb-init", "--"]
CMD ["/app/entrypoint.sh", "start"]

ENV REPOSITORY_LOCAL_PATH=/repo
ENV TMPDIR=/tmp

USER root

# Runtime dependencies
RUN apk --no-cache --update add \
    composer \
    curl \
    freetype \
    g++ \
    git \
    libjpeg-turbo \
    libpng \
    libzip \
    make \
    mariadb-client \
    ncurses \
    patch \
    procps \
    py3-pip \
    py3-pygments \
    python3-dev

# Build PHP extensions. `zip` comes with PHP's source, so only APCu needs PECL,
# and pecl.php.net sometimes answers with an HTTP 500, so retry it.
RUN apk --no-cache add --virtual build-dependencies \
        $PHPIZE_DEPS \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        mariadb-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j "$(nproc)" \
        gd \
        mysqli \
        pcntl \
        zip \
    && for try in 1 2 3; do \
        pecl install apcu-5.1.27 && break; \
        [ "$try" -lt 3 ] && sleep 10 || exit 1; \
    done \
    && docker-php-ext-enable apcu \
    && apk del build-dependencies

RUN wget -O /usr/local/bin/dumb-init https://github.com/Yelp/dumb-init/releases/download/v1.2.1/dumb-init_1.2.1_amd64 \
    && chmod 755 /usr/local/bin/dumb-init

# The container does not log errors by default, so turn them on
RUN { \
        echo 'php_admin_flag[log_errors] = on'; \
        echo 'php_flag[display_errors] = off'; \
    } | tee /usr/local/etc/php-fpm.d/zz-log.conf

# Phabricator recommended settings (skipping these will result in setup warnings
# in the application).
RUN { \
        echo 'always_populate_raw_post_data=-1'; \
        echo 'post_max_size="32M"'; \
        echo 'session.upload_progress.enabled = Off'; \
    } | tee /usr/local/etc/php/conf.d/phabricator.ini

# Disable session upload progress for all PHPs, including the distro-shipped
# one (despite it not being used), to counter CVE-2026-109619.
RUN sed -i \
  's/^;session.upload_progress.enabled.*/session.upload_progress.enabled = Off/' \
  /etc/php${PHPSHORT}/php.ini \
  /usr/local/etc/php/php.ini-production \
  /usr/local/etc/php/php.ini-development

# add a non-privileged user for installing and running the application
RUN addgroup -g 10001 app \
    && adduser -D -u 10001 -G app -h /app -s /bin/sh app

RUN mkdir $REPOSITORY_LOCAL_PATH \
    && chown app:app $REPOSITORY_LOCAL_PATH

WORKDIR /app
USER app

# Install Phabricator and Arcanist specific code
COPY --chown=app bin phabricator/bin
COPY --chown=app conf phabricator/conf
COPY --chown=app externals phabricator/externals
COPY --chown=app resources phabricator/resources
COPY --chown=app scripts phabricator/scripts
COPY --chown=app src phabricator/src
COPY --chown=app support phabricator/support
COPY --chown=app webroot phabricator/webroot
RUN git clone https://github.com/mozilla-conduit/arcanist -b stable arcanist \
    && ./arcanist/support/xhpast/build-xhpast.php

# Install Python dependencies
COPY --chown=app requirements.txt ./
USER root
# Alpine's Python is marked as externally managed, so pip refuses to install
# into it without `--break-system-packages`. Nothing else in the image uses pip.
RUN pip install --break-system-packages --require-hashes -r requirements.txt

# Use ft as an alias of fetch.
RUN git config --system --add "alias.ft" "fetch"

USER app

# Install PHP dependencies
ENV COMPOSER_VENDOR_DIR=/app/phabricator/externals/extensions
COPY --chown=app composer.json composer.lock ./
RUN mkdir ${COMPOSER_VENDOR_DIR} \
    && composer install --no-dev

# Copy mercurial extension, and enable it.
COPY --chown=app hgext hgext
COPY --chown=app .hgrc /app/.hgrc

# Configure Phabricator to use moz-extensions library
RUN \
    mkdir /app/phabricator/conf/custom/ && \
    echo custom/moz-extensions > /app/phabricator/conf/local/ENVIRONMENT
COPY moz-extensions.conf.php /app/phabricator/conf/custom/

COPY --chown=app assert-git.sh entrypoint.sh LICENSE update_version_json.py wait-for-mysql.php ./
COPY --chown=app nginx/ nginx/

# Update version.json
RUN chmod +x /app/assert-git.sh /app/update_version_json.py /app/entrypoint.sh /app/wait-for-mysql.php \
    && /app/update_version_json.py

RUN { \
        echo '/**'; \
        echo '* @provides moz-risk-analysis-js'; \
        echo '* @do-not-minify'; \
        echo '*/'; \
    } | tee /app/phabricator/webroot/rsrc/js/MozillaRiskAnalysis.js
RUN curl -fsSL https://raw.githubusercontent.com/marco-c/risk-analysis-addon/${RISK_ANALYSIS_VERSION}/risk_analysis.js \
    >> /app/phabricator/webroot/rsrc/js/MozillaRiskAnalysis.js

FROM base AS production

USER root
RUN docker-php-ext-install -j "$(nproc)" opcache

# Install opcache recommended settings from
# https://secure.php.net/manual/en/opcache.installation.php
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=0'; \
    } | tee /usr/local/etc/php/conf.d/opcache.ini

USER app

# Using a wildcard so that the COPY still succeeds, even if version.json doesn't exist.
COPY --chown=app version.json* ./
COPY --chown=app moz-extensions moz-extensions
RUN chmod +x /app/moz-extensions/bin/*

RUN /app/assert-git.sh

FROM base AS development

USER root
# pecl.php.net sometimes answers with an HTTP 500, so retry.
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && for try in 1 2 3; do \
        pecl install xdebug-3.4.5 && break; \
        [ "$try" -lt 3 ] && sleep 10 || exit 1; \
    done \
    && docker-php-ext-enable xdebug

RUN { \
        echo '[xdebug]'; \
        echo 'xdebug.mode=debug'; \
        echo 'xdebug.start_with_request=yes'; \
        echo 'xdebug.client_host=172.17.0.1'; \
        echo 'xdebug.log="/tmp/xdebug.log"'; \
        echo ''; \
    } | tee /usr/local/etc/php/conf.d/xdebug.ini

USER app

RUN /app/assert-git.sh

FROM base AS test

USER root
RUN apk --update --no-cache add \
    bash \
    g++ \
    git \
    make \
    nodejs \
    npm

# Used by the `jshint` linters in `.arclint`.
RUN npm install --global jshint@2.13.6

USER app

# Used by `just phpstan`. `COMPOSER_VENDOR_DIR` points at Phabricator's own
# dependencies, so unset it to keep PHPStan out of them.
RUN mkdir /app/phpstan \
    && env -u COMPOSER_VENDOR_DIR composer require \
        --working-dir=/app/phpstan --no-interaction --no-progress \
        phpstan/phpstan:2.2.16

# The `phutil-library` linter analyzes arcanist too, and writes its symbol
# cache into `arcanist/src`. The `tools` Compose service runs as the host user,
# so build the cache here and let any user update it.
RUN php arcanist/support/lib/rebuild-map.php arcanist/src \
    && chmod a+w arcanist/src arcanist/src/.phutil_module_cache

# `arc unit` only runs inside a working copy, but needs none of its history.
RUN git init --quiet /app

COPY --chown=app .arcunit .arcunit
COPY --chown=app test-arcconfig .arcconfig
COPY --chown=app moz-extensions moz-extensions
RUN chmod +x /app/moz-extensions/bin/*

RUN /app/assert-git.sh
