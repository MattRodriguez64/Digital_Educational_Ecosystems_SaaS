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
    university VARCHAR(100),
    handled_by VARCHAR(100),
    solution TEXT
);