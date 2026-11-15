FROM php:8.4-fpm-alpine

# Install system dependencies & PHP extensions needed for Laravel
RUN apk add --no-cache \
    curl \
    git \
    bash \
    mysql-client \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]

