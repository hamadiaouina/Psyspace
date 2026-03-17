FROM php:8.2-apache

RUN apt-get update \
  && docker-php-ext-install mysqli pdo pdo_mysql \
  && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html