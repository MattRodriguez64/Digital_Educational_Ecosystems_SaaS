#!/bin/bash
set -e
#   docker compose exec mariadb bash /docker-entrypoint-initdb.d/zy-landing.sh

if [ -z "${LANDING_DB_PASSWORD:-}" ] && [ -f /run/secrets/landing_db_password ]; then
    LANDING_DB_PASSWORD="$(tr -d '\r\n' < /run/secrets/landing_db_password)"
fi
: "${LANDING_DB_PASSWORD:?mot de passe de la base landing manquant (secret landing_db_password)}"
case "$LANDING_DB_PASSWORD" in
    *[!A-Za-z0-9_-]*) echo 'mot de passe invalide : lettres, chiffres, _ et - uniquement' >&2; exit 1 ;;
esac

MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS landing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- L'API ne peut que lire, ajouter et modifier
CREATE USER IF NOT EXISTS 'landing'@'%' IDENTIFIED BY '${LANDING_DB_PASSWORD}';
ALTER USER 'landing'@'%' IDENTIFIED BY '${LANDING_DB_PASSWORD}';
GRANT SELECT, INSERT, UPDATE ON landing.* TO 'landing'@'%';

USE landing;
CREATE TABLE IF NOT EXISTS contact_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    university VARCHAR(180) NOT NULL,
    -- Code pays ISO 3166-1 (MA, FR...) choisi dans la liste du formulaire.
    country VARCHAR(80) NOT NULL,
    city VARCHAR(120) NULL,
    contact_name VARCHAR(120) NOT NULL,
    role VARCHAR(120) NULL,
    email VARCHAR(254) NOT NULL,
    students VARCHAR(20) NULL,
    message TEXT NOT NULL,
    -- 0 = nouvelle, 1 = contactée, 2 = clôturée (même logique que les tickets du support)
    status TINYINT NOT NULL DEFAULT 0,
    -- Empreinte (HMAC) de l'adresse IP, pour limiter le nombre d'envois sans stocker l'adresse.
    ip_hash CHAR(64) NULL,
    INDEX idx_status_created (status, created_at)
);

-- Pour une base créée avant l'ajout de ces colonnes
ALTER TABLE contact_requests ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER country;
ALTER TABLE contact_requests ADD COLUMN IF NOT EXISTS ip_hash CHAR(64) NULL;
CREATE INDEX IF NOT EXISTS idx_ip_created ON contact_requests (ip_hash, created_at);
SQL