<?php
// Requête sur l'API d'ES pour récupérer les données de conso de chaque Stack

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8'); // On renvoie un JSON au navigateur
header('Cache-Control: no-store'); // On stcok rien en cache , la mesure change régulièrement

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('{}');
}

session_write_close();

$query = [
    'size' => 0, // On veut pas de liste générale, on veut une réponse précise
    'aggs' => [ // On agrège et on nomme campuses
        'campuses' => [ 
            'terms' => ['field' => 'university', 'size' => 1000], // On regroupe pra universités
            'aggs' => [
                'latest' => [ // Agrégation pour avoir le DERNIER relevé
                    'top_hits' => [ // On utilise la fonction d'ES pour récupérer le relevé
                        'size' => 1,
                        'sort' => [['@timestamp' => 'desc']], // Tri DESC sur la date (pour avoir le plus récent)
                        '_source' => [ // On sélectionne les champs qu'on souhaite
                            'university',
                            '@timestamp',
                            'running',
                            'cpu_pct', // CPU
                            'memory_mib', // RAM
                            'rx_kib_s', // RESEAU Download
                            'tx_kib_s' // RESEAU Upload
                        ]
                    ]
                ]
            ]
        ]
    ]
];

try {
    // INIT de la requête HTTP sur l'index ES campus-usage-*
    $ch = curl_init('http://elasticsearch:9200/campus-usage-*/_search?allow_no_indices=true');
    // Construction du PAYLOAD
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($query, JSON_THROW_ON_ERROR)
    ]);
    // On exécute la requête et on save son résultat
    $raw = curl_exec($ch);
    // On regarde la réponse et le code HTTP
    if ($raw === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new RuntimeException('Elasticsearch indisponible');
    }

    // On convertit le JSON retourné en tableau PHP
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($data['timed_out'] || $data['_shards']['failed']) {
        throw new RuntimeException('Recherche incomplète');
    }

    $rows = [];
    // chaque bucket = une université
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
