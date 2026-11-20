<?php

include_once __DIR__ . '/authentHelper.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}
if (!estAdmin()) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$message = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $roles = listeRoles();
    $roleValide = false;
    if (isset($roles[$role])) {
        $roleValide = true;
    }

    $estMonCompte = false;
    if ($id === (int) $_SESSION['user_id']) {
        $estMonCompte = true;
    }

    if ($action === 'delete') {
        if ($estMonCompte === true) {
            $error = 'Impossible de supprimer le compte sur lequel vous êtes connecté.';
        } else {
            deleteUser($id);
            $_SESSION['flash'] = 'Utilisateur supprimé.';
            header('Location: handleUsers.php');
            exit;
        }
    } elseif ($action === 'create' || $action === 'update') {
        if ($username === '' || strlen($username) > 100) {
            $error = 'Nom d\'utilisateur invalide.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $error = 'Adresse e-mail invalide.';
        } elseif (($action === 'create' || $password !== '') && strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($roleValide === false) {
            $error = 'Rôle invalide.';
        } elseif ($action === 'update' && $estMonCompte === true && $role !== 'admin') {
            $error = 'Vous ne pouvez pas retirer votre propre rôle administrateur.';
        } else {
            try {
                if ($action === 'create') {
                    createUser($username, $email, $password, $role);
                    $_SESSION['flash'] = 'Utilisateur créé.';
                } else {
                    updateUser($id, $username, $email, $role, $password);
                    if ($estMonCompte === true) {
                        $_SESSION['username'] = $username;
                    }
                    $_SESSION['flash'] = 'Utilisateur modifié.';
                }
                header('Location: handleUsers.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Cette adresse e-mail ou ce nom d\'utilisateur est déjà utilisé.';
                } else {
                    throw $e;
                }
            }
        }
    }
}

$editUser = isset($_GET['edit']) ? findUserById((int) $_GET['edit']) : null;
$users = listUsers();
$roles = listeRoles();

$roleChoisi = 'student';
if ($editUser !== null) {
    $roleChoisi = $editUser['role'];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Utilisateurs - Portail UNITA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f4f4;
            color: #222;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background: #fff;
            border-bottom: 1px solid #ccc;
        }

        header h1 {
            font-size: 20px;
            margin: 0;
        }

        main {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }

        section {
            background: #fff;
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 17px;
            margin: 0 0 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            text-align: left;
            padding: 6px;
            border-bottom: 1px solid #ddd;
        }

        form.inline {
            display: inline;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 6px;
            margin-top: 4px;
        }

        .bouton {
            display: inline-block;
            padding: 4px 12px;
            border: 1px solid #555;
            background: #e9e9e9;
            color: #222;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .error {
            color: #b00020;
        }

        .message {
            color: #1b6e2d;
        }
    </style>
</head>

<body>
    <header>
        <h1>Gestion des utilisateurs</h1>
        <a class="bouton" href="index.php">Retour au portail</a>
    </header>
    <main>
        <?php if ($message): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <section>
            <h2><?= $editUser ? 'Modifier l\'utilisateur' : 'Créer un utilisateur' ?></h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
                <?php endif; ?>
                <label>Nom d'utilisateur
                    <input type="text" name="username" maxlength="100" required value="<?= htmlspecialchars($editUser['username'] ?? '') ?>">
                </label>
                <label>E-mail
                    <input type="email" name="email" maxlength="254" required value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                </label>
                <label>Mot de passe<?= $editUser ? ' (laisser vide pour ne pas le changer)' : '' ?>
                    <input type="password" name="password" minlength="8" autocomplete="new-password" <?= $editUser ? '' : 'required' ?>>
                </label>
                <label>Rôle
                    <select name="role" required>
                        <?php foreach ($roles as $valeur => $libelle): ?>
                            <?php if ($valeur === $roleChoisi): ?>
                                <option value="<?= htmlspecialchars($valeur) ?>" selected><?= htmlspecialchars($libelle) ?></option>
                            <?php else: ?>
                                <option value="<?= htmlspecialchars($valeur) ?>"><?= htmlspecialchars($libelle) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="bouton"><?= $editUser ? 'Enregistrer' : 'Créer' ?></button>
                <?php if ($editUser): ?>
                    <a class="bouton" href="handleUsers.php">Annuler</a>
                <?php endif; ?>
            </form>
        </section>

        <section>
            <h2>Utilisateurs</h2>
            <table>
                <tr>
                    <th>Nom d'utilisateur</th>
                    <th>E-mail</th>
                    <th>Rôle</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($roles[$user['role']]) ?></td>
                        <td>
                            <a class="bouton" href="handleUsers.php?edit=<?= (int) $user['id'] ?>">Modifier</a>
                            <?php if ((int) $user['id'] !== (int) $_SESSION['user_id']): ?>
                                <form class="inline" method="post" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button type="submit" class="bouton">Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>
    </main>
</body>

</html>
