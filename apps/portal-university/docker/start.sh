#!/bin/sh
set -eu
php /usr/local/lib/campus/prepare-auth.php
exec docker-php-entrypoint "$@"
