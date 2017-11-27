FROM php:fpm-alpine

RUN apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS; \
  pecl install -o -f redis; \
  docker-php-ext-enable redis; \
  apk del .phpize-deps
