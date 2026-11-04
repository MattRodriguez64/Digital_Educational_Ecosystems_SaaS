<?php
$services = [
    ['nom' => 'Elearn', 'description' => 'Cours et activités en ligne', 'url' => 'http://elearn.' . getenv('UNIV_DOMAIN')],
    ['nom' => 'Nextcloud', 'description' => 'Stockage et partage de fichiers', 'url' => '#'],
    ['nom' => 'Kibana', 'description' => 'Monitoring et performances', 'url' => 'http://localhost:5601'],
    ['nom' => 'Support', 'description' => 'Support et réclamations', 'url' => 'http://support.localhost'],
];
//faudra remplir pour nextcloud, voir plus selon ce qu'on fait
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portail UNITA</title>
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
        <button type="button" class="bouton">Connexion</button>
    </header>
    <main>
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
    <footer>Écosystèmes éducatifs numériques &middot; <?= date('Y') ?></footer>
</body>

</html>