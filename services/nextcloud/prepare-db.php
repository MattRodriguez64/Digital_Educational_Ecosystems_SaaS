<?php
declare(strict_types=1);
// University stacks initialize this database using their embedded SQL config.
$rootPassword = getenv('CAMPUS_DB_ROOT_PASSWORD');
if ($rootPassword === false || $rootPassword === '') { exit(0); }
$password = getenv('MYSQL_PASSWORD');
if (!is_string($password) || !preg_match('/^[A-Za-z0-9_-]+$/D', $password)) {
    throw new RuntimeException('Mot de passe de base Nextcloud invalide.');
}
$db = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $db = new PDO('mysql:host=' . getenv('MYSQL_HOST') . ';charset=utf8mb4', 'root', $rootPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
        break;
    } catch (PDOException $error) {
        if ($attempt === 30) { throw new RuntimeException('Base Nextcloud inaccessible après 30 tentatives.', 0, $error); }
        sleep(2);
    }
}
$db->exec('CREATE DATABASE IF NOT EXISTS nextcloud CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$db->exec("CREATE USER IF NOT EXISTS 'nextcloud'@'%' IDENTIFIED BY " . $db->quote($password));
$db->exec("GRANT ALL PRIVILEGES ON nextcloud.* TO 'nextcloud'@'%'");
echo "[nextcloud] Base prête, comptes existants conservés.\n";
