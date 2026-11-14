<?php
// Cette vue reçoit ses données du contrôleur de facturation.
if (!isset($contacts, $history)) {
    http_response_code(404);
    exit;
}

// Chaque formulaire indique son action et transmet un jeton propre à la session.
function hiddenAction(string $action): void
{
    ?>
    <input type="hidden" name="action" value="<?= esc($action) ?>" />
    <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>" />
    <?php
}

$rateLabels = [
    'monthly' => 'Forfait mensuel (€ / université)',
    'cpu' => 'Processeur (€ / cœur-h)',
    'memory' => 'Mémoire (€ / Gio-h)',
    'network' => 'Réseau entrant + sortant (€ / Gio)',
    'vat' => 'TVA (%)',
];
?>

<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Facturation · Campus</title>
        <link rel="stylesheet" href="styles/dashboard.css" />
        <script defer src="scripts/billing.js"></script>
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
                <a href="billing.php" aria-current="page">€　Facturation</a>
                <a href="dashboard.php#deploiement">＋　Déploiement</a>
            </nav>
            <footer>Espace administrateur</footer>
        </aside>
        <main>
            <header>
                <span>
                    Console
                    <em>/</em>
                    Facturation
                </span>
            </header>
            <div class="hero">
                <small>GESTION FINANCIÈRE</small>
                <h1>Facturation</h1>
                <p>Préparez vos factures et retrouvez les documents émis par université.</p>
            </div>
            <?php if ($error || $message): ?>
                <p role="status" class="notice"><?= esc($error ?: $message) ?></p>
            <?php endif; ?>
            <section class="panel bill-panel">
                <small>ÉMISSION</small>
                <h2>Préparer une facture</h2>
                <!-- Le premier formulaire calcule un aperçu. Le second confirme son émission. -->
                <p>
                    Le premier relevé facturé du mois inclut le forfait complet. Les suivants
                    reprennent uniquement la consommation encore à facturer.
                </p>
                <form method="post">
                    <?php hiddenAction('preview'); ?>
                    <label for="bill-campus">Université</label>
                    <select name="slug" id="bill-campus" required>
                        <option value="">Choisir une université</option>
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?= esc($c['slug']) ?>"><?= esc($c['name'] . ' · ' . $c['slug']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="campus-status" role="status">Chargement des universités…</p>
                    <label for="bill-month">Mois de service (UTC)</label>
                    <input
                        id="bill-month"
                        type="month"
                        required
                        name="month"
                        max="<?= gmdate('Y-m') ?>"
                        value="<?= gmdate('Y-m') ?>"
                    />
                    <button class="button">Calculer l’aperçu</button>
                </form>
                <?php if ($draft && time() - $draft['time'] <= 900): ?>
                    <?php $b = $draft['bill']; ?>
                    <div class="bill-preview">
                        <h2>Aperçu · <?= esc($b['contact']['name']) ?></h2>
                        <p><?= esc($b['month'].' · '.$b['contact']['email']) ?></p>
                        <div class="scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Prestation</th>
                                        <th>Quantité</th>
                                        <th>Prix unitaire HT</th>
                                        <th>Montant HT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($b['lines'] as $l): ?>
                                        <tr>
                                            <td><?= esc($l['label']) ?></td>
                                            <td><?= numberFr($l['qty']).' '.esc($l['unit']) ?></td>
                                            <td><?= numberFr($l['rate']) ?> €</td>
                                            <td><?= euros($l['cents']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p>
                            HT <?= euros($b['subtotal']) ?> · TVA <?= euros($b['tax']) ?> ·
                            <strong>Total TTC <?= euros($b['total']) ?></strong>
                        </p>
                        <form method="post">
                            <?php hiddenAction('issue'); ?>
                            <input type="hidden" name="key" value="<?= esc($draft['key']) ?>" />
                            <button class="button">Émettre cette facture</button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
            <details class="panel bill-panel" <?= !$settings ? 'open' : '' ?>>
                <summary>Tarifs et émetteur</summary>
                <p>
                    Tarifs communs en euros HT. L’émission conserve les coordonnées, quantités et
                    prix de l’aperçu.
                </p>
                <form method="post">
                    <?php hiddenAction('settings'); ?>
                    <label>
                        Nom de l’émetteur
                        <input name="issuer" required maxlength="180" value="<?= esc($settings['issuer'] ?? '') ?>" />
                    </label>
                    <label>
                        Adresse et mentions de l’émetteur
                        <textarea name="address" required maxlength="500"><?= esc($settings['address'] ?? '') ?></textarea>
                    </label>
                    <?php foreach ($rateLabels as $k => $label): ?>
                        <label>
                            <?= esc($label) ?>
                            <input
                                type="number"
                                min="0"
                                max="<?= $k==='vat'?'100':'999999.999999' ?>"
                                step="<?= $k==='vat'?'0.01':'0.000001' ?>"
                                required
                                name="<?= $k ?>"
                                value="<?= esc($settings[$k] ?? '') ?>"
                            />
                        </label>
                    <?php endforeach; ?>
                    <button class="button">Enregistrer les tarifs</button>
                </form>
            </details>
            <details class="panel bill-panel" <?= !$contacts ? 'open' : '' ?>>
                <summary>Contact de facturation</summary>
                <p>Sélectionnez une stack existante pour compléter ou modifier ses coordonnées.</p>
                <form method="post">
                    <?php hiddenAction('contact'); ?>
                    <label>
                        Stack universitaire
                        <input
                            list="known-stacks"
                            id="contact-slug"
                            name="slug"
                            required
                            maxlength="128"
                            placeholder="univ-bordeaux"
                        />
                    </label>
                    <datalist id="known-stacks">
                        <?php foreach ($contacts as $c): ?>
                            <option
                                value="<?= esc($c['slug']) ?>"
                                data-name="<?= esc($c['name']) ?>"
                                data-email="<?= esc($c['email']) ?>"
                                data-address="<?= esc($c['address']) ?>"
                            ></option>
                        <?php endforeach; ?>
                    </datalist>
                    <label>
                        Nom de l’université
                        <input id="contact-name" name="name" required maxlength="180" />
                    </label>
                    <label>
                        E-mail de facturation
                        <input
                            id="contact-email"
                            type="email"
                            required
                            name="email"
                            maxlength="254"
                        />
                    </label>
                    <label>
                        Adresse et références du destinataire
                        <textarea id="contact-address" name="address" maxlength="500"></textarea>
                    </label>
                    <button class="button">Enregistrer le contact</button>
                </form>
            </details>
            <section class="panel bill-panel">
                <small>DOCUMENTS</small>
                <h2>Factures émises</h2>
                <?php if (!$history): ?>
                    <p>Aucune facture émise.</p>
                <?php else: ?>
                    <div class="scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Numéro</th>
                                    <th>Stack / mois</th>
                                    <th>Total TTC</th>
                                    <th>Documents</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $i): ?>
                                    <?php $url = 'billing.php?invoice=' . (int) $i['id']; ?>
                                    <tr>
                                        <td>
                                            <a href="<?= $url ?>" target="_blank" rel="noopener">
                                                <?= sprintf('FAC-%06d',$i['id']) ?>
                                            </a>
                                        </td>
                                        <td><?= esc($i['slug'].' · '.$i['period']) ?></td>
                                        <td><?= euros((int)$i['cents']) ?></td>
                                        <td>
                                            <a href="<?= $url ?>&amp;format=download">
                                                Facture HTML
                                            </a>
                                            ·
                                            <a href="<?= $url ?>&amp;format=email">
                                                Courriel avec pièce jointe
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        Pour un PDF, ouvrez le numéro de facture puis choisissez « Imprimer » et «
                        Enregistrer au format PDF ».
                    </p>
                <?php endif; ?>
            </section>
        </main>
    </body>
</html>
