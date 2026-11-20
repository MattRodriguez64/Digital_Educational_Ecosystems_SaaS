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
require_once __DIR__ . '/portainer.php';
require_once __DIR__ . '/swarm-services.php';
require_once __DIR__ . '/consumption-swarm.php';

try {
    $endpoint = portainerRequiredEnv('PORTAINER_ENDPOINT_ID');
    $swarm = portainerSwarmId($endpoint);
    $base = 'endpoints/' . rawurlencode($endpoint) . '/docker/';
    $services = portainerList($base . 'services?status=true');
    $tasks = portainerList($base . 'tasks?filters=' . rawurlencode(json_encode(
        ['desired-state'=>['running']], JSON_THROW_ON_ERROR)));
    $universities = swarmUniversityRows(portainerList('stacks'), $services, $tasks, $endpoint, $swarm);

    // Composite pagination avoids silently dropping universities or workers
    $sources = [['university'=>['terms'=>['field'=>'university']]],
                ['node'=>['terms'=>['field'=>'node_id']]]];
    $samples = [];
    $after = null;

    do {
        $composite = ['size'=>500, 'sources'=>$sources];

        if ($after !== null) $composite['after'] = $after;
        $query = ['size'=>0,
            'query'=>['range'=>['@timestamp'=>['gte'=>'now-2m']]],
            'aggs'=>['nodes'=>['composite'=>$composite,
                'aggs'=>['latest'=>['top_hits'=>['size'=>1,
                    'sort'=>[['@timestamp'=>'desc']],
                    '_source'=>['university','node_id','@timestamp','complete','container_ids',
                                'cpu_pct','memory_mib','rx_kib_s','tx_kib_s']]]]]]];

        $url = rtrim(getenv('ELASTICSEARCH_URL') ?: 'http://elasticsearch:9200', '/');
        $ch = curl_init($url . '/campus-usage-swarm-*/_search?allow_no_indices=true');

        try {
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8,
                CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_NOPROXY=>'*',
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                CURLOPT_POSTFIELDS=>json_encode($query, JSON_THROW_ON_ERROR)]);
            $raw = curl_exec($ch);
            if ($raw === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200)
                throw new RuntimeException('Lecture Elasticsearch impossible');
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } finally { curl_close($ch); }

        if (($data['timed_out'] ?? true) || ($data['_shards']['failed'] ?? 1))
            throw new RuntimeException('Recherche incomplète');

        $buckets = $data['aggregations']['nodes']['buckets'] ?? [];

        foreach ($buckets as $bucket) {
            $sample = $bucket['latest']['hits']['hits'][0]['_source'];
            $samples[$sample['university']][$sample['node_id']] = $sample;
        }

        $after = $buckets ? ($data['aggregations']['nodes']['after_key'] ?? null) : null;
    } while ($after !== null);
    
    $kibana = require __DIR__ . '/kibana-links.php';
    echo json_encode(['samples'=>consumptionRows($universities, $services, $tasks, $samples, time()),
                      'dashboards'=>$kibana['dashboards'], 'kibana_url'=>$kibana['kibana_url']], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('[consumption] ' . $error->getMessage());
    http_response_code(503);
    echo '{"error":"Collecte ou inventaire Swarm indisponible"}';
}
