FROM composer:latest as dependencies
WORKDIR /deps
COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist \
    ;

FROM php:8.0.10-cli-alpine3.13 as env
RUN docker-php-ext-install \
    pcntl \
    sockets
ENV RESOLVE_STATSD_HOST=true
WORKDIR /app
COPY --from=dependencies /deps/vendor ./vendor
COPY . .
CMD php server.php
