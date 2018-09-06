FROM composer:latest as dependencies
WORKDIR /deps
COPY composer.json .
COPY composer.lock .
RUN composer install \
    --ignore-platform-reqs \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist \
    ;

FROM php:7.2.9-cli-alpine as env
RUN docker-php-ext-install \
    sockets
ENV RESOLVE_STATSD_HOST=true
WORKDIR /app
COPY . .
COPY --from=dependencies /deps/vendor ./vendor
CMD php server.php
