<?php

include_once __DIR__ . '/authentHelper.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = checkLogin(trim($_POST['email'] ?? ''),$_POST['password'] ?? '');
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    }
    $error = 'Identifiants incorrects.';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - Portail UNITA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f4f4;
            color: #222;
        }

        main {
            max-width: 320px;
            margin: 80px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccc;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 15px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 6px;
            margin-top: 4px;
        }

        button {
            padding: 6px 14px;
            border: 1px solid #555;
            background: #e9e9e9;
            cursor: pointer;
        }

        .error {
            color: #b00020;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <main>
        <h1>Connexion</h1>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <label>E-mail
                <input type="email" name="email" required autofocus>
            </label>
            <label>Mot de passe
                <input type="password" name="password" required>
            </label>
            <button type="submit">Se connecter</button>
        </form>
    </main>
</body>

</html>
