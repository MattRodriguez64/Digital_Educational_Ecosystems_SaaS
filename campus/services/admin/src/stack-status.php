<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('{}');
}

// Libérer la session avant les appels réseau pour ne pas bloquer une navigation.
session_write_close();

require __DIR__ . '/portainer.php';

function university(string $name): array {
    return [
        'name' => $name,
        'label' => $name,
        'country' => '',
        'total' => 0,
        'running' => 0,
        'stopped' => 0,
        'healthy' => 0,
        'id' => null,
        'managed' => null
    ];
}

try {
    $endpoint = getenv('PORTAINER_ENDPOINT_ID');
    $rows = [];
    // On liste les stacks univ-* pour le dahsboard
    foreach (portainerList('stacks') as $stack) {
        $name = $stack['Name'];
        if ((string) $stack['EndpointId'] !== $endpoint || !str_starts_with($name, 'univ-')) {
            continue;
        }
        $row = university($name);
        // La réponse Portainer contient aussi des secrets. Seuls ces deux champs sont publics au dashboard.
        $env = array_column($stack['Env'] ?? [], 'value', 'name');
        $row['label'] = $env['UNIV_NAME'] ?? $name;
        $row['country'] = $env['UNIV_COUNTRY'] ?? '';
        $row['id'] = (int) $stack['Id'];
        $row['managed'] = (int) $stack['Status'];
        $rows[$name] = $row;
    }

    // all=1 inclut les conteneurs arrêtés. Le label Compose identifie la stack sans analyser les noms.
    foreach (
        portainerList('endpoints/' . rawurlencode($endpoint) . '/docker/containers/json?all=1')
        as $container
    ) {
        $name = $container['Labels']['com.docker.compose.project'] ?? '';
        if (!str_starts_with($name, 'univ-')) {
            continue;
        }
        $rows[$name] ??= university($name);
        $rows[$name]['total']++;
        if ($container['State'] === 'running') {
            $rows[$name]['running']++;
            if (!preg_match('/\((unhealthy|health: starting)\)/', $container['Status'])) {
                $rows[$name]['healthy']++;
            }
        } elseif (in_array($container['State'], ['exited', 'dead', 'created'], true)) {
            $rows[$name]['stopped']++;
        }
    }

    foreach ($rows as &$row) {
        if ($row['total'] === 0) {
            $row['state'] = $row['managed'] === 2 ? 'stopped' : 'empty';
        } elseif ($row['healthy'] === $row['total']) {
            $row['state'] = 'active';
        } elseif ($row['stopped'] === $row['total']) {
            $row['state'] = 'stopped';
        } else {
            $row['state'] = 'partial';
        }
    }
    
    unset($row);
    ksort($rows, SORT_NATURAL);
    echo json_encode(['stacks' => array_values($rows), 'at' => gmdate('c')], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"error":"Relevé Portainer indisponible"}';
}
