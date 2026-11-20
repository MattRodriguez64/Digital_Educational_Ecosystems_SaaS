<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('{}');
}

session_write_close();
require_once __DIR__ . '/portainer.php';
require_once __DIR__ . '/swarm-services.php';

try {
    $endpoint = portainerRequiredEnv('PORTAINER_ENDPOINT_ID');
    $swarmId = portainerSwarmId($endpoint);
    $rows = swarmSnapshot($endpoint, $swarmId, portainerList('stacks'));
    // Les Env/Spec/Tasks complets contiennent des secrets : ne jamais les renvoyer
    echo json_encode(['stacks'=>$rows, 'at'=>gmdate('c')], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"error":"Relevé Swarm indisponible"}';
}

