<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Attendre que la base et les tables nécessaires soient disponibles.
for ($attempt = 1; $attempt <= 30; $attempt++) {
    $db = null;

    try {
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

        $db->real_connect(
            getenv('DB_HOST') ?: 'mariadb',
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            'admin_saas'
        );

        $db->set_charset('utf8mb4');

        // Vérifier les tables et leurs colonnes sans modifier les données.
        $queries = [
            'SELECT slug, name, email, address
             FROM billing_contacts LIMIT 0',

            'SELECT id, issuer, address, monthly, cpu, memory, network, vat
             FROM billing_settings LIMIT 0',

            'SELECT id, slug, period, request_key, issued, snapshot
             FROM billing_invoices LIMIT 0',
        ];

        foreach ($queries as $query) {
            $db->query($query)->free();
        }

        echo "[gestion] Base et tables de facturation disponibles.\n";
        exit(0);

    } catch (mysqli_sql_exception $error) {
        if ($attempt === 30) {
            throw new RuntimeException(
                'Base ou schema de facturation indisponible : '
                . 'verifier initialisation, droits et configuration.',
                0,
                $error
            );
        }
    } finally {
        if ($db !== null) {
            $db->close();
        }
    }

    sleep(2);
}
