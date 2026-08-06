FROM php:8.2-apache

# Install system dependencies and PHP extensions required by Shopware
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    libicu-dev libxml2-dev libsodium-dev git unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip intl opcache sodium

# Enable Apache mod_rewrite for Shopware routing
RUN a2enmod rewrite

# Set Apache DocumentRoot to Shopware public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Adjust permissions for WSL / Apache user
RUN chown -R www-data:www-data /var/www/html

# Add to Dockerfile
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini