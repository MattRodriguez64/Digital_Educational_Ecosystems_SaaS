<?php
// API interne Portainer ; les identifiants ne sont jamais envoyés au navigateur//
function portainerRequiredEnv(string $name): string {
    $value = getenv($name);

    if ($value === false || trim($value) === '') {
        throw new RuntimeException('Configuration manquante : ' . $name);
    }

    return $value;
}

function portainerRequest(string $path, string $method = 'GET', ?array $payload = null): array {
    $base = rtrim(portainerRequiredEnv('PORTAINER_URL'), '/');

    if (!in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)) {
        throw new RuntimeException('PORTAINER_URL invalide');
    }

    $key = portainerRequiredEnv('PORTAINER_API_KEY');
    $json = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR);
    $curl = curl_init($base . '/api/' . ltrim($path, '/'));

    if ($curl === false) {
        throw new RuntimeException('Initialisation HTTP impossible');
    }

    // Compatibilité avec le certificat autosigné de l'installation actuelle
    // Pour vérifier un certificat de confiance : PORTAINER_TLS_VERIFY=true
    $verify = filter_var(getenv('PORTAINER_TLS_VERIFY') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    try {
        $headers = ['X-API-Key: ' . $key, 'Accept: application/json'];
        
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $method === 'GET' ? 15 : 120,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            // Portainer est interne : ne pas passer par le proxy universitaire.
            CURLOPT_NOPROXY => '*',
        ]);

        $body = curl_exec($curl);

        if ($body === false) {
            throw new RuntimeException(
                'Portainer : erreur de transport ' . curl_errno($curl) .
                '. Vérifier son état avant de réessayer un déploiement.'
            );
        }

        return [(int) curl_getinfo($curl, CURLINFO_HTTP_CODE), $body];
    } finally {
        curl_close($curl);
    }
}

function portainerList(string $path): array {
    [$status, $body] = portainerRequest($path);

    if ($status !== 200) {
        throw new RuntimeException('Portainer indisponible (HTTP ' . $status . ')');
    }

    $list = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($list) || !array_is_list($list)) {
        throw new RuntimeException('Réponse Portainer inattendue');
    }

    return $list;
}

function portainerSwarmId(string $endpointId): string {

    if (!ctype_digit($endpointId) || (int) $endpointId < 1) {
        throw new RuntimeException('PORTAINER_ENDPOINT_ID invalide');
    }

    [$status, $body] = portainerRequest(
        'endpoints/' . rawurlencode($endpointId) . '/docker/info'
    );

    if ($status !== 200) {
        throw new RuntimeException('Lecture du Swarm impossible (HTTP ' . $status . ')');
    }

    $info = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $swarm = is_array($info) ? ($info['Swarm'] ?? []) : [];
    $id = $swarm['Cluster']['ID'] ?? null;

    if (($swarm['LocalNodeState'] ?? '') !== 'active'
        || ($swarm['ControlAvailable'] ?? false) !== true
        || !is_string($id) || $id === '') {
        throw new RuntimeException('L’environnement Portainer doit cibler un manager Swarm actif.');
    }

    return $id;
}
