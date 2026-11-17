<?php
if (!isset($tickets, $universites)) {
    http_response_code(404);
    exit;
}

function ticketForm(string $action, string $uuid): void
{
    ?>
    <input type="hidden" name="action" value="<?= e($action) ?>" />
    <input type="hidden" name="uuid" value="<?= e($uuid) ?>" />
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>" />
    <?php
}
?>

<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Tickets · Campus</title>
        <link rel="stylesheet" href="styles/dashboard.css" />
    </head>
    <body>
        <aside>
            <div class="brand">
                <b class="mark" aria-hidden="true">▥</b>
                Campus
            </div>
            <p class="nav-label">ADMINISTRATION</p>
            <nav aria-label="Gestion de la plateforme">
                <a href="dashboard.php#accueil">⌂　Accueil</a>
                <a href="dashboard.php#universites">▦　Universités</a>
                <a href="dashboard.php#consommation">◴　Consommation</a>
                <a href="billing.php">€　Facturation</a>
                <a href="tickets.php" aria-current="page">✉　Tickets</a>
                <a href="dashboard.php#deploiement">＋　Déploiement</a>
            </nav>
            <footer>Espace administrateur</footer>
        </aside>
        <main>
            <header>
                <span>
                    Console
                    <em>/</em>
                    Tickets
                </span>
            </header>
            <div class="hero">
                <small>SUPPORT</small>
                <h1>Tickets</h1>
                <p>Suivez et traitez les tickets de toutes les universités.</p>
            </div>
            <?php if ($message): ?>
                <p role="status" class="notice"><?= e($message) ?></p>
            <?php endif; ?>
            <section class="panel bill-panel">
                <form method="get">
                    <label for="filtre-universite">Université</label>
                    <select name="universite" id="filtre-universite">
                        <option value="">Toutes les universités</option>
                        <?php foreach ($universites as $u): ?>
                            <option value="<?= e($u['slug']) ?>" <?= $filters['universite'] === $u['slug'] ? 'selected' : '' ?>>
                                <?= e(($u['name'] ?: $u['slug']) . ' · ' . $u['slug']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="filtre-statut">Statut</label>
                    <select name="statut" id="filtre-statut">
                        <option value="">Tous les statuts</option>
                        <?php foreach ($statuts as $code => $libelle): ?>
                            <option value="<?= $code ?>" <?= $filters['statut'] === (string) $code ? 'selected' : '' ?>><?= e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button">Filtrer</button>
                </form>
            </section>
            <section class="panel bill-panel">
                <small>TICKETS</small>
                <h2><?= count($tickets) ?> ticket(s)</h2>
                <?php if (!$tickets): ?>
                    <p>Aucun ticket pour ces filtres.</p>
                <?php endif; ?>
                <?php foreach ($tickets as $t): ?>
                    <article class="bill-preview">
                        <h2><?= e($t['title']) ?> · <?= e($statuts[(int) $t['status']] ?? '') ?></h2>
                        <p>
                            <?= e($t['category']) ?> · Priorité <?= e($t['priority']) ?> ·
                            par <?= e($t['created_by']) ?>
                            (<?= e(($t['university'] ?: 'Université inconnue') . ($t['university_slug'] ? ' · ' . $t['university_slug'] : '')) ?>)
                            le <?= e($t['created_at']) ?>
                        </p>
                        <p style="white-space: pre-wrap"><?= e($t['description']) ?></p>
                        <?php if ((int) $t['status'] === 0): ?>
                            <form method="post">
                                <?php ticketForm('prendre', $t['uuid']); ?>
                                <button class="button">Prendre en charge</button>
                            </form>
                        <?php elseif ((int) $t['status'] === 1 && $t['handled_by_id'] === $handlerId): ?>
                            <form method="post">
                                <?php ticketForm('traiter', $t['uuid']); ?>
                                <label>
                                    Solution
                                    <textarea name="solution" rows="3"><?= e($t['solution']) ?></textarea>
                                </label>
                                <button class="button" name="status" value="1">Enregistrer</button>
                                <button class="button" name="status" value="2">Clôturer</button>
                            </form>
                        <?php elseif ((int) $t['status'] === 1): ?>
                            <p>Pris en charge par <?= e($t['handled_by']) ?></p>
                            <form method="post">
                                <?php ticketForm('prendre', $t['uuid']); ?>
                                <button class="button">Reprendre</button>
                            </form>
                        <?php else: ?>
                            <p style="white-space: pre-wrap">Solution : <?= e($t['solution']) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
    </body>
</html>
