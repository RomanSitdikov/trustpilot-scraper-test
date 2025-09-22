FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    unzip git libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-dev --optimize-autoloader

ENTRYPOINT ["php", "cli.php"]