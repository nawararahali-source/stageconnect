FROM php:8.1-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql bcmath mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --retries=5 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/') === false ? 1 : 0);"

CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]