<?php
session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Le jeton lie le formulaire de déploiement à la session de l’administrateur.
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
$notice = $_SESSION['dashboard_notice'] ?? '';
unset($_SESSION['dashboard_notice']);
require __DIR__ . '/views/dashboard.php';
