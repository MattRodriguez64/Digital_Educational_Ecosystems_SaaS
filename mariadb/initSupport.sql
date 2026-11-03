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
