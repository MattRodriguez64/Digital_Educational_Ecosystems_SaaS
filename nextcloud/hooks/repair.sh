#!/bin/sh
set -eu
php /var/www/html/occ maintenance:repair --include-expensive
