#!/bin/sh
set -eu
# No HTTP requests are served until the additive schema migration succeeds.
php /usr/local/lib/campus/migrate.php
exec docker-php-entrypoint "$@"
