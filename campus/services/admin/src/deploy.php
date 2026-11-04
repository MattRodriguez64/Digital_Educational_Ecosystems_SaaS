<?php

session_start();
// vérification qu'un compte admin est co
if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Accès via form avec méthode POST sinon on mets une erreur 405
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode interdite');
}

if (
    empty($_SESSION['csrf']) ||
    !is_string($_POST['csrf'] ?? null) ||
    !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')
) {
    http_response_code(403);
    exit('Requête invalide');
}

// Vérification que les champs pour créer une stack univ-* ne sont pas vides
if (isset($_POST['univ-name']) && isset($_POST['univ-slug']) && isset($_POST['univ-country'])) {
    $name = $_POST['univ-name'];
    $slug = $_POST['univ-slug'];
    $country = $_POST['univ-country'];

    $email = filter_var($_POST['billing-email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (
        !$email ||
        strlen($email) > 254 ||
        !is_string($name) ||
        trim($name) === '' ||
        strlen($name) > 180
    ) {
        http_response_code(422);
        exit('Nom ou adresse de facturation invalide.');
    }
    // On importe la config de connexion à la DB
    require __DIR__ . '/config.php';

    // Valider aussi côté serveur pour garantir que la stack apparaît dans la supervision.
    if (
        !is_string($slug) ||
        strlen($slug) > 128 ||
        !preg_match('/^univ-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)
    ) {
        http_response_code(422);
        exit('Identifiant de stack invalide.');
    }
    $domain = $slug . '.localhost';
    $stackName = $slug;

    // Lire le template (IMPOORTANT : de mettre le chemin absolu)
    $template = file_get_contents(dirname(__DIR__) . '/templates/compose.yaml');

    if ($template === false) {
        http_response_code(500);
        exit('Template introuvable');
    }

    // Paramètres de Portainer
    $portainerUrl = rtrim(getenv('PORTAINER_URL'), '/');
    $apiKey = getenv('PORTAINER_API_KEY');
    $endpointId = getenv('PORTAINER_ENDPOINT_ID');

    // Création du payload pour l'API Portainer.
    // Le même modèle peut ainsi servir à déployer plusieurs stacks.
    $data = [
        'Name' => $stackName, // Nom de la stack, par convention du projet : univ-*
        'StackFileContent' => $template, // Fichier de config template
        'Env' => [ // toutes les variables d'environnements nécessaire à la stack
            ['name' => 'DB_ROOT_PASSWORD', 'value' => 'moodle'],
            ['name' => 'DB_PASSWORD', 'value' => 'moodle'],
            ['name' => 'DB_USER', 'value' => 'moodle'],
            ['name' => 'DB_NAME', 'value' => 'moodle'],
            ['name' => 'UNIV_SLUG', 'value' => $slug],
            ['name' => 'UNIV_DOMAIN', 'value' => $domain],
            ['name' => 'UNIV_NAME', 'value' => $name],
            ['name' => 'UNIV_COUNTRY', 'value' => $country]
        ]
    ];

    // Appeler l'API de Portainer
    $url =
        $portainerUrl .
        '/api/stacks/create/standalone/string?endpointId=' .
        rawurlencode($endpointId);

    $ch = curl_init($url);

    // Envoyer les paramètres en JSON et attendre la réponse avant d’enregistrer le contact.
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT => 120,

        // Le Portainer local utilise un certificat autosigné.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        $_SESSION['dashboard_notice'] = 'Échec du déploiement (Portainer HTTP ' . $httpCode . ').';
        header('Location: dashboard.php#deploiement', true, 303);
        exit();
    }

    // Le contact appartient à l'administration, pas aux variables des conteneurs universitaires.
    $mysqli->execute_query(
        'INSERT INTO billing_contacts (slug,name,email) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email)',
        [$slug, $name, $email]
    );
    $_SESSION['dashboard_notice'] = 'Stack créée : ' . $stackName . ' · ' . $domain;
    header('Location: dashboard.php#universites', true, 303);
    exit();
}
