#!/bin/bash
set -e
# Grant the configured application user access to the shared management database.
case "${MARIADB_USER:-}" in
    ''|*[!A-Za-z0-9_]*) echo 'MARIADB_USER invalide pour les droits Gestion' >&2; exit 1 ;;
esac
printf "GRANT ALL PRIVILEGES ON admin_saas.* TO '%s'@'%%';\n" "$MARIADB_USER" | MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb --protocol=socket -uroot
