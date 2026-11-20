CREATE DATABASE IF NOT EXISTS admin_saas;

USE admin_saas;
CREATE TABLE IF NOT EXISTS auth_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO auth_users (id, username, password_hash, created_at) VALUES (NULL, 'admin', 'admin', NULL);

CREATE DATABASE support;
CREATE USER 'support'@'%' IDENTIFIED BY 'support';
GRANT ALL PRIVILEGES ON support.* TO 'support'@'%';

USE support;

CREATE TABLE tickets (
    uuid VARCHAR(100) PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    category VARCHAR(30) NOT NULL,
    priority VARCHAR(15) NOT NULL,
    description TEXT NOT NULL,
    status INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NOT NULL,
    user_id INT NULL,
    university VARCHAR(100),
    university_slug VARCHAR(128) NULL,
    handled_by VARCHAR(100),
    handled_by_id VARCHAR(160) NULL,
    solution TEXT,
    INDEX idx_tickets_owner (university_slug, user_id)
);

USE admin_saas;
CREATE TABLE IF NOT EXISTS billing_contacts (
    slug VARCHAR(128) PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    email VARCHAR(254) NOT NULL,
    address VARCHAR(500) NOT NULL DEFAULT ''
);

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

CREATE TABLE IF NOT EXISTS billing_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL,
    period CHAR(7) NOT NULL,
    request_key CHAR(32) NOT NULL UNIQUE,
    issued DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    snapshot JSON NOT NULL,
    INDEX (slug, period, id)
);
