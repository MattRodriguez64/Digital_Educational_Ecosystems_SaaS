#!/bin/sh
set -eu

# Read the database state using the same credentials as Moodle.
state=$(php -r '
$db = new mysqli(
    getenv("MOODLE_DB_HOST"),
    getenv("MOODLE_DB_USER"),
    getenv("MOODLE_DB_PASSWORD"),
    getenv("MOODLE_DB_NAME")
);
$tables = $db->query("SHOW TABLES");
if ($tables->num_rows === 0) {
    echo "empty";
    exit;
}
$config = $db->query("SELECT value FROM mdl_config WHERE name = \"version\"");
echo $config->num_rows === 1 ? "installed" : "incomplete";
')

case "$state" in
    empty)
        echo "Installing Moodle..."
        runuser -u www-data -- php /var/www/html/admin/cli/install_database.php \
            --agree-license \
            --lang=fr \
            --fullname="Campus numerique UNITA" \
            --shortname=UNITA \
            --adminuser=admin \
            --adminemail=admin@example.test \
            --adminpass="${MOODLE_ADMIN_PASSWORD:?Set MOODLE_ADMIN_PASSWORD in .env}"
        ;;
    installed)
        echo "Existing Moodle database found."
        ;;
    *)
        echo "Moodle installation appears incomplete. Check the database before retrying." >&2
        exit 1
        ;;
esac

if [ -n "${MOODLE_DEMO_PASSWORD:-}" ]; then
    runuser -u www-data -- php <<'MOODLE_DEMO_USERS'
<?php
define('CLI_SCRIPT', true);

require '/var/www/html/config.php';
require_once $CFG->dirroot . '/user/lib.php';

$accounts = [
    ['unita.enseignant', 'Camille', 'Martin'],
    ['unita.etudiant1', 'Lina', 'Bernard'],
    ['unita.etudiant2', 'Noah', 'Diallo'],
];

foreach ($accounts as [$username, $firstname, $lastname]) {
    $identity = [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
    ];

    if ($DB->record_exists('user', $identity)) {
        continue;
    }

    user_create_user((object) ($identity + [
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $username . '@example.test',
        'auth' => 'manual',
        'confirmed' => 1,
        'password' => getenv('MOODLE_DEMO_PASSWORD'),
    ]));

    echo "Compte demo cree : $username\n";
}

require_once $CFG->dirroot . '/course/lib.php';
\core\session\manager::set_user(get_admin());

$shortname = 'UNITA-DOCKER-01';
$course = $DB->get_record('course', ['shortname' => $shortname]);

if (!$course) {
    $course = create_course((object) [
        'category' => \core_course_category::get_default()->id,
        'fullname' => 'Introduction au cloud computing',
        'shortname' => $shortname,
        'format' => 'topics',
        'visible' => 1,
        'summaryformat' => FORMAT_HTML,
        'summary' => <<<'HTML'
<h3>Objectif</h3>
<p>Comprendre comment une application web sépare son interface, sa base de données et son stockage.</p>

<h3>Mini-leçon</h3>
<p>Le cloud computing permet d'utiliser à la demande des ressources informatiques accessibles par réseau, comme des serveurs, du stockage ou des bases de données.</p>
<p>Notre plateforme Moodle illustre cette séparation. Traefik reçoit la requête du navigateur et la transmet à Moodle. Moodle interroge MariaDB pour retrouver les comptes et les cours. Les fichiers déposés dans les cours sont conservés dans <code>moodledata</code>.</p>

<h3>Exercice</h3>
<p>Une université remplace le conteneur Moodle pour mettre à jour l'application. Quels volumes doit-elle conserver pour retrouver à la fois les inscriptions et les PDF déposés dans les cours ?</p>

<h3>Correction</h3>
<p>Le volume <code>db_data</code> conserve les inscriptions dans MariaDB. Le volume <code>moodledata</code> conserve le contenu des PDF. Le nouveau conteneur Moodle réutilise ces deux volumes.</p>
HTML,
    ]);
}

$manual = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', [
    'courseid' => $course->id,
    'enrol' => 'manual',
]);

if (!$instance) {
    $instanceId = $manual->add_instance($course);
    $instance = $DB->get_record('enrol', ['id' => $instanceId], '*', MUST_EXIST);
}

$roles = [
    'unita.enseignant' => 'editingteacher',
    'unita.etudiant1' => 'student',
    'unita.etudiant2' => 'student',
];

foreach ($roles as $username => $role) {
    $userId = $DB->get_field('user', 'id', [
        'username' => $username,
        'mnethostid' => $CFG->mnet_localhost_id,
    ], MUST_EXIST);

    if ($DB->record_exists('user_enrolments', [
        'enrolid' => $instance->id,
        'userid' => $userId,
    ])) {
        continue;
    }

    $roleId = $DB->get_field('role', 'id', ['shortname' => $role], MUST_EXIST);
    $manual->enrol_user($instance, $userId, $roleId);
}

echo "Cours demo pret : $shortname\n";
MOODLE_DEMO_USERS
fi

exec docker-php-entrypoint apache2-foreground
