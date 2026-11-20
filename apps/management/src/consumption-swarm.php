<?php
declare(strict_types=1);

// Never present a partial or old node sample as a cluster total.
function consumptionRows(array $universities, array $services, array $tasks, array $samples, int $now): array {
    $serviceNames = [];
    foreach ($services as $service) {
        $serviceNames[$service['ID']] = $service['Spec']['Labels']['com.docker.stack.namespace'] ?? '';
    }

    $expected = [];
    foreach ($tasks as $task) {
        $name = $serviceNames[$task['ServiceID'] ?? ''] ?? '';
        if (!str_starts_with($name, 'univ-') || ($task['DesiredState'] ?? '') !== 'running'
            || ($task['Status']['State'] ?? '') !== 'running') continue;
        $node = $task['NodeID'] ?? '';
        $id = $task['Status']['ContainerStatus']['ContainerID'] ?? '';
        $expected[$name][$node][] = $id;
    }

    $rows = [];
    $fields = ['cpu_pct', 'memory_mib', 'rx_kib_s', 'tx_kib_s'];
    foreach ($universities as $university) {
        $name = $university['name'];
        $row = ['university'=>$name, 'running'=>$university['running'],
                '@timestamp'=>null, 'coverage'=>'waiting', 'missing_nodes'=>[]];
        foreach ($fields as $field) $row[$field] = null;
        $nodes = $expected[$name] ?? [];
        if (!$nodes) {
            if ($university['state'] === 'stopped') {
                $row['coverage'] = 'stopped';
                foreach ($fields as $field) $row[$field] = 0;
            }
            $rows[] = $row;
            continue;
        }

        $totals = array_fill_keys($fields, 0.0);
        $times = [];
        foreach ($nodes as $node => $ids) {
            $sample = $samples[$name][$node] ?? null;
            $stamp = $sample ? strtotime($sample['@timestamp'] ?? '') : false;
            $seen = $sample['container_ids'] ?? [];
            sort($ids); sort($seen);
            if (!$sample || !$stamp || $stamp < $now - 90 || $stamp > $now + 15
                || !($sample['complete'] ?? false) || $ids !== $seen) {
                $row['missing_nodes'][] = $node;
                continue;
            }

            $times[] = $stamp;
            foreach ($fields as $field) {
                $value = $sample[$field] ?? null;
                if (!is_numeric($value) || (float)$value < 0) $totals[$field] = null;
                elseif ($totals[$field] !== null) $totals[$field] += (float)$value;
            }
        }

        if (!$row['missing_nodes']) {
            foreach ($fields as $field) $row[$field] = $totals[$field];
            $row['@timestamp'] = gmdate('Y-m-d\TH:i:s\Z', min($times));
            $row['coverage'] = in_array(null, $totals, true) ? 'warming' : 'complete';
        } else {
            $row['coverage'] = 'incomplete';
        }

        $rows[] = $row;
    }
    
    return $rows;
}
