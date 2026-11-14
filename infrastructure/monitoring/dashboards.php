<?php
declare(strict_types=1);

// Kibana attribue les identifiants. Le volume partagé transmet uniquement ces liens au menu PHP.
// Si une vue existe déjà, conserver les modifications réalisées dans Kibana.
$dashboards = [
    'campus-resources' => [
        'Ressources des universités',
        [
            'cpu_pct' => 'CPU moyen (% d’un cœur)',
            'memory_mib' => 'Mémoire moyenne (Mio)'
        ]
    ],
    'campus-network' => [
        'Réseau des universités',
        [
            'rx_kib_s' => 'Réception moyenne (Kio/s)',
            'tx_kib_s' => 'Émission moyenne (Kio/s)'
        ]
    ]
];

function callKibana(string $path, ?array $body = null): array
{
    $ch = curl_init('http://kibana:5601/api/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    if ($body !== null) {
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'kbn-xsrf: campus'],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR)
        ]);
    }
    $response = curl_exec($ch);
    return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $response];
}

// Kibana peut démarrer plus lentement qu’Elasticsearch après une installation neuve.
for ($attempt = 0; $attempt < 120; $attempt++) {
    [$status, $response] = callKibana('status');
    if (
        $status === 200 &&
        (json_decode($response, true)['status']['overall']['level'] ?? '') === 'available'
    ) {
        break;
    }
    sleep(3);
}
$file = '/links/dashboards.json';
$links = is_file($file)
    ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)
    : [];
foreach ($dashboards as $id => [$title, $metrics]) {
    [$status] = isset($links[$id]) ? callKibana('dashboards/' . rawurlencode($links[$id])) : [404];
    if ($status === 200) {
        continue;
    }
    if ($status !== 404) {
        throw new RuntimeException("Lecture du dashboard impossible ($status)");
    }
    // Chaque indicateur devient une courbe avec une série par université.
    $panels = [];
    foreach ($metrics as $field => $label) {
        $panels[] = [
            'grid' => ['x' => 0, 'y' => count($panels) * 13, 'w' => 48, 'h' => 13],
            'type' => 'vis',
            'config' => [
                'type' => 'xy',
                'title' => $label,
                'layers' => [
                    [
                        'type' => 'line',
                        'data_source' => [
                            'type' => 'data_view_spec',
                            'index_pattern' => 'campus-usage-*',
                            'time_field' => '@timestamp'
                        ],
                        'x' => ['operation' => 'date_histogram', 'field' => '@timestamp'],
                        'y' => [['operation' => 'average', 'field' => $field]],
                        'breakdown_by' => [
                            'operation' => 'terms',
                            'fields' => ['university'],
                            'limit' => 20
                        ]
                    ]
                ]
            ]
        ];
    }
    [$status, $response] = callKibana('dashboards', [
        'title' => $title,
        'panels' => $panels,
        'time_range' => ['from' => 'now-1h', 'to' => 'now']
    ]);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("Création du dashboard $id : $status $response");
    }
    $created = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    $links[$id] = $created['id'] ?? throw new RuntimeException('Identifiant Kibana absent');
    file_put_contents($file . '.tmp', json_encode($links, JSON_THROW_ON_ERROR));
    rename($file . '.tmp', $file);
}
