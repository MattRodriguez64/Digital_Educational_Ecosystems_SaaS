<?php
declare(strict_types=1);

function put(string $path, array $payload): void
{
    $ch = curl_init('http://elasticsearch:9200/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR)
    ]);
    if (curl_exec($ch) === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new RuntimeException("Initialisation Elasticsearch impossible : $path");
    }
}

// Indices quotidiens avec expiration automatique, afin de borner l'espace disque.
// Les cumuls horaires de facturation survivent à la rétention du dashboard.
put('_index_template/campus-billing-hours', [
    'index_patterns' => ['campus-billing-hours-*'],
    'template' => [
        'settings' => ['number_of_shards' => 1, 'number_of_replicas' => 0],
        'mappings' => [
            'dynamic' => false,
            'properties' => [
                'university' => ['type' => 'keyword'],
                'period' => ['type' => 'keyword'],
                'core_hours' => ['type' => 'double'],
                'gib_hours' => ['type' => 'double'],
                'traffic_gib' => ['type' => 'double']
            ]
        ]
    ]
]);
put('_ilm/policy/campus-usage', [
    'policy' => [
        'phases' => [
            'delete' => ['min_age' => '30d', 'actions' => ['delete' => (object) []]]
        ]
    ]
]);
$fields = [
    '@timestamp' => 'date',
    'university' => 'keyword',
    'running' => 'integer',
    'cpu_pct' => 'double',
    'memory_mib' => 'double',
    'rx_kib_s' => 'double',
    'tx_kib_s' => 'double'
];
put('_index_template/campus-usage', [
    'index_patterns' => ['campus-usage-*'],
    'template' => [
        'settings' => [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'index.lifecycle.name' => 'campus-usage'
        ],
        'mappings' => ['properties' => array_map(fn($type) => ['type' => $type], $fields)]
    ]
]);

// Horodater les contributions déjà appliquées assure un rejeu sans double facturation.
put('_scripts/campus-billing-sample', [
    'script' => [
        'lang' => 'painless',
        'source' => <<<'PAINLESS'
        if (ctx._source.seen == null) {
            ctx._source.seen = [];
            ctx._source.university = params.university;
            ctx._source.period = params.period;
            ctx._source.core_hours = 0.0;
            ctx._source.gib_hours = 0.0;
            ctx._source.traffic_gib = 0.0;
        }
        if (ctx._source.seen.contains(params.t)) {
            ctx.op = 'none';
        } else {
            ctx._source.seen.add(params.t);
            ctx._source.core_hours += params.cpu;
            ctx._source.gib_hours += params.memory;
            ctx._source.traffic_gib += params.network;
        }
        PAINLESS
    ]
]);
