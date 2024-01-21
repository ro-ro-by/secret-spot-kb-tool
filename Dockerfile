FROM composer:2.6.6 AS build
WORKDIR /app

COPY composer.json .
COPY composer.lock .
RUN composer install --no-dev --no-scripts --ignore-platform-reqs

COPY . .
RUN composer dumpautoload --optimize

FROM php:8.2-cli AS runtime

RUN apt-get -y update

RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

COPY --from=node:slim /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node:slim /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

FROM runtime
WORKDIR /app

# global requirements for OSM features
RUN npm install -g osmtogeojson

COPY --from=build /app .
