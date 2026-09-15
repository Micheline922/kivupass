FROM php:8.2-apache

WORKDIR /var/www/html

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && chown -R www-data:www-data /var/www/html

COPY php.ini /usr/local/etc/php/conf.d/kivupass.ini
COPY . /var/www/html/

# Render fournit la variable PORT au demarrage du conteneur.
EXPOSE 10000

CMD ["sh", "-c", "PORT=${PORT:-10000}; sed -i \"s/Listen 80/Listen $PORT/; s/:80>/:$PORT>/\" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
