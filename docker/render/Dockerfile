# Imagem de HOMOLOGAÇÃO no Render (ver docs/adr/0001-hospedagem-render.md).
# Diferente da imagem de desenvolvimento (docker/app/Dockerfile): código copiado para dentro da imagem,
# dependências sem pacotes de dev, assets compilados e processos rodando como usuário sem privilégios.

FROM node:22-bookworm-slim AS node
FROM composer:2 AS composer

FROM php:8.3-fpm-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor gettext-base unzip \
        libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip gd bcmath pcntl opcache exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---------- build: dependências PHP + assets ----------
FROM base AS build
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && npm run build \
    && rm -rf node_modules

# ---------- imagem final ----------
FROM base
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/render/php.ini /usr/local/etc/php/conf.d/zz-render.ini
COPY docker/render/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-render.conf
COPY docker/render/nginx.conf.template /etc/nginx/templates/app.conf.template
COPY docker/render/supervisord.conf /etc/supervisor/render.conf
COPY docker/render/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app /var/www/html

RUN chmod +x /usr/local/bin/entrypoint \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /var/lib/nginx /var/log/nginx /etc/nginx/conf.d \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /etc/nginx/conf.d

USER www-data
ENV PORT=10000 TZ=America/Sao_Paulo
EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/entrypoint"]
