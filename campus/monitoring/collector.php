<?php
declare(strict_types=1);

// Le socket permet d'interroger le moteur local sans distribuer la clé Portainer.
// Les seuls appels Docker de ce collecteur sont des GET.
function request(string $url, ?array $body = null, bool $docker = false): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
    if ($docker) {
        curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, '/var/run/docker.sock');
    }
    if ($body !== null) {
        curl_setopt_array($ch, [
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
    }
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($result === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("Appel impossible ($status) : $url");
    }
    return $result;
}

function dockerRead(string $path): array
{
    return json_decode(
        request('http://localhost' . $path, null, true),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

// Un passage lit les conteneurs et additionne leurs mesures par université.
// Le passage précédent sert de référence pour calculer les débits.
function collect(array &$previous): void
{
    $version = dockerRead('/version')['ApiVersion'];
    $base = '/v' . $version . '/containers';
    $samples = [];
    $next = [];
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    foreach (dockerRead($base . '/json?all=1') as $container) {
        $name = $container['Labels']['com.docker.compose.project'] ?? '';
        if (!preg_match('/^univ-[a-z0-9][a-z0-9-]*$/D', $name)) {
            continue;
        }
        $samples[$name] ??= [
            'university' => $name,
            'sampled_at' => $timestamp,
            'running' => 0,
            'memory_mib' => 0.0,
            'cpu_pct' => 0.0,
            'rx_kib_s' => 0.0,
            'tx_kib_s' => 0.0,
            'core_hours' => 0.0,
            'gib_hours' => 0.0,
            'traffic_gib' => 0.0
        ];
        if ($container['State'] !== 'running') {
            continue;
        }
        $id = $container['Id'];
        $stats = dockerRead("$base/$id/stats?stream=false&one-shot=true");
        $memory = $stats['memory_stats'];
        $cpu = $stats['cpu_stats']['cpu_usage']['total_usage'] ?? null;
        if (!isset($memory['usage'], $cpu)) {
            throw new RuntimeException('Statistiques Docker incomplètes');
        }
        $samples[$name]['running']++;
        // Comme docker stats sous Linux, retrancher le cache de fichiers inactif.
        $cache =
            $memory['stats']['inactive_file'] ?? ($memory['stats']['total_inactive_file'] ?? 0);
        $samples[$name]['memory_mib'] += max(0, $memory['usage'] - $cache) / 1048576;
        $interfaces = $stats['networks'] ?? [];
        $current = [
            'time' => hrtime(true),
            'cpu_pct' => $cpu,
            'memory' => max(0, $memory['usage'] - $cache),
            'rx_kib_s' => array_sum(array_column($interfaces, 'rx_bytes')),
            'tx_kib_s' => array_sum(array_column($interfaces, 'tx_bytes'))
        ];
        $next[$id] = $current;
        // Facturation sur deux observations rapprochées du même conteneur. Une interruption
        // ou un compteur remis à zéro ne doit pas créer une consommation estimée.
        $prior = $previous[$id] ?? null;
        if ($prior && ($seconds = ($current['time'] - $prior['time']) / 1e9) <= 90) {
            $samples[$name]['core_hours'] += max(0, $cpu - $prior['cpu_pct']) / 3.6e12;
            $meanBytes = ($current['memory'] + $prior['memory']) / 2;
            $samples[$name]['gib_hours'] += (($meanBytes / 1073741824) * $seconds) / 3600;
            foreach (['rx_kib_s', 'tx_kib_s'] as $counter) {
                $samples[$name]['traffic_gib'] +=
                    max(0, $current[$counter] - $prior[$counter]) / 1073741824;
            }
        }
        foreach (
            ['cpu_pct' => 100, 'rx_kib_s' => 1e9 / 1024, 'tx_kib_s' => 1e9 / 1024]
            as $metric => $factor
        ) {
            $before = $previous[$id] ?? null;
            // Au démarrage ou après remise à zéro d'un compteur, ignorer le débit
            // de la stack entière pour ne pas publier une somme partielle comme complète.
            if (!$before || $current[$metric] < $before[$metric]) {
                $samples[$name][$metric] = null;
            } elseif ($samples[$name][$metric] !== null) {
                $delta = $current[$metric] - $before[$metric];
                $samples[$name][$metric] +=
                    ($delta * $factor) / ($current['time'] - $before['time']);
            }
        }
    }
    $previous = $next;
    if ($samples) {
        request('http://logstash:8080', array_values($samples));
    }
}

$previous = [];
while (true) {
    try {
        collect($previous);
    } catch (Throwable $error) {
        error_log($error->getMessage());
    }
    sleep(30);
}
