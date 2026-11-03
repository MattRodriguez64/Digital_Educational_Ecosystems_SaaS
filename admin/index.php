<?php
$services = [
    ['nom' => 'Elearn', 'description' => 'Cours et activités en ligne', 'url' => 'http://elearn.localhost'],
    ['nom' => 'Nextcloud', 'description' => 'Stockage et partage de fichiers', 'url' => '#'],
    ['nom' => 'Portainer', 'description' => 'Administration de la plateforme', 'url' => 'http://portainer.localhost'],
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
</head>

<body>
    <header>
        <h1>Portail UNITA</h1>
        <button type="button" class="bouton">Connexion</button>
    </header>
    <main>
        <p>Bienvenue sur l'écosystème éducatif numérique de l'alliance UNITA. Choisissez un service :</p>
        <div class="services">

        </div>
    </main>
    <footer>Écosystèmes éducatifs numériques &middot; <?= date('Y') ?></footer>
</body>

</html>