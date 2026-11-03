FROM php:8.3-apache

# Le contexte de build contient uniquement les fichiers web du service choisi.
COPY . /var/www/html/
