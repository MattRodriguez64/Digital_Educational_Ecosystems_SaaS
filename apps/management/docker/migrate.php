<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        $db->real_connect(getenv('DB_HOST') ?: 'mariadb', getenv('DB_USER'), getenv('DB_PASSWORD'), 'admin_saas');
        break;
    } catch (mysqli_sql_exception $error) {
        if ($attempt === 30) { throw new RuntimeException('Base Gestion inaccessible aprÃ¨s 30 tentatives.', 0, $error); }
        sleep(2);
    }
}
$db->set_charset('utf8mb4');
// if ((int) $db->query("SELECT GET_LOCK('campus-billing-schema', 60)")->fetch_row()[0] !== 1) {
//     throw new RuntimeException('Migration Gestion dÃ©jÃ  verrouillÃ©e.');
// }
// try {
//     $sql = file_get_contents('/usr/local/share/campus/billing.sql');
//     if ($sql === false) { throw new RuntimeException('Migration de facturation introuvable.'); }
//     $db->multi_query($sql);
//     do {
//         if ($result = $db->store_result()) { $result->free(); }
//     } while ($db->more_results() && $db->next_result());
//     echo "[gestion] SchÃ©ma de facturation prÃªt.\n";
// } finally {
//     $db->query("SELECT RELEASE_LOCK('campus-billing-schema')");
//     $db->close();
// }
