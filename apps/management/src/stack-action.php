<?php
session_start();

if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Méthode interdite.');
}
if (empty($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null)
    || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Requête invalide.');
}

require __DIR__ . '/portainer.php';

// Le label Compose isole les conteneurs de cette université, même s’ils sont arrêtés.
function containerCounts(string $endpoint, string $name): array
{
    $filters = json_encode(['label' => ['com.docker.compose.project=' . $name]], JSON_THROW_ON_ERROR);
    $containers = portainerList("endpoints/$endpoint/docker/containers/json?all=1&filters=" . rawurlencode($filters));
    $running = array_filter($containers, fn($container) => $container['State'] === 'running');
    return [count($running), count($containers)];
}

$action = $_POST['action'] ?? '';
$name = $_POST['name'] ?? '';
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id || !is_string($name) || !str_starts_with($name, 'univ-')
    || !in_array($action, ['start', 'stop', 'delete'], true)
    || ($action === 'delete' && ($_POST['confirmation'] ?? '') !== $name)) {
    http_response_code(422);
    exit('Action ou confirmation invalide.');
}

try {
    $endpoint = getenv('PORTAINER_ENDPOINT_ID');
    // Relire l’identité avant d’agir : une stack a pu être recréée sous le même nom
    // depuis l’affichage du tableau, ou appartenir à un autre environnement.
    [$status, $body] = portainerRequest("stacks/$id");
    if ($status === 404) {
        throw new DomainException('Cette stack n’existe plus dans Portainer.');
    }
    if ($status !== 200) {
        throw new DomainException("Portainer ne peut pas vérifier la stack (HTTP $status).");
    }
    $stack = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (($stack['Name'] ?? null) !== $name || (string) ($stack['EndpointId'] ?? '') !== $endpoint) {
        throw new DomainException('La stack ne correspond plus à la sélection.');
    }
    if ($action === 'stop' && (int) $stack['Status'] === 2) {
        throw new DomainException("$name est déjà arrêtée.");
    }
    $method = $action === 'delete' ? 'DELETE' : 'POST';
    $path = $action === 'delete' ? "stacks/$id" : "stacks/$id/$action";
    $payload = null;
    if ($action === 'start' && (int) $stack['Status'] !== 2) {
        if ((int) $stack['Status'] !== 1 || (int) $stack['Type'] !== 2 || !empty($stack['GitConfig'])) {
            throw new DomainException("Cette stack ne peut pas être démarrée dans son état actuel.");
        }
        [$running, $total] = containerCounts($endpoint, $name);
        if ($total > 0 && $running === $total) {
            throw new DomainException("$name : les $total conteneurs sont déjà démarrés.");
        }
        // Portainer refuse /start pour une stack marquée active. Réappliquer son propre
        // fichier Compose relance les services arrêtés en respectant leurs dépendances.
        [$fileStatus, $fileBody] = portainerRequest("stacks/$id/file");
        $file = $fileStatus === 200 ? json_decode($fileBody, true, 512, JSON_THROW_ON_ERROR) : [];
        if (empty($file['StackFileContent'])) {
            throw new DomainException("Impossible de relire le fichier Compose de $name.");
        }
        $method = 'PUT';
        $path = "stacks/$id";
        $payload = ['StackFileContent' => $file['StackFileContent'], 'Env' => $stack['Env'] ?? [],
            'Prune' => false, 'RepullImageAndRedeploy' => false];
    }
    [$status] = portainerRequest($path . '?endpointId=' . rawurlencode($endpoint), $method, $payload);
    if ($status < 200 || $status >= 300) {
        throw new DomainException("Opération refusée pour $name (Portainer HTTP $status).");
    }
    if ($action === 'start') {
        [$running, $total] = containerCounts($endpoint, $name);
        // Le déploiement est asynchrone : le tableau actualise les compteurs pendant le démarrage.
        $_SESSION['dashboard_notice'] = $total > 0 && $running === $total
            ? "$name : démarrage confirmé par Docker ($running/$total)."
            : "$name : demande de démarrage acceptée. Suivez les conteneurs actifs dans le tableau.";
    } else {
        $_SESSION['dashboard_notice'] = $action === 'stop' ? "Stack arrêtée : $name." : "Stack supprimée : $name.";
    }
} catch (DomainException $error) {
    $_SESSION['dashboard_notice'] = $error->getMessage();
} catch (Throwable $error) {
    // Une perte de connexion ne garantit pas que l’opération a échoué.
    $_SESSION['dashboard_notice'] = 'Résultat indisponible. Vérifiez le nouvel état de la stack avant de réessayer.';
}
header('Location: dashboard.php#universites', true, 303);
exit;
