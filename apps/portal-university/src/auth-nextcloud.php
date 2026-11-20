<?php

include_once __DIR__ . '/authentHelper.php';

$hoteInterne = 'portail-' . getenv('UNIV_SLUG');

if ($_SERVER['HTTP_HOST'] !== $hoteInterne) {
    http_response_code(404);
    exit;
}

$email = '';
if (isset($_SERVER['PHP_AUTH_USER'])) {
    $email = strtolower(trim($_SERVER['PHP_AUTH_USER']));
}

$password = '';
if (isset($_SERVER['PHP_AUTH_PW'])) {
    $password = $_SERVER['PHP_AUTH_PW'];
}

if ($email === '' || $password === '') {
    header('WWW-Authenticate: Basic realm="Portail"');
    http_response_code(401);
    exit;
}

$user = checkLogin($email, $password);

if ($user === null) {
    header('WWW-Authenticate: Basic realm="Portail"');
    http_response_code(401);
    exit;
}

http_response_code(200);
echo 'OK';
