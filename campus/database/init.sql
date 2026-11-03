CREATE DATABASE IF NOT EXISTS admin_saas;

USE admin_saas;
CREATE TABLE IF NOT EXISTS auth_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO auth_users (id, username, password_hash, created_at) VALUES (NULL, 'admin', 'admin', NULL);