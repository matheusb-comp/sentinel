ARG PHP_VERSION="8.5"

#
#
#
FROM serversideup/php:${PHP_VERSION}-frankenphp AS base
LABEL maintainer="matheusb-comp"

USER root

RUN set -eux; \
  install-php-extensions bcmath gd intl pcntl sockets;

RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends nodejs npm; \
  rm -rf /var/lib/apt/lists/*;

USER www-data

#
#
#
FROM base AS dev

ARG USER_ID
ARG GROUP_ID

USER root

RUN set -eux; \
  docker-php-serversideup-set-id www-data $USER_ID:$GROUP_ID; \
  docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID; \
  apt-get update; \
  apt-get install -y --no-install-recommends git; \
  rm -rf /var/lib/apt/lists/*;

# COPY --chmod=755 .docker/entrypoint.d/* /etc/entrypoint.d/

USER www-data

#
#
#
FROM base AS deps

COPY composer.* .

RUN composer install \
  --no-dev \
  --no-scripts \
  --no-interaction \
  --optimize-autoloader

#
#
#
FROM base AS prod

ARG ENV_FILE=".env.production"

ENV APP_ENV="production"
ENV APP_DEBUG="false"

COPY --chown=www-data:www-data --from=deps /var/www/html/vendor vendor

COPY --chown=www-data:www-data --exclude=.docker . .

# COPY --chmod=755 .docker/entrypoint.d/* /etc/entrypoint.d/

RUN set -eux; \
  if [ -e "${ENV_FILE}" ]; then \
    cp "${ENV_FILE}" ".env"; \
  elif [ ! -e ".env" ]; then \
    cp ".env.example" ".env"; \
  fi; \
  mkdir -p storage/logs; \
  composer dump-autoload \
    --no-dev \
    --optimize \
  ; \
  php artisan config:cache; \
  php artisan route:cache; \
  php artisan view:cache; \
  php artisan optimize;
