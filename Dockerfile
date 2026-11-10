# 1. frontend
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# kartes atslēga tiek iebūvēta JS failos
ARG VITE_MAPTILER_KEY=
ENV VITE_MAPTILER_KEY=$VITE_MAPTILER_KEY
RUN npm run build

# 2. PHP bibliotēkas
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# 3. gatavais attēls — PHP 8.4 + Apache
FROM php:8.4-apache
WORKDIR /var/www/html

RUN docker-php-ext-install opcache \
    && a2enmod rewrite \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs database/data \
    && DB_DATABASE=:memory: php artisan package:discover \
    && chown -R www-data:www-data storage bootstrap/cache database public/storage

COPY docker/entrypoint.sh /usr/local/bin/bookloop-start
# Windows git var pielikt \r rindu beigās
RUN sed -i 's/\r$//' /usr/local/bin/bookloop-start && chmod +x /usr/local/bin/bookloop-start

EXPOSE 80
CMD ["bookloop-start"]
