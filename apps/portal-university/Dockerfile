FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql

COPY /src/ /var/www/html/
COPY /docker/ /usr/local/lib/campus/
RUN sed -i 's/\r$//' /usr/local/lib/campus/start.sh && chmod +x /usr/local/lib/campus/start.sh
ENTRYPOINT ["/usr/local/lib/campus/start.sh"]
CMD ["apache2-foreground"]
