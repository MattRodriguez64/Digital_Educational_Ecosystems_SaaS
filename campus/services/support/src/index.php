<?php
declare(strict_types=1);
session_start();

$dataDir = __DIR__ . '/data';
$dataFile = $dataDir . '/tickets.json';
$errors = [];

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function readTickets(string $path): array {
    if (!is_file($path)) return [];
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}
function badgeClass(string $value): string {
    return match ($value) {
        'Ouvert', 'Haute' => 'red',
        'En cours', 'Moyenne' => 'amber',
        'Résolu', 'Basse' => 'green',
        default => 'gray'
    };
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$tickets = readTickets($dataFile);
$old = ['title' => '', 'category' => 'Technique', 'priority' => 'Moyenne', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'category' => (string)($_POST['category'] ?? ''),
        'priority' => (string)($_POST['priority'] ?? ''),
        'description' => trim((string)($_POST['description'] ?? '')),
    ];
    if (!hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) $errors[] = 'Session expirée : actualisez la page.';
    if (mb_strlen($old['title']) < 4 || mb_strlen($old['title']) > 120) $errors[] = 'Le titre doit contenir entre 4 et 120 caractères.';
    if (mb_strlen($old['description']) < 10 || mb_strlen($old['description']) > 3000) $errors[] = 'La description doit contenir entre 10 et 3 000 caractères.';
    if (!in_array($old['category'], ['Technique', 'Compte', 'Facturation', 'Autre'], true)) $errors[] = 'Catégorie invalide.';
    if (!in_array($old['priority'], ['Basse', 'Moyenne', 'Haute'], true)) $errors[] = 'Priorité invalide.';

    if (!$errors) {
        if (!is_dir($dataDir) && !mkdir($dataDir, 0700, true) && !is_dir($dataDir)) {
            $errors[] = 'Impossible de créer le dossier de stockage.';
        } else {
            // Verrouillage pour éviter de perdre des tickets lors de soumissions simultanées.
            $handle = fopen($dataFile, 'c+');
            if (!$handle || !flock($handle, LOCK_EX)) {
                $errors[] = 'Impossible de sauvegarder le ticket.';
            } else {
                rewind($handle);
                $content = stream_get_contents($handle);
                $current = $content ? json_decode($content, true) : [];
                if (!is_array($current)) $current = [];
                $current[] = [
                    'id' => strtoupper(substr(bin2hex(random_bytes(5)), 0, 8)),
                    'title' => $old['title'],
                    'category' => $old['category'],
                    'priority' => $old['priority'],
                    'description' => $old['description'],
                    'status' => 'Ouvert',
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                rewind($handle);
                if ($json === false || !ftruncate($handle, 0) || fwrite($handle, $json) === false || !fflush($handle)) {
                    $errors[] = 'Erreur lors de l’écriture du ticket.';
                }
                flock($handle, LOCK_UN);
            }
            if ($handle) fclose($handle);
            if (!$errors) {
                $_SESSION['flash'] = 'Votre ticket a bien été créé.';
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
                exit;
            }
        }
    }
    $tickets = readTickets($dataFile);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$tickets = array_reverse($tickets);
$total = count($tickets);
$open = count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'Ouvert'));
$progress = count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'En cours'));
$resolved = count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'Résolu'));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HelpDesk — Centre de support</title>
<style>
:root{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#17233d;background:#f5f7fb}*{box-sizing:border-box}body{margin:0}header{background:#fff;border-bottom:1px solid #e7eaf1}.shell{max-width:1120px;margin:auto;padding:0 24px}.top{display:flex;align-items:center;justify-content:space-between;height:76px}.brand{font-size:21px;font-weight:800;letter-spacing:-.6px}.brand span{color:#5268e9}.navnote{font-size:13px;color:#758098}.avatar{background:#edf0ff;color:#5364d8;padding:10px;border-radius:50%;font-weight:800;margin-left:12px}main{padding-top:38px;padding-bottom:70px}h1{font-size:31px;letter-spacing:-1px;margin:0 0 8px}h2{font-size:19px;margin:0}.subtitle{color:#78849a;margin:0 0 28px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}.stat,.panel{background:#fff;border:1px solid #e8ebf2;border-radius:14px;box-shadow:0 5px 20px #1d336708}.stat{padding:20px}.stat small{font-size:13px;color:#78849a}.stat strong{display:block;font-size:29px;margin-top:9px}.layout{display:grid;grid-template-columns:1.45fr 1fr;gap:22px;align-items:start}.panel{padding:24px}.panel-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}.count{color:#6573d8;background:#edf0ff;padding:5px 10px;border-radius:20px;font-size:12px;font-weight:700}.ticket{padding:18px 0;border-bottom:1px solid #eef0f5}.ticket:first-of-type{padding-top:0}.ticket:last-child{border:0;padding-bottom:0}.ticket-top{display:flex;justify-content:space-between;gap:10px}.ticket-title{font-weight:700;font-size:15px;overflow-wrap:anywhere}.meta{color:#8a95a7;font-size:12px;margin-top:7px}.ticket-text{font-size:13px;color:#6e7b92;margin:10px 0 0;white-space:pre-wrap;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.badge{font-size:11px;font-weight:700;padding:5px 9px;border-radius:6px}.red{background:#ffeded;color:#c54e50}.amber{background:#fff3dc;color:#a66d13}.green{background:#e7f8ee;color:#268958}.gray{background:#edf0f5;color:#60708a}label{font-size:13px;font-weight:700;display:block;margin:17px 0 7px}input,select,textarea{width:100%;padding:12px 13px;font:inherit;font-size:14px;border:1px solid #dfe4ee;border-radius:9px;background:#fff;color:#253149;outline:none}input:focus,select:focus,textarea:focus{border-color:#6175ed;box-shadow:0 0 0 3px #6175ed1c}textarea{height:127px;resize:vertical}.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.btn{width:100%;margin-top:19px;padding:13px;background:#5367e8;border:0;border-radius:9px;color:#fff;font-size:14px;font-weight:750;cursor:pointer}.btn:hover{background:#4055d6}.notice{border-radius:10px;padding:13px 16px;margin-bottom:17px;font-size:14px}.success{background:#e9f8ee;color:#23794e}.error{background:#ffeded;color:#a93535}.empty{text-align:center;padding:45px 10px;color:#8994a7}.empty strong{display:block;color:#46536c;margin-bottom:6px}.foot{font-size:12px;color:#9ba5b5;margin-top:25px;text-align:center}@media(max-width:800px){.stats{grid-template-columns:repeat(2,1fr)}.layout{grid-template-columns:1fr}h1{font-size:26px}}@media(max-width:450px){.stats{gap:9px}.stat{padding:16px}.two{grid-template-columns:1fr}.shell{padding:0 14px}.panel{padding:18px}.navnote{display:none}}
</style>
</head>
<body>
<header><div class="shell top"><div class="brand">◈ Help<span>Desk</span></div><div><span class="navnote">Centre d'assistance</span><span class="avatar">HD</span></div></div></header>
<main class="shell">
<h1>Centre de support</h1><p class="subtitle">Créez une demande et retrouvez l'ensemble de vos tickets.</p>
<?php if ($flash): ?><div class="notice success" role="status"><?= h((string)$flash) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="notice error" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
<section class="stats" aria-label="Statistiques des tickets">
<div class="stat"><small>Total des tickets</small><strong><?= $total ?></strong></div>
<div class="stat"><small>Tickets ouverts</small><strong><?= $open ?></strong></div>
<div class="stat"><small>En cours</small><strong><?= $progress ?></strong></div>
<div class="stat"><small>Tickets résolus</small><strong><?= $resolved ?></strong></div>
</section>
<div class="layout">
<section class="panel"><div class="panel-head"><h2>Mes tickets</h2><span class="count"><?= $total ?> ticket<?= $total > 1 ? 's' : '' ?></span></div>
<?php if (!$tickets): ?><div class="empty"><strong>Aucun ticket pour le moment</strong>Votre première demande apparaîtra ici.</div><?php endif; ?>
<?php foreach ($tickets as $ticket): ?>
<article class="ticket"><div class="ticket-top"><div class="ticket-title"><?= h((string)($ticket['title'] ?? 'Sans titre')) ?></div><span class="badge <?= badgeClass((string)($ticket['status'] ?? '')) ?>"><?= h((string)($ticket['status'] ?? 'Inconnu')) ?></span></div>
<div class="meta">#<?= h((string)($ticket['id'] ?? '')) ?> · <?= h((string)($ticket['created_at'] ?? '')) ?></div>
<p class="ticket-text"><?= h((string)($ticket['description'] ?? '')) ?></p>
<div class="badges"><span class="badge gray"><?= h((string)($ticket['category'] ?? 'Autre')) ?></span><span class="badge <?= badgeClass((string)($ticket['priority'] ?? '')) ?>">Priorité <?= h((string)($ticket['priority'] ?? '')) ?></span></div>
</article>
<?php endforeach; ?>
</section>
<section class="panel"><div class="panel-head"><h2>Nouveau ticket</h2><span class="count">+ Créer</span></div>
<form method="post" action=""><input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
<label for="title">Sujet de la demande</label><input id="title" name="title" required minlength="4" maxlength="120" placeholder="Ex. : Impossible de me connecter" value="<?= h($old['title']) ?>">
<div class="two"><div><label for="category">Catégorie</label><select name="category" id="category"><?php foreach (['Technique','Compte','Facturation','Autre'] as $value): ?><option value="<?= h($value) ?>" <?= $old['category'] === $value ? 'selected' : '' ?>><?= h($value) ?></option><?php endforeach; ?></select></div><div><label for="priority">Priorité</label><select name="priority" id="priority"><?php foreach (['Basse','Moyenne','Haute'] as $value): ?><option value="<?= h($value) ?>" <?= $old['priority'] === $value ? 'selected' : '' ?>><?= h($value) ?></option><?php endforeach; ?></select></div></div>
<label for="description">Description du problème</label><textarea id="description" name="description" required minlength="10" maxlength="3000" placeholder="Décrivez votre problème avec le plus de détails possible..."><?= h($old['description']) ?></textarea>
<button type="submit" class="btn">Envoyer ma demande →</button></form></section>
</div><div class="foot">HelpDesk — Portail de support · Démonstration PHP</div>
</main>
</body>
</html>
