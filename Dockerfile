FROM php:8.1-apache
COPY . /var/www/html/
RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config libssl-dev
RUN pecl install mongodb && docker-php-ext-enable mongodb
EXPOSE 80
