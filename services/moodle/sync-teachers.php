<?php
define('CLI_SCRIPT', true);

require '/var/www/html/config.php';

$authDb = new mysqli(
    getenv('MOODLE_DB_HOST'),
    getenv('MOODLE_DB_USER'),
    getenv('MOODLE_DB_PASSWORD'),
    'authentification'
);

$createurs = [];
$resultat = $authDb->query("SELECT email FROM users WHERE role = 'teacher' OR role = 'admin'");
while ($ligne = $resultat->fetch_assoc()) {
    $email = strtolower($ligne['email']);
    $createurs[$email] = true;
}
$authDb->close();

$roleId = $DB->get_field('role', 'id', ['shortname' => 'coursecreator'], MUST_EXIST);
$contexte = context_system::instance();

$utilisateurs = $DB->get_records('user', ['auth' => 'db', 'deleted' => 0], '', 'id, username');

foreach ($utilisateurs as $utilisateur) {
    $doitEtreCreateur = false;
    if (isset($createurs[$utilisateur->username])) {
        $doitEtreCreateur = true;
    }

    $estCreateur = user_has_role_assignment($utilisateur->id, $roleId, $contexte->id);

    if ($doitEtreCreateur === true && $estCreateur === false) {
        role_assign($roleId, $utilisateur->id, $contexte->id);
        echo "Rôle créateur de cours ajouté : " . $utilisateur->username . "\n";
    }

    if ($doitEtreCreateur === false && $estCreateur === true) {
        role_unassign($roleId, $utilisateur->id, $contexte->id);
        echo "Rôle créateur de cours retiré : " . $utilisateur->username . "\n";
    }
}
