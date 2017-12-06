FROM php:fpm-alpine

RUN echo "http://dl-3.alpinelinux.org/alpine/edge/testing/" >> /etc/apk/repositories; \
  echo "http://dl-cdn.alpinelinux.org/alpine/edge/main" >> /etc/apk/repositories; \
  echo "http://dl-cdn.alpinelinux.org/alpine/edge/community" >> /etc/apk/repositories; \
  apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS git gearman-dev; \
  pecl install -o -f redis; \
  docker-php-ext-enable redis; \
# Gearman installer \
  cd /tmp; \
  git clone -b gearman-2.0.3 --depth=1 https://github.com/wcgallego/pecl-gearman.git; \
  cd pecl-gearman; \
  phpize; \
  ./configure; \
  make; \
  make install; \
  docker-php-ext-enable gearman; \
  rm -rf /tmp/pecl-gearman; \
# Cleanup \
  apk del .phpize-deps git; \
# Composer install \
  php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"; \
  php /tmp/composer-setup.php --install-dir=/usr/bin --filename=composer; \
  rm -f /tmp/composer-setup.php; \
  cd /src; \
  composer install
