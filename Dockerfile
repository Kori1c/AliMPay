FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libsqlite3-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd bcmath pdo_sqlite zip

# Enable Apache modules and .htaccess support
RUN a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copy project files and Composer dependencies
COPY . /var/www/html/
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY docker/startup.sh /usr/local/bin/alimpay-startup

# Ensure runtime directories exist and are writable
RUN mkdir -p data logs qrcode config \
    && find data logs qrcode config -type d -exec chmod 770 {} + \
    && chown -R www-data:www-data /var/www/html \
    && chmod +x /usr/local/bin/alimpay-startup

EXPOSE 80

CMD ["alimpay-startup"]
