<?php
session_start();

if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode interdite');
}

if (!is_string($_SESSION['csrf'] ?? null) || $_SESSION['csrf'] === ''
    || !is_string($_POST['csrf'] ?? null)
    || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Requête invalide');
}

$name = $_POST['univ-name'] ?? null;
$slug = $_POST['univ-slug'] ?? null;
$country = $_POST['univ-country'] ?? null;
$emailInput = $_POST['billing-email'] ?? null;
$email = is_string($emailInput) ? filter_var($emailInput, FILTER_VALIDATE_EMAIL) : false;

if (!is_string($name) || trim($name) === '' || strlen($name) > 180
    || !is_string($country) || trim($country) === '' || strlen($country) > 100
    || !$email || strlen($email) > 254
    || !is_string($slug) || strlen($slug) > 63
    || !preg_match('/^univ-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
    http_response_code(422);
    exit('Paramètres universitaires ou adresse de facturation invalides.');
}

$name = trim($name);
$country = trim($country);
require __DIR__ . '/config.php';
require_once __DIR__ . '/portainer.php';

$accepted = false;
try {
    $suffix = strtolower(trim(portainerRequiredEnv('CAMPUS_BASE_DOMAIN'), '. '));
    if (strlen($suffix) > 180
        || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $suffix)) {
        throw new RuntimeException('CAMPUS_BASE_DOMAIN invalide');
    }

    $domain = $slug . '.' . $suffix;
    if (strlen('nextcloud.' . $domain) > 253) {
        throw new RuntimeException('Domaine universitaire trop long');
    }

    $port = getenv('PUBLIC_PORT') ?: '18081';
    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('PUBLIC_PORT invalide');
    }

    // Un fichier distinct conserve le modèle Compose historiqu
    $templatePath = dirname(__DIR__) . '/templates/swarm.yml';
    if (!is_file($templatePath) || !is_readable($templatePath)) {
        throw new RuntimeException('Modèle Swarm absent : templates/swarm.yml');
    }

    $template = file_get_contents($templatePath);
    if ($template === false || trim($template) === '') {
        throw new RuntimeException('Modèle Swarm vide ou illisible');
    }

    $endpointId = portainerRequiredEnv('PORTAINER_ENDPOINT_ID');
    $swarmId = portainerSwarmId($endpointId);
    // Mots de passe SQL propres à chaque nouvelle université
    // Le futur modèle Swarm prépare Nextcloud avec son hook prepare-db.php
    $values = [
        'DB_ROOT_PASSWORD' => bin2hex(random_bytes(24)),
        'DB_PASSWORD' => bin2hex(random_bytes(24)),
        'DB_USER' => 'moodle',
        'DB_NAME' => 'moodle',
        'MOODLE_ADMIN_PASSWORD' => portainerRequiredEnv('MOODLE_ADMIN_PASSWORD'),
        'MOODLE_DEMO_PASSWORD' => getenv('MOODLE_DEMO_PASSWORD') ?: '',
        'UNIV_SLUG' => $slug,
        'UNIV_DOMAIN' => $domain,
        'UNIV_NAME' => $name,
        'UNIV_COUNTRY' => $country,
        'PUBLIC_PORT' => $port,
        'DATA_NODE_HOSTNAME' => portainerRequiredEnv('DATA_NODE_HOSTNAME'),
        'NEXTCLOUD_DB_PASSWORD' => bin2hex(random_bytes(24)),
        'NEXTCLOUD_ADMIN_USER' => getenv('NEXTCLOUD_ADMIN_USER') ?: 'admin',
        'NEXTCLOUD_ADMIN_PASSWORD' => portainerRequiredEnv('NEXTCLOUD_ADMIN_PASSWORD'),
        'NEXTCLOUD_DEMO_PASSWORD' => portainerRequiredEnv('NEXTCLOUD_DEMO_PASSWORD'),
    ];

    $env = [];
    foreach ($values as $key => $value) {
        $env[] = ['name' => $key, 'value' => $value];
    }

    [$httpCode, $response] = portainerRequest(
        'stacks/create/swarm/string?endpointId=' . rawurlencode($endpointId),
        'POST',
        [
            'Name' => $slug,
            'SwarmID' => $swarmId,
            'StackFileContent' => $template,
            'Env' => $env,
        ]
    );

    if ($httpCode < 200 || $httpCode >= 300) {
        // Ne pas afficher la réponse brute : elle peut contenir des identifiants
        throw new RuntimeException('Déploiement refusé par Portainer (HTTP ' . $httpCode . ').');
    }

    $accepted = true;
    $mysqli->execute_query(
        'INSERT INTO billing_contacts (slug,name,email) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email)',
        [$slug, $name, $email]
    );

    $_SESSION['dashboard_notice'] = 'Déploiement Swarm demandé : ' . $slug .
        ' · ' . $domain . ':' . $port . ' (initialisation en cours).';
    header('Location: dashboard.php#universites', true, 303);
} catch (Throwable $error) {
    if ($accepted) {
        $_SESSION['dashboard_notice'] =
            'Stack acceptée par Portainer, mais contact de facturation non enregistré. ' .
            'Ne pas relancer la création ; vérifier la facturation.';
    } else {
        // Seuls les messages RuntimeException contrôlés sont affichés
        $_SESSION['dashboard_notice'] = $error instanceof RuntimeException
            ? $error->getMessage()
            : 'Erreur de préparation du déploiement. Vérifier la configuration.';
    }

    header('Location: dashboard.php#deploiement', true, 303);
}

exit();
