FROM php:8.2-apache

# Install sistem yang diperlukan
RUN apt-get update && apt-get install -y \
    libssl-dev \
    unzip \
    git \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/

# Copy semua fail projek
COPY . .

# Tukar permission supaya tak kena 'Forbidden'
RUN chown -R www-data:www-data /var/www/html/

# Composer install (kita letak --ignore-platform-reqs untuk elak error version)
# Ganti baris RUN composer install... dengan ni:
# Buang lock file lama dan install semula
RUN rm -f composer.lock && composer install --no-dev --optimize-autoloader
EXPOSE 80
