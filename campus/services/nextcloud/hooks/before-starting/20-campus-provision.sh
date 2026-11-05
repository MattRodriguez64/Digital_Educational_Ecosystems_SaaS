#!/bin/sh
set -eu

occ() { php /var/www/html/occ "$@"; }
log() { echo "[campus-nextcloud] $*"; }

i=0
for domain in ${NEXTCLOUD_TRUSTED_DOMAINS:-}; do
    occ config:system:set trusted_domains "$i" --value="$domain" >/dev/null
    i=$((i + 1))
done
occ config:system:set default_language --value="${NEXTCLOUD_DEFAULT_LANGUAGE:-fr}" >/dev/null
occ config:system:set default_locale --value="${NEXTCLOUD_DEFAULT_LOCALE:-fr_FR}" >/dev/null
occ config:system:set default_phone_region --value="${NEXTCLOUD_PHONE_REGION:-FR}" >/dev/null
occ background:cron >/dev/null
occ config:system:set maintenance_window_start --value=1 --type=integer >/dev/null
occ config:system:set serverid --value=1 --type=integer >/dev/null

ensure_group() {
    occ group:info "$1" >/dev/null 2>&1 || occ group:add --display-name="$2" "$1" >/dev/null
}
ensure_group etudiants "Étudiants"
ensure_group enseignants "Enseignants"

occ config:app:set files default_quota --value="${NEXTCLOUD_DEFAULT_QUOTA:-2 GB}" >/dev/null

occ config:app:set core shareapi_enabled --value=yes >/dev/null
occ config:app:set core shareapi_allow_links --value=yes >/dev/null
occ config:app:set core shareapi_enforce_links_password --value=yes >/dev/null
occ config:app:set core shareapi_default_expire_date --value=yes >/dev/null
occ config:app:set core shareapi_expire_after_n_days --value="${NEXTCLOUD_LINK_EXPIRE_DAYS:-30}" >/dev/null

occ app:disable app_api >/dev/null
for app in ${NEXTCLOUD_EXTRA_APPS:-groupfolders deck calendar}; do
    if occ app:getpath "$app" >/dev/null 2>&1; then
        occ app:enable "$app" >/dev/null
    elif occ app:install "$app" >/dev/null 2>&1; then
        log "Application installée : $app"
    else
        log "ATTENTION : $app non installée (magasin d'applications injoignable ?)"
    fi
done

folder_id() {
    occ groupfolders:list --output=json | php -r '
        foreach (json_decode(stream_get_contents(STDIN), true) ?: [] as $f) {
            if (($f["mountPoint"] ?? $f["mount_point"] ?? null) === $argv[1]) { echo $f["id"]; exit; }
        }' -- "$1"
}
ensure_folder() {
    id=$(folder_id "$1")
    [ -n "$id" ] || id=$(occ groupfolders:create "$1")
    occ groupfolders:quota "$id" "$2" >/dev/null
    echo "$id"
}
if occ app:getpath groupfolders >/dev/null 2>&1; then
    id=$(ensure_folder "Ressources de cours" "${NEXTCLOUD_COURSE_FOLDER_QUOTA:-20 GB}")
    occ groupfolders:group "$id" enseignants read write share delete >/dev/null
    occ groupfolders:group "$id" etudiants read >/dev/null

    id=$(ensure_folder "Salle des enseignants" "${NEXTCLOUD_TEACHER_FOLDER_QUOTA:-10 GB}")
    occ groupfolders:group "$id" enseignants read write share delete >/dev/null
else
    log "ATTENTION : groupfolders absent, espaces partagés non créés"
fi

ensure_user() {
    if ! occ user:info "$1" >/dev/null 2>&1; then
        NC_PASS="$NEXTCLOUD_DEMO_PASSWORD" occ user:add --password-from-env \
            --display-name="$3" --group="$2" "$1" >/dev/null
        log "Compte de démonstration créé : $1"
    fi
    [ -z "$4" ] || occ user:setting "$1" files quota "$4"
}
if [ -n "${NEXTCLOUD_DEMO_PASSWORD:-}" ]; then
    ensure_user enseignant1 enseignants "Enseignant Démo" "${NEXTCLOUD_TEACHER_QUOTA:-10 GB}"
    ensure_user etudiant1 etudiants "Étudiant Démo 1" ""
    ensure_user etudiant2 etudiants "Étudiant Démo 2" ""
fi

log "Configuration appliquée"
