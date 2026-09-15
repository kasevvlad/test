FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev zip libonig-dev libpq-dev \
 && docker-php-ext-install intl pdo pdo_pgsql zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -sS https://get.symfony.com/cli/installer | bash \
 && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

CMD ["symfony", "serve", "--no-tls", "--port=8000", "--allow-http", "--allow-all-ip"]
