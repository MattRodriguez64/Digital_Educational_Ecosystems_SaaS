#!/bin/sh

VERROU=/tmp/campus-cron.lock

if [ -f "$VERROU" ]; then
    ancien_pid=$(cat "$VERROU")
    if kill -0 "$ancien_pid" 2>/dev/null; then
        echo "Cron déjà en cours (pid $ancien_pid), lancement ignoré."
        exit 0
    fi
    echo "Verrou abandonné trouvé, suppression."
    rm -f "$VERROU"
fi

echo $$ > "$VERROU"

runuser -u www-data -- php /usr/local/lib/campus/sync-teachers.php
runuser -u www-data -- php /var/www/html/admin/cli/cron.php > /tmp/moodle-cron.log 2>&1

rm -f "$VERROU"
