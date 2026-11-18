# syntax=docker/dockerfile:1
#
# UN SEUL SERVICE : Laravel (API) + interface React servie par le même Apache.
# Le contexte de build est la racine du dépôt (frontend/ et backend/ côte à côte).

# ---------- Stage 1 : interface React ----------
FROM node:22-alpine AS frontend
WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
# frontend/.env.production fixe VITE_API_URL=/api (même origine que l'API : aucun CORS).
RUN npm run build

# ---------- Stage 2 : dépendances Composer ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
    --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ---------- Stage 3 : image d'exécution ----------
FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev libonig-dev unzip postgresql-client \
    && docker-php-ext-install pdo_pgsql pgsql zip intl mbstring opcache pcntl bcmath \
    && a2enmod rewrite headers deflate \
    && rm -rf /var/lib/apt/lists/*

# Apache : document root = public/, port dynamique ($PORT fourni par Render)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!Listen 80!Listen ${PORT}!' /etc/apache2/ports.conf \
    && sed -ri -e 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf

COPY backend/docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY backend/docker/apache-cache.conf /etc/apache2/conf-available/app-cache.conf
RUN a2enconf app-cache

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY backend/ ./
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# L'interface construite est copiée dans public/ : index.html à la racine, fichiers hachés dans /assets.
COPY --from=frontend /frontend/dist/ ./public/

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

ENV PORT=8080 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8080
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
