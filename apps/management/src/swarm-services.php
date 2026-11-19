<?php
// Compteurs Swarm. Aucun conteneur du noeud local n'est utilisé
function swarmUniversityRows(array $stacks, array $services, array $tasks, string $endpoint, string $swarmId): array {
    $rows = [];
    
    $blank = static fn(string $name): array => [
        'name'=>$name, 'label'=>$name, 'country'=>'', 'total'=>0, 'running'=>0,
        'stopped'=>0, 'healthy'=>0, 'id'=>null, 'managed'=>null,
        'services'=>0, 'disabled_services'=>0, 'transitioning'=>false,
        'health_basis'=>'swarm_task_state', 'application_health_verified'=>false,
    ];

    foreach ($stacks as $stack) {
        $name = $stack['Name'] ?? '';

        if (!is_string($name) || !str_starts_with($name, 'univ-')
            || (string)($stack['EndpointId'] ?? '') !== $endpoint
            || (int)($stack['Type'] ?? 0) !== 1
            || (string)($stack['SwarmId'] ?? '') !== $swarmId) {
            continue;
        }

        $row = $blank($name);
        $env = array_column($stack['Env'] ?? [], 'value', 'name');
        $row['label'] = (string)($env['UNIV_NAME'] ?? $name);
        $row['country'] = (string)($env['UNIV_COUNTRY'] ?? '');
        $row['id'] = (int)$stack['Id'];
        $row['managed'] = (int)($stack['Status'] ?? 0);
        $rows[$name] = $row;
    }

    $counts = [];

    foreach ($tasks as $task) {
        // Les anciennes tâches échouées/arrêtées ne sont pas des réplicas attendus.
        if (($task['DesiredState'] ?? '') === 'running'
            && ($task['Status']['State'] ?? '') === 'running') {
            $id = $task['ServiceID'] ?? '';
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }
    }

    foreach ($services as $service) {
        $name = $service['Spec']['Labels']['com.docker.stack.namespace'] ?? '';

        if (!is_string($name) || !str_starts_with($name, 'univ-')) {
            continue;
        }

        $rows[$name] ??= $blank($name); // Stacks CLI visibles mais sans boutons Portainer.
        $row = &$rows[$name];
        $mode = $service['Spec']['Mode'] ?? [];

        if (array_key_exists('Replicated', $mode)) {
            $desired = (int)($mode['Replicated']['Replicas'] ?? 1);
        } elseif (array_key_exists('Global', $mode)
            && isset($service['ServiceStatus']['DesiredTasks'])) {
            $desired = (int)$service['ServiceStatus']['DesiredTasks'];
        } else {
            throw new RuntimeException('Mode de service non pris en charge');
        }

        $running = (int)($counts[$service['ID']] ?? 0);
        $row['services']++;
        $row['disabled_services'] += $desired === 0 ? 1 : 0;
        $row['total'] += $desired;
        $row['running'] += $running;
        $row['stopped'] += max(0, $desired - $running);
        // Champ historique conservé pour compatibilité ; pas un test HTTP.
        $row['healthy'] += min($running, $desired);
        $update = $service['UpdateStatus']['State'] ?? '';

        if ($running !== $desired || in_array($update, [
            'updating', 'paused', 'rollback_started', 'rollback_paused'
        ], true)) {
            $row['transitioning'] = true;
        }

        unset($row);
    }

    foreach ($rows as &$row) {
        if ($row['services'] === 0) {
            $row['state'] = $row['managed'] === 2 ? 'stopped' : 'empty';
        } elseif ($row['total'] === 0 && $row['running'] === 0) {
            $row['state'] = 'stopped';
        } elseif ($row['total'] > 0 && !$row['transitioning']
            && $row['disabled_services'] === 0
            && ($row['managed'] === null || $row['managed'] === 1)) {
            $row['state'] = 'active';
        } else {
            $row['state'] = 'partial';
        }
    }

    unset($row);
    ksort($rows, SORT_NATURAL);

    return array_values($rows);
}

function swarmSnapshot(string $endpoint, string $swarmId, array $stacks): array {
    $base = 'endpoints/' . rawurlencode($endpoint) . '/docker/';
    $filters = rawurlencode(json_encode(
        ['label'=>['com.docker.stack.namespace']], JSON_THROW_ON_ERROR
    ));
    $services = portainerList($base . 'services?status=true&filters=' . $filters);
    $taskFilters = rawurlencode(json_encode(
        ['desired-state'=>['running']], JSON_THROW_ON_ERROR
    ));
    $tasks = portainerList($base . 'tasks?filters=' . $taskFilters);
    return swarmUniversityRows($stacks, $services, $tasks, $endpoint, $swarmId);
}

function assertUniversityStack(array $stack, int $id, string $name, string $endpoint, string $swarmId): void {
    if ((int)($stack['Id'] ?? 0) !== $id || ($stack['Name'] ?? null) !== $name
        || (string)($stack['EndpointId'] ?? '') !== $endpoint
        || (int)($stack['Type'] ?? 0) !== 1
        || (string)($stack['SwarmId'] ?? '') !== $swarmId) {
        throw new DomainException('La sélection ne correspond pas à une stack universitaire de ce Swarm.');
    }
}
