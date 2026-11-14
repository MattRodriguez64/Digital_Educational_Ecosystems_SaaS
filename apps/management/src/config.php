<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Connexion DB classique
$db_host = getenv('DB_HOST') ?: 'mariadb';
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_name = 'admin_saas';

// On Test la Connexion
$mysqli = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    die('DB Connection Error: ' . $mysqli->connect_error);
}
