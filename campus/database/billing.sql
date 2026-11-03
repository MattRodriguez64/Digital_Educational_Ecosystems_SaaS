-- Migration additive. Le même script convient à une base existante ou neuve.
-- Le slug relie les coordonnées de facturation à la stack de l’université.
CREATE TABLE IF NOT EXISTS billing_contacts (
    slug VARCHAR(128) PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    email VARCHAR(254) NOT NULL,
    address VARCHAR(500) NOT NULL DEFAULT ''
);

-- Ces tarifs sont utilisés lors de la préparation du prochain aperçu.
CREATE TABLE IF NOT EXISTS billing_settings (
    id TINYINT PRIMARY KEY,
    issuer VARCHAR(180) NOT NULL,
    address VARCHAR(500) NOT NULL,
    monthly DECIMAL(12,6) NOT NULL,
    cpu DECIMAL(12,6) NOT NULL,
    memory DECIMAL(12,6) NOT NULL,
    network DECIMAL(12,6) NOT NULL,
    vat DECIMAL(5,2) NOT NULL
);

-- Chaque facture garde sa copie des prix, des quantités et du destinataire.
CREATE TABLE IF NOT EXISTS billing_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL,
    period CHAR(7) NOT NULL,
    request_key CHAR(32) NOT NULL UNIQUE,
    issued DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    snapshot JSON NOT NULL,
    INDEX (slug, period, id)
);
