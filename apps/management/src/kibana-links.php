<?php

declare(strict_types=1);

// Fixed IDs provisioned by /usr/local/lib/campus/setup-kibana.php. No API key reaches the browser
$publicUrl = trim(getenv('KIBANA_PUBLIC_URL') ?: '');

if ($publicUrl === '') {
    $domain = trim(getenv('CAMPUS_BASE_DOMAIN') ?: '');
    $port = trim(getenv('PUBLIC_PORT') ?: '80');
    if ($domain !== '' && preg_match('/^[a-zA-Z0-9.-]+$/D', $domain)
        && ctype_digit($port) && (int)$port >= 1 && (int)$port <= 65535) {
        $publicUrl = 'http://kibana.' . $domain . ((int)$port === 80 ? '' : ':' . $port);
    }
}

$parts = parse_url($publicUrl);
if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
    || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
    || isset($parts['query']) || isset($parts['fragment'])) {
    return ['dashboards'=>(object)[], 'kibana_url'=>null];
}

return [
    'dashboards'=>[
        'campus-resources'=>'a5c8370a-9106-44f6-a339-3c573c4738d1',
        'campus-network'=>'a5c8370a-9106-44f6-a339-3c573c4738d2',
    ],
    'kibana_url'=>rtrim($publicUrl, '/'),
];
