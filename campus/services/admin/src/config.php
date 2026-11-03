<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$db_host = 'campus-db';
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_name = 'admin_saas';

$mysqli = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    die('DB Connection Error: ' . $mysqli->connect_error);
}
