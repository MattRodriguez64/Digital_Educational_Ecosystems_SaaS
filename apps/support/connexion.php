<?php

function refuser(): never
{
    require __DIR__ . '/acces-refuse.php';
    exit;
}

$secret = getenv('SUPPORT_SECRET') ?: '';
$token = $_POST['token'] ?? '';
if ($secret === '' || !is_string($token)) {
    refuser();
}

$parts = explode('.', $token, 2);
if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], $secret), $parts[1])) {
    refuser();
}

$data = json_decode((string) base64_decode($parts[0], true), true);
if (
    !is_array($data) ||
    abs(time() - (int) ($data['ts'] ?? 0)) > 120 ||
    !preg_match('/^univ-[a-z0-9]+(?:-[a-z0-9]+)*$/D', (string) ($data['slug'] ?? '')) ||
    (int) ($data['user_id'] ?? 0) <= 0 ||
    !is_string($data['username'] ?? null) || $data['username'] === '' ||
    !is_string($data['role'] ?? null) ||
    !is_string($data['university'] ?? null) ||
    !preg_match('#^https?://#', (string) ($data['portal'] ?? ''))
) {
    refuser();
}

session_start();
session_regenerate_id(true);
$_SESSION['support_user'] = [
    'slug' => $data['slug'],
    'university' => $data['university'],
    'user_id' => (int) $data['user_id'],
    'username' => $data['username'],
    'role' => $data['role'],
    'portal' => $data['portal'],
];

header('Location: index.php');
exit;
