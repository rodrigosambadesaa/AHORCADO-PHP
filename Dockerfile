FROM php:8.3-apache

RUN a2enmod headers rewrite

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

RUN mkdir -p /var/run/apache2 /var/lock/apache2 /tmp \
  && chown -R www-data:www-data /var/www/html /var/run/apache2 /var/lock/apache2 /tmp

EXPOSE 80
