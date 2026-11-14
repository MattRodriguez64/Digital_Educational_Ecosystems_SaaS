<?php
// On Fait une facture HTML de la conso, plus facile à gérer + On peut la télécharger en format PDF si besoin
if (!isset($bill, $title, $date)) {
    http_response_code(404);
    exit;
}
?>

<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?= esc($title) ?></title>
        <style>
            <?= file_get_contents(__DIR__ . '/../styles/invoice.css') ?>
        </style>
    </head>
    <body>
        <main>
            <p class="brand">SERVICES UNIVERSITAIRES</p>
            <h1><?= esc($title) ?></h1>
            <p>Édition du <?= esc($date) ?> UTC · Mois de service <?= esc($bill['month']) ?></p>
            <div class="parties">
                <!-- Les coordonnées proviennent de la facture enregistrée à la date d’émission. -->
                <section>
                    <h2>Émetteur</h2>
                    <b><?= esc($bill['settings']['issuer']) ?></b>
                    <p><?= nl2br(esc($bill['settings']['address'])) ?></p>
                </section>
                <section>
                    <h2>Université destinataire</h2>
                    <b><?= esc($bill['contact']['name']) ?></b>
                    <p>
                        <?= nl2br(esc($bill['contact']['address'])) ?>
                        <br />
                        <?= esc($bill['contact']['email']) ?>
                        <br />
                        <?= esc($bill['slug']) ?>
                    </p>
                </section>
            </div>
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
                    <?php foreach ($bill['lines'] as $line): ?>
                        <tr>
                            <td><?= esc($line['label']) ?></td>
                            <td><?= numberFr($line['qty']) ?> <?= esc($line['unit']) ?></td>
                            <td><?= numberFr($line['rate']) ?> €</td>
                            <td><?= euros($line['cents']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">
                <p>
                    Total HT
                    <b><?= euros($bill['subtotal']) ?></b>
                </p>
                <p>
                    TVA (<?= numberFr($bill['settings']['vat']) ?> %)
                    <b><?= euros($bill['tax']) ?></b>
                </p>
                <p>
                    Total TTC
                    <b><?= euros($bill['total']) ?></b>
                </p>
            </div>
        </main>
    </body>
</html>
