<?php
session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}
header('Cache-Control: no-store');
require __DIR__ . '/config.php';
require __DIR__ . '/billing/model.php';
$mysqli->set_charset('utf8mb4');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

// Lire une valeur de formulaire et limiter sa taille avant de la transmettre à la base.
function field(string $key, int $max = 500): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v) || strlen($v = trim($v)) > $max) {
        throw new DomainException('Champ invalide.');
    }
    return $v;
}

function slugField(): string
{
    $slug = field('slug', 128);
    if (!preg_match('/^univ-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
        throw new DomainException('Identifiant de stack invalide.');
    }
    return $slug;
}

function returnToBilling(string $message): never
{
    $_SESSION['billing_message'] = $message;
    header('Location: billing.php', true, 303);
    exit();
}

// Pour rouvrir une facture, utiliser la copie enregistrée le jour de son émission.
// Un changement de tarif ou d’adresse ne doit pas modifier ce document.
if (isset($_GET['invoice'])) {
    $row = $mysqli
        ->execute_query('SELECT * FROM billing_invoices WHERE id=?', [(int) $_GET['invoice']])
        ->fetch_assoc();
    if (!$row) {
        http_response_code(404);
        exit('Facture introuvable.');
    }
    $bill = json_decode($row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    $ref = sprintf('FAC-%06d', $row['id']);
    $html = invoiceDocument($bill, "Facture $ref", $row['issued']);
    $format = $_GET['format'] ?? 'view';
    if ($format === 'email') {
        // Le .eml contient une vraie pièce jointe. L'administrateur contrôle l'envoi dans sa messagerie.
        $boundary = 'campus-' . bin2hex(random_bytes(12));
        $body =
            "Bonjour,\r\n\r\nVeuillez trouver en pièce jointe la facture $ref pour le mois " .
            $bill['month'] .
            ".\r\n";
        header('Content-Type: message/rfc822');
        header('Content-Disposition: attachment; filename="' . $ref . '.eml"');
        echo "X-Unsent: 1\r\nTo: " .
            $bill['contact']['email'] .
            "\r\nSubject: Facture $ref\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
        echo "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n",
            chunk_split(base64_encode($body));
        echo "--$boundary\r\nContent-Type: text/html; charset=UTF-8; name=\"$ref.html\"\r\nContent-Disposition: attachment; filename=\"$ref.html\"\r\nContent-Transfer-Encoding: base64\r\n\r\n",
            chunk_split(base64_encode($html)),
            "--$boundary--\r\n";
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        if ($format === 'download') {
            header('Content-Disposition: attachment; filename="' . $ref . '.html"');
        }
        echo $html;
    }
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Requête invalide.');
    }
    try {
        switch (field('action')) {
            case 'contact':
                $slug = slugField();
                $name = field('name', 180);
                $email = field('email', 254);
                if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new DomainException('Nom et e-mail valides requis.');
                }
                $mysqli->execute_query(
                    'INSERT INTO billing_contacts (slug, name, email, address)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                         name = VALUES(name),
                         email = VALUES(email),
                         address = VALUES(address)',
                    [$slug, $name, $email, field('address')]
                );
                returnToBilling('Contact enregistré.');
            case 'settings':
                // Les tarifs permettent six décimales pour les petites consommations.
                $issuer = field('issuer', 180);
                $address = field('address');
                if (!$issuer || !$address) {
                    throw new DomainException('Renseignez les coordonnées de l’émetteur.');
                }
                $prices = [];
                foreach (['monthly', 'cpu', 'memory', 'network', 'vat'] as $k) {
                    $value = str_replace(',', '.', field($k));
                    $pattern =
                        $k === 'vat' ? '/^\d{1,3}(?:\.\d{1,2})?$/D' : '/^\d{1,6}(?:\.\d{1,6})?$/D';
                    if (!preg_match($pattern, $value) || ($k === 'vat' && (float) $value > 100)) {
                        throw new DomainException('Vérifiez les tarifs et le taux de TVA.');
                    }
                    $prices[] = $value;
                }
                $mysqli->execute_query(
                    'INSERT INTO billing_settings (id, issuer, address, monthly, cpu, memory, network, vat)
                     VALUES (1, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                         issuer = VALUES(issuer),
                         address = VALUES(address),
                         monthly = VALUES(monthly),
                         cpu = VALUES(cpu),
                         memory = VALUES(memory),
                         network = VALUES(network),
                         vat = VALUES(vat)',
                    [$issuer, $address, ...$prices]
                );
                returnToBilling('Tarifs enregistrés. Ils s’appliquent aux nouveaux aperçus.');
            case 'preview':
                // L’aperçu reste en session pendant quinze minutes, le temps de vérifier les montants.
                $month = field('month', 7);
                if (!preg_match('/^\d{4}-(0[1-9]|1[012])$/D', $month) || $month > gmdate('Y-m')) {
                    throw new DomainException('Mois invalide.');
                }
                $preview = invoicePreview($mysqli, slugField(), $month);
                $_SESSION['bill_preview'] = [
                    'key' => bin2hex(random_bytes(16)),
                    'bill' => $preview,
                    'time' => time()
                ];
                returnToBilling('Vérifiez les quantités et les tarifs avant d’émettre.');
            case 'issue':
                $key = field('key', 32);
                $draft = $_SESSION['bill_preview'] ?? [];
                if (!$key || $key !== ($draft['key'] ?? '') || time() - $draft['time'] > 900) {
                    throw new DomainException('Recalculez l’aperçu avant émission.');
                }
                $bill = $draft['bill'];
                if ($bill['total'] <= 0) {
                    throw new DomainException('Aucun montant à facturer.');
                }
                $mysqli->begin_transaction();
                // Deux administrateurs peuvent préparer le même mois. Sérialiser l’émission
                // et refuser un aperçu dont le point de départ a déjà été facturé.
                $mysqli->execute_query(
                    'SELECT slug FROM billing_contacts WHERE slug=? FOR UPDATE',
                    [$bill['slug']]
                );
                $last = $mysqli
                    ->execute_query(
                        'SELECT id FROM billing_invoices WHERE slug=? AND period=? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                        [$bill['slug'], $bill['month']]
                    )
                    ->fetch_column();
                if ((int) $last !== $bill['base_id']) {
                    throw new DomainException(
                        'Une facture a été émise depuis cet aperçu. Recalculez-le.'
                    );
                }
                $mysqli->execute_query(
                    'INSERT INTO billing_invoices (slug,period,request_key,snapshot) VALUES(?,?,?,?)',
                    [$bill['slug'], $bill['month'], $key, json_encode($bill, JSON_THROW_ON_ERROR)]
                );
                $mysqli->commit();
                unset($_SESSION['bill_preview']);
                returnToBilling(
                    'Facture émise. Le document et le courriel sont disponibles dans l’historique.'
                );
            default:
                throw new DomainException('Action inconnue.');
        }
    } catch (Throwable $e) {
        $mysqli->rollback();
        $error =
            $e instanceof DomainException
                ? $e->getMessage()
                : 'Opération indisponible. Réessayez après vérification des services.';
        if (!$e instanceof DomainException) {
            error_log('Facturation : ' . $e->getMessage());
        }
    }
}
$settings = $mysqli->query('SELECT * FROM billing_settings WHERE id=1')->fetch_assoc() ?: [];
$contacts = $mysqli->query('SELECT * FROM billing_contacts ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$history = $mysqli
    ->query(
        'SELECT id,slug,period,issued,JSON_VALUE(snapshot,\'$.total\') AS cents FROM billing_invoices ORDER BY id DESC'
    )
    ->fetch_all(MYSQLI_ASSOC);
$message = $_SESSION['billing_message'] ?? '';
unset($_SESSION['billing_message']);
$draft = $_SESSION['bill_preview'] ?? null;
require __DIR__ . '/views/billing.php';
