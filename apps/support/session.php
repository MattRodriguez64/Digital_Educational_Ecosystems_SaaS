<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['support_user'])) {
    require __DIR__ . '/acces-refuse.php';
    exit;
}

$compte = $_SESSION['support_user'];
$est_admin = $compte['role'] === 'admin';
$utilisateur = $compte['username'];
$universite = $compte['university'];
$slug = $compte['slug'];
$user_id = (int) $compte['user_id'];
$portail = $compte['portal'];
$handler_id = $slug . ':' . $user_id;
