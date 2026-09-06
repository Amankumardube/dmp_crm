FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql pdo_pgsql

RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
