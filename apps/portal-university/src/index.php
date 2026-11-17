<?php

include_once __DIR__ . '/authentHelper.php';
if(!isLoggedIn()) {
    header('Location: login.php');
    exit;
}
$services = [
    ['nom' => 'Elearn', 'description' => 'Cours et activités en ligne', 'url' => 'http://elearn.' . getenv('UNIV_DOMAIN')],
    ['nom' => 'Nextcloud', 'description' => 'Stockage et partage de fichiers', 'url' => 'http://nextcloud.' . getenv('UNIV_DOMAIN')],
    ['nom' => 'Kibana', 'description' => 'Monitoring et performances', 'url' => 'http://localhost:5601'],
    ['nom' => 'Support', 'description' => 'Support et réclamations', 'url' => 'support.php'],
];
//faudra remplir pour nextcloud, voir plus selon ce qu'on fait

if (estAdmin()) {
    $services[] = ['nom' => 'Utilisateurs', 'description' => 'Gestion des comptes de l\'université', 'url' => 'handleUsers.php'];
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$message = '';
if (isset($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = '';
    if (isset($_POST['csrf'])) {
        $csrf = $_POST['csrf'];
    }

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $ancien = '';
    if (isset($_POST['ancien'])) {
        $ancien = $_POST['ancien'];
    }

    $nouveau = '';
    if (isset($_POST['nouveau'])) {
        $nouveau = $_POST['nouveau'];
    }

    $confirmation = '';
    if (isset($_POST['confirmation'])) {
        $confirmation = $_POST['confirmation'];
    }

    if (!verifierMotDePasse((int) $_SESSION['user_id'], $ancien)) {
        $erreur = 'Mot de passe actuel incorrect.';
    } elseif (strlen($nouveau) < 8) {
        $erreur = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } elseif ($nouveau !== $confirmation) {
        $erreur = 'Les deux nouveaux mots de passe ne sont pas identiques.';
    } else {
        changerMotDePasse((int) $_SESSION['user_id'], $nouveau);
        $_SESSION['flash'] = 'Mot de passe modifié.';
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portail UNITA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .services {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .service {
            flex: 1 1 180px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccc;
        }

        .service h2 {
            font-size: 17px;
            margin: 0 0 5px;
        }

        .service p {
            flex-grow: 1;
            margin: 0 0 12px;
            font-size: 14px;
        }

        .bouton {
            display: inline-block;
            padding: 6px 14px;
            border: 1px solid #555;
            background: #e9e9e9;
            color: #222;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .bouton:hover {
            background: #dcdcdc;
        }

        .compte {
            width: 90%;
            max-width: 400px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccc;
        }

        .compte::backdrop {
            background: rgba(0, 0, 0, 0.4);
        }

        .compte h2 {
            font-size: 17px;
            margin: 0 0 10px;
        }

        .compte label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .compte input {
            width: 100%;
            box-sizing: border-box;
            padding: 6px;
            margin-top: 4px;
        }

        .message {
            color: #1b6e2d;
        }

        .erreur {
            color: #b00020;
        }

        footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin: 40px 0 20px;
        }
    </style>
</head>

<body>
    <header>
        <h1>Portail UNITA - <?php echo getenv('UNIV_NAME') . " - " . getenv('UNIV_COUNTRY'); ?></h1>
        <span>
            <button type="button" class="bouton" id="ouvrir-compte" title="Changer mon mot de passe">
                <i class="fas fa-cog"></i>
            </button>
            <a class="bouton" href="deconnexion.php">Déconnexion</a>
        </span><!-- Vu qu'on peut accéder à là qu'en étant connecté, ce sera toujours un bouton déconnexion!-->
    </header>
    <main>
        <?php if ($message !== ''): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <p>Bienvenue sur l'écosystème éducatif numérique de l'alliance UNITA. Choisissez un service :</p>
        <div class="services">
            <?php foreach ($services as $service): ?>
                <div class="service">
                    <h2><?= htmlspecialchars($service['nom']) ?></h2>
                    <p><?= htmlspecialchars($service['description']) ?></p>
                    <a class="bouton" href="<?= htmlspecialchars($service['url']) ?>">Accéder</a>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <dialog class="compte" id="modal-compte">
        <h2>Changer mon mot de passe</h2>
        <?php if ($erreur !== ''): ?>
            <p class="erreur"><?= htmlspecialchars($erreur) ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <label>Mot de passe actuel
                <input type="password" name="ancien" required autocomplete="current-password">
            </label>
            <label>Nouveau mot de passe
                <input type="password" name="nouveau" required minlength="8" autocomplete="new-password">
            </label>
            <label>Confirmer le nouveau mot de passe
                <input type="password" name="confirmation" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="bouton">Modifier</button>
            <button type="button" class="bouton" id="fermer-compte">Annuler</button>
        </form>
    </dialog>

    <footer>Écosystèmes éducatifs numériques &middot; <?= date('Y') ?></footer>

    <script>
        const modal = document.getElementById('modal-compte');
        const boutonOuvrir = document.getElementById('ouvrir-compte');
        const boutonFermer = document.getElementById('fermer-compte');

        boutonOuvrir.addEventListener('click', function () {
            modal.showModal();
        });

        boutonFermer.addEventListener('click', function () {
            modal.close();
        });

        <?php if ($erreur !== ''): ?>
            modal.showModal();
        <?php endif; ?>
    </script>
</body>

</html>