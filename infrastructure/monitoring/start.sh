#!/bin/sh
set -eu
# Retry transient Elasticsearch failures; a permanent failure leaves a visible restart.
attempt=0
until php /opt/campus/setup.php; do
    attempt=$((attempt + 1))
    [ "$attempt" -lt 30 ] || exit 1
    sleep 2
done
echo '[monitoring] Schémas Elasticsearch prêts.'
exec php /opt/campus/collector.php
