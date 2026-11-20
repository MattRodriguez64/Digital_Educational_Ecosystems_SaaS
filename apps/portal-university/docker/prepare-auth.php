<?php
declare(strict_types=1);

$rootPassword = getenv('CAMPUS_DB_ROOT_PASSWORD');
if ($rootPassword === false || $rootPassword === '') {
    exit(0);
}

$host = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: '';
if (!preg_match('/^[A-Za-z0-9_]{1,80}$/D', $dbUser)) {
    throw new RuntimeException('DB_USER invalide pour la base authentification.');
}

$db = null;
for ($attempt = 1; $attempt <= 60; $attempt++) {
    try {
        $db = new PDO('mysql:host=' . $host . ';charset=utf8mb4', 'root', $rootPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        break;
    } catch (PDOException $error) {
        if ($attempt === 60) {
            throw new RuntimeException('Base authentification inaccessible après 60 tentatives.', 0, $error);
        }
        sleep(2);
    }
}

$db->exec('CREATE DATABASE IF NOT EXISTS authentification CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec("GRANT ALL PRIVILEGES ON authentification.* TO " . $db->quote($dbUser) . "@'%'");
$db->exec("CREATE TABLE IF NOT EXISTS authentification.users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$email = strtolower(trim((string) getenv('ADMIN_EMAIL')));
$hash = base64_decode((string) getenv('ADMIN_PASSWORD_HASH_B64'), true);
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && is_string($hash) && $hash !== '') {
    $stmt = $db->prepare("INSERT IGNORE INTO authentification.users (username, email, password_hash, role)
        SELECT ?, ?, ?, 'admin' FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM authentification.users)");
    $stmt->execute([$email, $email, $hash]);
    if ($stmt->rowCount() === 1) {
        echo "[portail] Compte administrateur initial créé : $email\n";
    }
}

echo "[portail] Base authentification prête.\n";
