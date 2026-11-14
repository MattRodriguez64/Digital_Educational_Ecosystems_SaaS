<?php
//encore une fois : a changé selon la situation tant que j'ai pas l'authent
$est_admin = true;
$utilisateur = 'admin';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SLA - Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body>
    <nav class="navbar navbar-dark bg-dark">
        <span class="navbar-brand">Support</span>
        <div class="d-flex align-items-center">
            <a href="index.php" class="btn btn-outline-light btn-sm mr-3">Retour au support</a>
            <a href="http://localhost" class="navbar-text"><?= htmlspecialchars($utilisateur . ($est_admin ? ' (admin)' : '')) ?></a>
        </div>
    </nav>

    <div class="container my-4">
        <h1 class="h3">Accord de niveau de service (SLA)</h1>
        <p class="text-muted">Voici ce que nous nous engageons à vous fournir. Le même SLA s'applique à toutes les universités membres.</p>

        <div class="row text-center mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body">
                        <div class="h2">99,5 %</div>
                        <p class="mb-0">de disponibilité par mois</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body">
                        <div class="h2">4 h</div>
                        <p class="mb-0">pour les incidents critiques</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body">
                        <div class="h2">1 / jour</div>
                        <p class="mb-0">sauvegarde des données</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body">
                        <div class="h2">1 jour</div>
                        <p class="mb-0">pour une première réponse</p>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th style="width: 25%">Sujet</th>
                    <th>Notre engagement</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Disponibilité</strong></td>
                    <td>99,5 % par mois, du lundi au vendredi de 8 h à 18 h. En dehors de ces heures, nous intervenons le jour ouvré suivant. Les maintenances annoncées à l'avance ne comptent pas.</td>
                </tr>
                <tr>
                    <td><strong>Support</strong></td>
                    <td>Vous recevez une réponse sous un jour ouvré. Un incident critique, c'est-à-dire un service totalement inutilisable, est traité sous 4 heures ouvrées.</td>
                </tr>
                <tr>
                    <td><strong>Performance</strong></td>
                    <td>Des ressources sont réservées pour chaque université, et nous ajoutons de la capacité pendant les périodes d'examens.</td>
                </tr>
                <tr>
                    <td><strong>Sécurité</strong></td>
                    <td>Connexion en HTTPS, données hébergées en France, séparées entre les universités et protégées conformément au RGPD.</td>
                </tr>
                <tr>
                    <td><strong>Sauvegardes</strong></td>
                    <td>Une sauvegarde chaque jour, conservée hors site. La restauration est testée chaque trimestre.</td>
                </tr>
            </tbody>
        </table>

        <h2 class="h5 mt-4">Comment faire une demande ?</h2>
        <p>Créez un ticket depuis la <a href="index.php">page de support</a> en choisissant une priorité : <strong>Haute</strong> pour un blocage, <strong>Moyenne</strong> pour une gêne et <strong>Basse</strong> pour une question. Vous suivez son avancement directement dans « Mes tickets ».</p>

        <p class="text-muted small">Ces engagements sont des objectifs de service et ne remplacent pas le contrat signé avec votre université.</p>
    </div>
</body>

</html>
