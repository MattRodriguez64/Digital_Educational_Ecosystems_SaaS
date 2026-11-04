<?php
// La clé API et le choix de l’environnement restent côté serveur.
// Création de la requête Portainer
function portainerRequest(string $path, string $method = 'GET', ?array $payload = null): array
{
    $url = rtrim(getenv('PORTAINER_URL'), '/') . '/api/' . $path;
    $curl = curl_init($url);
    $headers = ['X-API-Key: ' . getenv('PORTAINER_API_KEY')];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $method === 'GET' ? 5 : 120,
        // Le Portainer interne du Compose utilise un certificat autosigné.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException('Portainer indisponible');
    }
    return [curl_getinfo($curl, CURLINFO_HTTP_CODE), $body];
}

function portainerList(string $path): array
{
    [$status, $body] = portainerRequest($path);
    if ($status !== 200) {
        throw new RuntimeException('Portainer indisponible');
    }
    $list = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($list) || !array_is_list($list)) {
        throw new RuntimeException('Réponse inattendue');
    }
    return $list;
}
