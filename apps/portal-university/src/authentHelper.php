<?php

function authentDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'mariadb') . ';dbname=authentification;charset=utf8mb4';
        $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function findUserByEmail(string $email): ?array
{
    $stmt = authentDb()->prepare('SELECT id, username, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function createUser(string $username, string $email, string $password, string $role = 'student'): bool
{
    $stmt = authentDb()->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)');
    return $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
}

function checkLogin(string $email, string $password): ?array
{
    $user = findUserByEmail($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return null;
}

function isLoggedIn(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $user = findUserById((int) $_SESSION['user_id']);

    if ($user === null) {
        $_SESSION = [];
        return false;
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    return true;
}

function estAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function listUsers(): array
{
    return authentDb()->query('SELECT id, username, email, role FROM users ORDER BY id')->fetchAll();
}

function findUserById(int $id): ?array
{
    $stmt = authentDb()->prepare('SELECT id, username, email, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function updateUser(int $id, string $username, string $email, string $role, ?string $password = null): bool
{
    if ($password === null || $password === '') {
        $stmt = authentDb()->prepare('UPDATE users SET username = :username, email = :email, role = :role WHERE id = :id');
        return $stmt->execute([
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'id' => $id,
        ]);
    }

    $stmt = authentDb()->prepare('UPDATE users SET username = :username, email = :email, role = :role, password_hash = :password_hash WHERE id = :id');
    return $stmt->execute([
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $id,
    ]);
}

function verifierMotDePasse(int $id, string $password): bool
{
    $stmt = authentDb()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    if ($user === false) {
        return false;
    }

    if (password_verify($password, $user['password_hash'])) {
        return true;
    }

    return false;
}

function changerMotDePasse(int $id, string $password): bool
{
    $stmt = authentDb()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    return $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $id,
    ]);
}

function listeRoles(): array
{
    return [
        'admin' => 'Administrateur',
        'teacher' => 'Enseignant',
        'student' => 'Étudiant',
    ];
}

function deleteUser(int $id): bool
{
    $stmt = authentDb()->prepare('DELETE FROM users WHERE id = :id');
    return $stmt->execute(['id' => $id]);
}
