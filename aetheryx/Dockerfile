FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

CMD ["sh", "-c", "sed -i \"s/^Listen 80$/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && apache2-foreground"]
