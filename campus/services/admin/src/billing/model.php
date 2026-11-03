<?php
// Les lectures Elasticsearch portent uniquement sur les totaux horaires de la stack choisie.
function meteredTotals(string $slug, string $month): array
{
    $fields = ['core_hours', 'gib_hours', 'traffic_gib'];
    $aggs = [];
    foreach ($fields as $field) {
        $aggs[$field] = ['sum' => ['field' => $field]];
    }
    $query = [
        'size' => 0,
        'query' => [
            'bool' => [
                'filter' => [['term' => ['university' => $slug]], ['term' => ['period' => $month]]]
            ]
        ],
        'aggs' => $aggs
    ];
    $ch = curl_init('http://elasticsearch:9200/campus-billing-hours-v1/_search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($query, JSON_THROW_ON_ERROR)
    ]);
    $raw = curl_exec($ch);
    if (!$raw || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        throw new DomainException('Le registre de consommation est indisponible.');
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($data['timed_out'] || $data['_shards']['failed'] || !$data['hits']['total']['value']) {
        throw new DomainException('Aucun relevé exploitable pour ce mois et cette université.');
    }
    return array_map(fn($agg) => $agg['value'], $data['aggregations']);
}

// Réunir le contact, les prix et les compteurs du mois pour calculer l’aperçu.
function invoicePreview(mysqli $db, string $slug, string $month): array
{
    $contact = $db
        ->execute_query('SELECT * FROM billing_contacts WHERE slug=?', [$slug])
        ->fetch_assoc();
    $settings = $db->query('SELECT * FROM billing_settings WHERE id=1')->fetch_assoc();
    if (!$contact || !$settings) {
        throw new DomainException(
            'Enregistrez le contact, l’émetteur et les tarifs avant de préparer une facture.'
        );
    }
    $last = $db
        ->execute_query(
            'SELECT id,snapshot FROM billing_invoices WHERE slug=? AND period=? ORDER BY id DESC LIMIT 1',
            [$slug, $month]
        )
        ->fetch_assoc();
    $previous = $last
        ? json_decode($last['snapshot'], true, 512, JSON_THROW_ON_ERROR)['meters']
        : [];
    $meters = meteredTotals($slug, $month);
    $lines = [];
    $subtotal = 0;
    // Factures complémentaires : le forfait n’est dû qu’une fois par mois.
    // Soustraire les cumuls déjà facturés inclut aussi les relevés arrivés en retard.
    foreach (
        [
            ['Forfait mensuel', 'mois', 'monthly', null],
            ['Processeur', 'cœur-h', 'cpu', 'core_hours'],
            ['Mémoire', 'Gio-h', 'memory', 'gib_hours'],
            ['Réseau entrant et sortant', 'Gio', 'network', 'traffic_gib']
        ]
        as [$label, $unit, $rate, $field]
    ) {
        $qty = $field ? $meters[$field] - ($previous[$field] ?? 0) : (int) !$last;
        if ($qty < -0.0000001) {
            throw new DomainException(
                'Compteur inférieur au cumul déjà facturé. Vérifiez le registre.'
            );
        }
        $qty = max(0, $qty);
        $cents = (int) round($qty * (float) $settings[$rate] * 100);
        $subtotal += $cents;
        $lines[] = [
            'label' => $label,
            'unit' => $unit,
            'qty' => $qty,
            'rate' => $settings[$rate],
            'cents' => $cents
        ];
    }
    $tax = (int) round(($subtotal * (float) $settings['vat']) / 100);
    return [
        'slug' => $slug,
        'month' => $month,
        'contact' => $contact,
        'settings' => $settings,
        'meters' => $meters,
        'base_id' => (int) ($last['id'] ?? 0),
        'lines' => $lines,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $subtotal + $tax
    ];
}

// Protéger les caractères spéciaux des noms et adresses quand ils sont affichés en HTML.
function esc($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
// Les montants des lignes sont stockés en centimes pour additionner les arrondis affichés.
function euros(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ') . ' €';
}
function numberFr(float $n): string
{
    return rtrim(rtrim(number_format($n, 6, ',', ' '), '0'), ',');
}

function invoiceDocument(array $bill, string $title, string $date): string
{
    // Le même document autonome est utilisé à l’écran, au téléchargement et en pièce jointe.
    ob_start();
    require __DIR__ . '/../views/invoice.php';
    return ob_get_clean();
}
