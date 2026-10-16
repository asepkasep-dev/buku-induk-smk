FROM php:8.4-cli-bookworm AS dependencies

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install mbstring pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY resources ./resources
COPY routes ./routes
RUN mkdir -p storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs \
    bootstrap/cache
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM dependencies AS testing
COPY tests ./tests
COPY phpunit.xml ./phpunit.xml
RUN composer install --no-interaction --prefer-dist --optimize-autoloader
CMD ["php", "artisan", "test"]

FROM node:24-bookworm-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY --from=dependencies /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM php:8.4-apache-bookworm AS application

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libzip-dev \
    && docker-php-ext-install mbstring pdo_pgsql zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'upload_max_filesize=12M\npost_max_size=14M\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY --from=dependencies /var/www/html/composer.json ./composer.json
COPY --from=dependencies /var/www/html/app ./app
COPY --from=dependencies /var/www/html/bootstrap ./bootstrap
COPY --from=dependencies /var/www/html/config ./config
COPY --from=dependencies /var/www/html/database ./database
COPY public ./public
COPY resources ./resources
COPY --from=dependencies /var/www/html/routes ./routes
COPY --from=dependencies /var/www/html/storage ./storage
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY --from=dependencies /var/www/html/artisan ./artisan
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80


