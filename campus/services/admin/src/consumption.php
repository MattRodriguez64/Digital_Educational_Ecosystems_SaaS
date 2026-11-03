<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('{}');
}
session_write_close();

// La requête est fixe. Le navigateur ne dispose ni d'un proxy Elasticsearch,
// ni de la possibilité d'interroger un autre index.
$query = [
    'size' => 0,
    'aggs' => [
        'campuses' => [
            'terms' => ['field' => 'university', 'size' => 1000],
            'aggs' => [
                'latest' => [
                    'top_hits' => [
                        'size' => 1,
                        'sort' => [['@timestamp' => 'desc']],
                        '_source' => [
                            'university',
                            '@timestamp',
                            'running',
                            'cpu_pct',
                            'memory_mib',
                            'rx_kib_s',
                            'tx_kib_s'
                        ]
                    ]
                ]
            ]
        ]
    ]
];
try {
    $ch = curl_init('http://elasticsearch:9200/campus-usage-*/_search?allow_no_indices=true');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($query, JSON_THROW_ON_ERROR)
    ]);
    $raw = curl_exec($ch);
    if ($raw === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new RuntimeException('Elasticsearch indisponible');
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($data['timed_out'] || $data['_shards']['failed']) {
        throw new RuntimeException('Recherche incomplète');
    }
    $rows = [];
    foreach ($data['aggregations']['campuses']['buckets'] as $bucket) {
        $rows[] = $bucket['latest']['hits']['hits'][0]['_source'];
    }
    $file = '/var/lib/campus/dashboards.json';
    $links = is_file($file)
        ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)
        : [];
    echo json_encode(['samples' => $rows, 'dashboards' => $links], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"error":"Mesures indisponibles"}';
}
