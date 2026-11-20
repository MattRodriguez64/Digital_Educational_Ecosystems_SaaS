<?php


declare(strict_types=1);

// CLI only; installed outside Apache's document root by the existing Dockerfile
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function kibanaRequest(string $method, string $path, ?array $payload = null): array {
    $base = rtrim(getenv('KIBANA_INTERNAL_URL') ?: 'http://campus-monitoring_kibana:5601', '/');
    $headers = ['Accept: application/json', 'kbn-xsrf: campus'];

    if ($key = getenv('KIBANA_API_KEY')) $headers[] = 'Authorization: ApiKey ' . $key;
    $curl = curl_init($base . '/api/' . $path);

    try {

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_NOPROXY => '*',
        ]);

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($curl);
        if ($body === false) throw new RuntimeException('Connexion Kibana impossible : ' . curl_error($curl));

        return [(int)curl_getinfo($curl, CURLINFO_HTTP_CODE), $body];
    } finally {
        curl_close($curl);
    }
}

function campusDashboard(string $title, string $marker, array $metrics): array {
    $panels = [];

    foreach ($metrics as $field => $label) {
        // Same inline chart structure as the project's original dashboards.php
        // No panel query.language property: the public API differs from browser URL state
        $panels[] = [
            'grid' => ['x'=>0, 'y'=>count($panels)*15, 'w'=>48, 'h'=>15],
            'type' => 'vis',
            'config' => [
                'type'=>'xy', 'title'=>$label . ' — université / nœud',
                'layers'=>[[
                    'type'=>'line',
                    'data_source'=>[
                        'type'=>'data_view_spec',
                        'index_pattern'=>'campus-usage-swarm-*',
                        'time_field'=>'@timestamp',
                    ],
                    'x'=>['operation'=>'date_histogram', 'field'=>'@timestamp'],
                    'y'=>[['operation'=>'average', 'field'=>$field]],
                    'breakdown_by'=>[
                        'operation'=>'terms', 'fields'=>['university', 'node_id'], 'limit'=>100,
                    ],
                ]],
            ],
        ];
    }

    $panels[] = [
        'grid'=>['x'=>0, 'y'=>30, 'w'=>48, 'h'=>5],
        'type'=>'markdown',
        'config'=>['content'=>'Chaque série représente une université sur un nœud. '
            . 'Les points sont des moyennes des relevés disponibles, pas des totaux entre nœuds. '
            . 'Le tableau Gestion affiche les totaux actuels. Une absence de relevés ne vaut pas zéro. '
            . '100 % CPU = un cœur ; mémoire hors cache inactif ; réseau interne inclus. '
            . 'Jusqu’à 100 séries : filtrer par université pour affiner.'],
    ];

    return ['title'=>$title, 'description'=>$marker, 'panels'=>$panels,
            'time_range'=>['from'=>'now-1h', 'to'=>'now']];
}

try {
    $definitions = [
        'a5c8370a-9106-44f6-a339-3c573c4738d1'=>campusDashboard(
            'Campus — CPU et mémoire', 'campus-swarm-dashboard:v1:campus-resources',
            ['cpu_pct'=>'CPU moyen (% d’un cœur)', 'memory_mib'=>'Mémoire moyenne (Mio)']),
        'a5c8370a-9106-44f6-a339-3c573c4738d2'=>campusDashboard(
            'Campus — Réseau', 'campus-swarm-dashboard:v1:campus-network',
            ['rx_kib_s'=>'Réception moyenne (Kio/s)', 'tx_kib_s'=>'Émission moyenne (Kio/s)']),
    ];

    // Allows reviewing the exact request without connecting to Kibana
    if (in_array('--dry-run', $argv, true)) {
        echo json_encode($definitions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
        exit(0);
    }

    foreach ($definitions as $id => $definition) {
        $path = 'dashboards/' . $id;
        [$status, $body] = kibanaRequest('GET', $path);
        if ($status === 200) {
            $existing = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (($existing['data']['description'] ?? '') !== $definition['description']) {
                throw new RuntimeException("Identifiant occupé par un autre dashboard : $id. Aucune modification.");
            }
            echo 'Conservé : ', $definition['title'], "\n";
            continue;
        }

        if ($status !== 404) throw new RuntimeException("Lecture HTTP $status : $body");

        [$status, $body] = kibanaRequest('PUT', $path, $definition);

        if (!in_array($status, [200, 201], true)) {
            throw new RuntimeException("Création de {$definition['title']} — HTTP $status : $body");
        }
        
        echo 'Créé : ', $definition['title'], "\n";
    }
    echo "Les deux dashboards sont disponibles.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Échec : ' . $error->getMessage() . "\n");
    exit(1);
}
