# Stage 1: Build dependencies
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    git \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libmagickwand-dev \
    cron \
    bsdmainutils \
    procps \
    zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        intl \
        gd

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

WORKDIR /var/www

COPY ./laravel /var/www

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader


RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Copy cron job
COPY ./docker/laravel-cron /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron
# Create log file
RUN touch /var/www/cron.log && chown www-data:www-data /var/www/cron.log
# CMD ["sh", "-c", "php /var/www/artisan schedule:work & php-fpm -F"]

CMD ["sh", "-c", "cron & php-fpm -F"]
