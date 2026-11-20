<?php

include_once __DIR__ . '/authentHelper.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$secret = getenv('SUPPORT_SECRET') ?: '';
if ($secret === '') {
    http_response_code(503);
    exit('Support indisponible : configuration manquante.');
}

$payload = base64_encode(json_encode([
    'slug' => getenv('UNIV_SLUG'),
    'university' => getenv('UNIV_NAME'),
    'user_id' => (int) $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
    'portal' => publicUrl('moncompte.' . getenv('UNIV_DOMAIN')),
    'ts' => time(),
], JSON_THROW_ON_ERROR));
$token = $payload . '.' . hash_hmac('sha256', $payload, $secret);
$action = rtrim(getenv('SUPPORT_URL') ?: 'http://support.localhost', '/') . '/connexion.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Redirection vers le support</title>
</head>

<body>
    <form method="post" action="<?= htmlspecialchars($action) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <noscript><button type="submit">Accéder au support</button></noscript>
    </form>
    <script>document.forms[0].submit();</script>
</body>

</html>
