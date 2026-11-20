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

if (!is_string($_SESSION['csrf'] ?? null) || $_SESSION['csrf'] === ''
    || !is_string($_POST['csrf'] ?? null)
    || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Requête invalide.');
}

require_once __DIR__ . '/portainer.php';
require_once __DIR__ . '/swarm-services.php';

$action = $_POST['action'] ?? '';
$name = $_POST['name'] ?? '';
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);

if (!$id || !is_string($name) || strlen($name) > 63
    || !preg_match('/^univ-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $name)
    || !in_array($action, ['start','stop','delete'], true)
    || ($action === 'delete' && ($_POST['confirmation'] ?? '') !== $name)) {
    http_response_code(422);
    exit('Action ou confirmation invalide.');
}

try {
    $endpoint = portainerRequiredEnv('PORTAINER_ENDPOINT_ID');
    $swarmId = portainerSwarmId($endpoint);
    [$status, $body] = portainerRequest("stacks/$id");

    if ($status !== 200) {
        throw new DomainException("Stack inaccessible dans Portainer (HTTP $status).");
    }

    $stack = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($stack)) {
        throw new DomainException('Réponse Portainer inattendue.');
    }

    assertUniversityStack($stack, $id, $name, $endpoint, $swarmId);
    $managed = (int)($stack['Status'] ?? 0);
    // Ne pas modifier une stack en déploiement ou en erreur à l'aveugle

    if (!in_array($managed, [1,2], true)) {
        throw new DomainException('Stack en transition ou en erreur : vérifier son état dans Portainer avant une action.');
    }

    $method = $action === 'delete' ? 'DELETE' : 'POST';
    $path = $action === 'delete' ? "stacks/$id" : "stacks/$id/$action";
    $payload = null;

    if ($action === 'stop' && $managed === 2) {
        throw new DomainException("$name est déjà arrêtée.");
    }

    if ($action === 'start' && $managed === 1) {
        $rows = swarmSnapshot($endpoint, $swarmId, [$stack]);

        foreach ($rows as $row) {
            if ($row['name'] === $name && $row['state'] === 'active') {
                throw new DomainException("$name : les réplicas demandés sont déjà en cours d’exécution.");
            }
        }

        if (!empty($stack['GitConfig'])) {
            throw new DomainException('Cette stack Git doit être redéployée depuis Portainer.');
        }

        [$fileStatus, $fileBody] = portainerRequest("stacks/$id/file");
        $file = $fileStatus === 200 ? json_decode($fileBody, true, 512, JSON_THROW_ON_ERROR) : [];

        if (!is_string($file['StackFileContent'] ?? null) || trim($file['StackFileContent']) === '') {
            throw new DomainException('Impossible de relire le modèle enregistré dans Portainer.');
        }

        // Réappliquer LE fichier de CETTE stack, avec ses mots de passe et volumes.
        // Ne jamais utiliser le modèle générique ni regénérer les identifiants SQL.
        $method = 'PUT';
        $path = "stacks/$id";
        $payload = [
            'StackFileContent'=>$file['StackFileContent'],
            'Env'=>$stack['Env'] ?? [],
            'Prune'=>false,
            'PullImage'=>false,
            'RepullImageAndRedeploy'=>false,
        ];
    }

    [$status] = portainerRequest(
        $path . '?endpointId=' . rawurlencode($endpoint), $method, $payload
    );

    if ($status < 200 || $status >= 300) {
        throw new DomainException("Opération refusée pour $name (Portainer HTTP $status).");
    }

    $_SESSION['dashboard_notice'] = match ($action) {
        'start' => "$name : demande de démarrage acceptée. Suivez les réplicas dans le tableau.",
        'stop' => "$name : demande d’arrêt acceptée. Portainer retire les services Swarm et conserve le fichier de stack ; les volumes nommés sont conservés.",
        'delete' => "$name : demande de suppression de la stack acceptée. Les volumes nommés ne sont pas supprimés par cette action.",
    };

} catch (DomainException $error) {
    $_SESSION['dashboard_notice'] = $error->getMessage();
} catch (Throwable $error) {
    $_SESSION['dashboard_notice'] = 'Résultat indisponible. Vérifiez l’état de la stack avant de réessayer.';
}

header('Location: dashboard.php#universites', true, 303);
exit;
