<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function scaleReply(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_id'])) scaleReply(401, ['error'=>'Connexion administrateur requise.']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    scaleReply(405, ['error'=>'Méthode interdite.']);
}
if (!is_string($_SESSION['csrf'] ?? null) || $_SESSION['csrf'] === ''
    || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    scaleReply(403, ['error'=>'Session expirée : rechargez la page.']);
}
session_write_close();

$name = $_POST['name'] ?? '';
$action = $_POST['action'] ?? '';
if (!is_string($name) || strlen($name) > 63
    || !preg_match('/^univ-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $name)
    || !in_array($action, ['status', 'high', 'low'], true)) {
    scaleReply(422, ['error'=>'Sélectionnez une université.']);
}

require_once __DIR__ . '/portainer.php';
require_once __DIR__ . '/swarm-services.php';

try {
    $endpoint = portainerRequiredEnv('PORTAINER_ENDPOINT_ID');
    $swarm = portainerSwarmId($endpoint);
    $stack = null;
    foreach (portainerList('stacks') as $candidate) {
        if (($candidate['Name'] ?? '') === $name
            && (string)($candidate['EndpointId'] ?? '') === $endpoint
            && (string)($candidate['SwarmId'] ?? '') === $swarm
            && (int)($candidate['Type'] ?? 0) === 1) {
            $stack = $candidate;
            break;
        }
    }

    if ($stack === null || (int)($stack['Status'] ?? 0) !== 1) {
        throw new DomainException('Le test nécessite une stack universitaire active gérée par Portainer.');
    }

    $base = 'endpoints/' . rawurlencode($endpoint) . '/docker/';
    [$code, $body] = portainerRequest($base . 'services/' . rawurlencode($name . '_portail'));
    if ($code !== 200) throw new DomainException('Portail introuvable dans ce Swarm.');
    // Garder les objets JSON (y compris les objets vides) du Spec inta
    $service = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
    $spec = $service->Spec ?? null;

    if (($spec->Name ?? '') !== $name . '_portail'
        || ($spec->Labels->{'com.docker.stack.namespace'} ?? '') !== $name
        || !isset($spec->Mode->Replicated->Replicas, $service->ID, $service->Version->Index)) {
        throw new DomainException('Ce service ne correspond pas à un portail répliqué de cette université.');
    }

    $desired = (int)$spec->Mode->Replicated->Replicas;
    $update = $service->UpdateStatus->State ?? '';
    $blocked = in_array($update, ['updating','paused','rollback_started','rollback_paused'], true);
    $load = $spec->Labels->{'campus.demo.load'} ?? null;
    $changed = false;
    if ($action !== 'status') {
        if ($blocked || !in_array($desired, [1, 2], true)) {
            throw new DomainException('Test indisponible : le portail doit avoir 1 ou 2 réplicas et aucune mise à jour en cours ou en pause.');
        }

        // Valeur synthétique uniquement : aucune écriture dans les mesures ou la facturation
        $load = $action === 'high' ? 90 : 40;
        $target = $load >= 90 ? 2 : ($load < 50 ? 1 : $desired);
        if ($desired !== $target || ($spec->Labels->{'campus.demo.load'} ?? '') !== (string)$load) {
            $spec->Mode->Replicated->Replicas = $target;
            $spec->Labels->{'campus.demo.load'} = (string)$load;
            [$code] = portainerRequest($base . 'services/' . rawurlencode($service->ID)
                . '/update?version=' . rawurlencode((string)$service->Version->Index)
                . '&registryAuthFrom=spec', 'POST', get_object_vars($spec));
            if ($code !== 200) {
                throw new DomainException("Modification non confirmée (HTTP $code). Actualisez avant de réessayer.");
            }
            $changed = true;
        }
        $desired = $target;
    }

    $filters = rawurlencode(json_encode(['service'=>[$service->ID]], JSON_THROW_ON_ERROR));
    $tasks = portainerList($base . 'tasks?filters=' . $filters);
    $running = 0;

    foreach ($tasks as $task) {
        if (($task['ServiceID'] ?? '') === $service->ID && ($task['DesiredState'] ?? '') === 'running'
            && ($task['Status']['State'] ?? '') === 'running') $running++;
    }

    scaleReply(200, ['name'=>$name, 'simulated_load'=>$load === null ? null : (int)$load,
        'desired'=>$desired, 'running'=>$running, 'changed'=>$changed,
        'blocked'=>$blocked, 'update_state'=>$update]);

} catch (DomainException $error) {
    scaleReply(409, ['error'=>$error->getMessage()]);
} catch (Throwable $error) {

    scaleReply(502, ['error'=>'État Portainer indisponible. Une demande peut avoir été acceptée : actualisez avant de réessayer.']);
}
