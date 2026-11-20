FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql
COPY apps/support/ /var/www/html/
