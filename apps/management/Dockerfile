FROM php:8.3-apache
RUN docker-php-ext-install mysqli pdo_mysql && docker-php-ext-enable mysqli
COPY apps/management/src/ /var/www/html/
COPY template/university/ /var/www/templates/
COPY apps/management/docker/ /usr/local/lib/campus/
RUN sed -i 's/\r$//' /usr/local/lib/campus/start.sh && chmod +x /usr/local/lib/campus/start.sh
ENTRYPOINT ["/usr/local/lib/campus/start.sh"]
CMD ["apache2-foreground"]
