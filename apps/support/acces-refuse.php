<?php
http_response_code(403);
// page quand on essaye d'accéder au support sans passer par le portail ; qui envoi les infos de l'user en post. Donc obligatoire.
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body>
    <nav class="navbar navbar-dark bg-dark">
        <span class="navbar-brand">Support</span>
    </nav>
    <div class="container my-5">
        <h1 class="h4">Accès au support</h1>
        <p>Le support est accessible uniquement depuis le portail de votre université.</p>
        <p>Connectez-vous à votre portail puis cliquez sur « Support ».</p>
    </div>
</body>

</html>
