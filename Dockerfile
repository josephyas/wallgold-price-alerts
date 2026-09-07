ARG REGISTRY=docker.arvancloud.ir

FROM ${REGISTRY}/library/composer:2 AS composer

FROM ${REGISTRY}/library/alpine:3.22

ARG APK_MIRROR=https://mirror.arvancloud.ir/alpine
ARG COMPOSER_MIRROR=

# PHP 8.4 and every extension the application needs come as binary packages
# from the configured Alpine mirror, so the image builds in seconds without
# compiling anything.
RUN sed -i "s#https://dl-cdn.alpinelinux.org/alpine#${APK_MIRROR}#g" /etc/apk/repositories \
    && apk add --no-cache \
        php84 php84-opcache php84-openssl php84-curl php84-mbstring php84-tokenizer \
        php84-xml php84-dom php84-xmlwriter php84-simplexml php84-session php84-fileinfo \
        php84-ctype php84-phar php84-iconv php84-zip php84-pdo_mysql php84-pdo_sqlite \
        php84-bcmath php84-intl php84-pcntl php84-posix php84-pecl-redis \
        tzdata \
    && ln -sf /usr/bin/php84 /usr/bin/php \
    && adduser -D -u 1000 app \
    && mkdir -p /app \
    && chown app:app /app

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /etc/php84/conf.d/99-app.ini

USER app
WORKDIR /app
ENV COMPOSER_HOME=/tmp/composer

COPY --chown=app:app composer.json composer.lock ./
RUN if [ -n "$COMPOSER_MIRROR" ]; then composer config -g repos.packagist composer "$COMPOSER_MIRROR"; fi \
    && composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress \
    && composer clear-cache

COPY --chown=app:app . .
RUN composer dump-autoload --optimize --no-interaction

ENTRYPOINT ["docker/entrypoint.sh"]
# --no-reload lets the built-in server honour PHP_CLI_SERVER_WORKERS.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
