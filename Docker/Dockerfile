<<<<<<< HEAD
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
 && a2enmod rewrite

COPY . /var/www/html/

=======
FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
 && a2enmod rewrite

COPY . /var/www/html/

>>>>>>> d6e3f91b78916b75d44a76b7efb2a322ca1649f3
RUN chown -R www-data:www-data /var/www/html