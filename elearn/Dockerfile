FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libicu-dev libxml2-dev unzip && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j"$(nproc)" gd intl zip mysqli soap exif opcache
RUN printf "max_input_vars=5000\nmemory_limit=256M\nupload_max_filesize=64M\npost_max_size=64M\n" > /usr/local/etc/php/conf.d/moodle.ini

RUN curl -fsSL "https://download.moodle.org/download.php/direct/stable405/moodle-latest-405.tgz" | tar -xz --strip-components=1 -C /var/www/html

RUN mkdir -p /var/www/moodlelang && curl -fsSL -o /tmp/fr.zip "https://download.moodle.org/download.php/direct/langpack/4.5/fr.zip" && unzip -q /tmp/fr.zip -d /var/www/moodlelang && rm /tmp/fr.zip
RUN mkdir -p /var/www/moodledata && chown -R www-data:www-data /var/www/moodledata
COPY config.php /var/www/html/config.php
