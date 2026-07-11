FROM php:8.2-apache

# 1. Pasang extension MongoDB & Unzip (untuk Composer)
RUN apt-get update && apt-get install -y libssl-dev unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# 2. Pasang Composer (untuk tarik fail dari composer.json kau)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Masukkan kod projek kau ke dalam server
WORKDIR /var/www/html/
COPY . /var/www/html/

# 4. Jalankan Composer install
RUN composer install --no-dev --optimize-autoloader

# 5. Betulkan akses kebenaran (permissions) fail
RUN chown -R www-data:www-data /var/www/html/
