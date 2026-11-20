<?php
session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}
header('Cache-Control: no-store');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$db = new PDO('mysql:host=' . (getenv('DB_HOST') ?: 'mariadb') . ';dbname=support;charset=utf8mb4', 'support', 'support', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$handlerId = 'global:' . (int) $_SESSION['admin_id'];
$handlerName = 'Campus · ' . ($_SESSION['username'] ?? 'admin');

$filters = [
    'universite' => is_string($_GET['universite'] ?? null) ? $_GET['universite'] : '',
    'statut' => is_string($_GET['statut'] ?? null) ? $_GET['statut'] : '',
];
$query = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$back = 'tickets.php' . ($query !== '' ? '?' . $query : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Requête invalide.');
    }
    $uuid = (string) ($_POST['uuid'] ?? '');
    $action = $_POST['action'] ?? '';
    $message = '';

    if ($action === 'prendre') {
        $db->prepare('UPDATE tickets SET status = 1, handled_by = ?, handled_by_id = ? WHERE uuid = ? AND status IN (0, 1)')
            ->execute([$handlerName, $handlerId, $uuid]);
        $message = 'Ticket pris en charge.';
    } elseif ($action === 'traiter') {
        $stmt = $db->prepare('SELECT status, handled_by_id FROM tickets WHERE uuid = ?');
        $stmt->execute([$uuid]);
        $ticket = $stmt->fetch();
        $solution = trim((string) ($_POST['solution'] ?? ''));
        $status = (int) ($_POST['status'] ?? 1) === 2 ? 2 : 1;
        if (!$ticket || (int) $ticket['status'] !== 1 || $ticket['handled_by_id'] !== $handlerId) {
            $message = 'Ce ticket n’est pas pris en charge par vous.';
        } elseif ($status === 2 && $solution === '') {
            $message = 'Une solution est obligatoire pour clôturer.';
        } else {
            $db->prepare('UPDATE tickets SET solution = ?, status = ? WHERE uuid = ?')->execute([$solution, $status, $uuid]);
            $message = $status === 2 ? 'Ticket clôturé.' : 'Solution enregistrée.';
        }
    }
    $_SESSION['tickets_message'] = $message;
    header('Location: ' . $back, true, 303);
    exit();
}

$sql = 'SELECT * FROM tickets WHERE 1 = 1';
$params = [];
if ($filters['universite'] !== '') {
    $sql .= ' AND university_slug = ?';
    $params[] = $filters['universite'];
}
if (in_array($filters['statut'], ['0', '1', '2'], true)) {
    $sql .= ' AND status = ?';
    $params[] = (int) $filters['statut'];
}
$stmt = $db->prepare($sql . ' ORDER BY status = 2, created_at DESC');
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$universites = $db->query(
    'SELECT university_slug AS slug, MAX(university) AS name FROM tickets WHERE university_slug IS NOT NULL GROUP BY university_slug ORDER BY name'
)->fetchAll();

$message = $_SESSION['tickets_message'] ?? '';
unset($_SESSION['tickets_message']);
$statuts = ['Ouvert', 'En cours', 'Clôturé'];

require __DIR__ . '/views/tickets.php';
