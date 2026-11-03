#!/bin/sh
set -eu

occ() { php /var/www/html/occ "$@"; }

occ config:system:set log_type --value=errorlog
occ config:system:set loglevel --value="${NEXTCLOUD_LOGLEVEL:-1}" --type=integer
occ config:system:set logtimezone --value="${TZ:-Europe/Paris}"

occ app:disable logreader >/dev/null

occ app:enable admin_audit >/dev/null
occ config:system:set log_type_audit --value=errorlog

echo "[campus-nextcloud] Journaux : errorlog, niveau ${NEXTCLOUD_LOGLEVEL:-1}, audit actif"
