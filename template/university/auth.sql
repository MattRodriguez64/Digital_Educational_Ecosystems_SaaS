CREATE DATABASE IF NOT EXISTS authentification CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON authentification.* TO '{{DB_USER}}'@'%';

USE authentification;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, email, password_hash, role) VALUES ('{{ADMIN_EMAIL}}', '{{ADMIN_EMAIL}}', '{{ADMIN_PASSWORD_HASH}}', 'admin');
