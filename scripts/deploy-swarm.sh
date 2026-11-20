#!/bin/sh
set -eu
PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$PROJECT_DIR"

if [ ! -f .env ]; then
    echo "Fichier .env absent : copier .env.example vers .env puis le compléter." >&2
    exit 1
fi
set -a
. ./.env
set +a

require() {
    for var in "$@"; do
        eval "value=\${$var:-}"
        if [ -z "$value" ]; then
            echo "Variable manquante dans .env : $var" >&2
            exit 1
        fi
    done
}

require DB_USER DB_PASSWORD DB_ROOT_PASSWORD CAMPUS_BASE_DOMAIN PUBLIC_PORT DATA_NODE_HOSTNAME \
        MOODLE_ADMIN_PASSWORD NEXTCLOUD_ADMIN_PASSWORD NEXTCLOUD_DEMO_PASSWORD SUPPORT_SECRET

if [ "$(docker info --format '{{.Swarm.ControlAvailable}}')" != "true" ]; then
    echo "Ce script doit être lancé sur un manager Swarm actif (docker swarm init)." >&2
    exit 1
fi

REGISTRY=localhost:5000
PORT_SUFFIX=":$PUBLIC_PORT"
[ "$PUBLIC_PORT" = "80" ] && PORT_SUFFIX=""

echo "== Réseaux"
for network in proxy-swarm campus-backend; do
    docker network inspect "$network" >/dev/null 2>&1 \
        || docker network create --driver overlay --attachable "$network"
done

echo "== Registre local"
docker stack deploy -c swarm/registry.yml campus-registry

push() {
    i=0
    until docker push -q "$1" >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            docker push "$1"
            return 1
        fi
        sleep 2
    done
    echo "Publiée : $1"
}

build() {
    image="$REGISTRY/$1:swarm-v1"
    shift
    docker build -t "$image" "$@"
    push "$image"
}

echo "== Images"
build digital-campus-management -f apps/management/Dockerfile .
build digital-campus-support -f swarm/support.Dockerfile .
build digital-campus-portail apps/portal-university
build digital-campus-elearn services/moodle
build digital-campus-nextcloud services/nextcloud
build digital-campus-collector infrastructure/monitoring/swarm

echo "== Labels des nœuds"
docker node update --label-add campus_data=true --label-add campus_monitoring=true "$DATA_NODE_HOSTNAME" >/dev/null
WORKERS=$(docker node ls --filter role=worker --format '{{.Hostname}} {{.Status}}' | awk '$2 == "Ready" { print $1 }')
if [ -n "$WORKERS" ]; then
    for worker in $WORKERS; do
        docker node update --label-add campus_web=true --label-add campus_monitoring=true "$worker" >/dev/null
        echo "Worker : $worker"
    done
    docker node update --label-rm campus_web "$DATA_NODE_HOSTNAME" >/dev/null 2>&1 || true
else
    docker node update --label-add campus_web=true "$DATA_NODE_HOSTNAME" >/dev/null
    echo "Aucun worker : portails et support sur $DATA_NODE_HOSTNAME"
fi

echo "== Stacks"
docker stack deploy -c swarm/edge.yml campus-edge
docker stack deploy -c swarm/portainer.yml campus-portainer

set_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}

portainer() {
    curl -s -H "Host: portainer.$CAMPUS_BASE_DOMAIN" "$@"
}

if [ -z "${PORTAINER_API_KEY:-}" ] || [ -z "${PORTAINER_ENDPOINT_ID:-}" ]; then
    echo "== Initialisation de Portainer"
    API="http://127.0.0.1:$PUBLIC_PORT/api"
    i=0
    until [ "$(portainer -o /dev/null -w '%{http_code}' "$API/system/status")" = "200" ]; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "Portainer ne répond pas." >&2
            exit 1
        fi
        sleep 3
    done

    TOKEN=$(docker service logs --raw campus-portainer_portainer 2>&1 | sed -n 's/.*setup_token=\([0-9a-f]*\).*/\1/p' | tail -1)
    PASSWORD=${PORTAINER_ADMIN_PASSWORD:-Campus-$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')}
    LOGIN="{\"Username\":\"admin\",\"Password\":\"$PASSWORD\"}"
    code=$(portainer -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' \
        -H "X-Setup-Token: $TOKEN" -d "$LOGIN" "$API/users/admin/init")
    [ "$code" = "200" ] && set_env PORTAINER_ADMIN_PASSWORD "$PASSWORD"

    JWT=$(portainer -H 'Content-Type: application/json' -d "$LOGIN" "$API/auth" | sed -n 's/.*"jwt":"\([^"]*\)".*/\1/p')
    if [ -z "$JWT" ]; then
        echo "Connexion à Portainer impossible : indiquer le mot de passe admin dans PORTAINER_ADMIN_PASSWORD (.env)." >&2
        exit 1
    fi

    ENDPOINT=$(portainer -H "Authorization: Bearer $JWT" "$API/endpoints" | grep -oE '"Id":[0-9]+' | head -1 | grep -oE '[0-9]+' || true)
    if [ -z "$ENDPOINT" ]; then
        ENDPOINT=$(portainer -H "Authorization: Bearer $JWT" --form-string Name=local \
            --form-string EndpointCreationType=1 "$API/endpoints" | grep -oE '^\{"Id":[0-9]+' | grep -oE '[0-9]+' || true)
    fi
    if [ -z "$ENDPOINT" ]; then
        echo "Création de l'environnement Portainer impossible." >&2
        exit 1
    fi

    KEY=$(portainer -H "Authorization: Bearer $JWT" -H 'Content-Type: application/json' \
        -d "{\"description\":\"gestion\",\"password\":\"$PASSWORD\"}" "$API/users/1/tokens" \
        | sed -n 's/.*"rawAPIKey":"\([^"]*\)".*/\1/p')
    case "$KEY" in
        ptr_*) ;;
        *) echo "Création de la clé API Portainer impossible." >&2; exit 1 ;;
    esac

    set_env PORTAINER_ENDPOINT_ID "$ENDPOINT"
    set_env PORTAINER_API_KEY "$KEY"
    export PORTAINER_ENDPOINT_ID="$ENDPOINT" PORTAINER_API_KEY="$KEY"
    echo "Portainer prêt : compte admin, environnement $ENDPOINT, clé API enregistrée dans .env"
fi

export PORTAINER_URL="${PORTAINER_URL:-https://portainer:9443}"

docker stack deploy -c swarm/database.yml campus-database
docker stack deploy -c swarm/monitoring.yml campus-monitoring
docker stack deploy -c swarm/support.yml campus-support
docker stack deploy -c swarm/management.yml campus-management

echo "== Tableaux de bord Kibana"
i=0
until MSYS_NO_PATHCONV=1 docker exec "$(docker ps -q -f name=campus-management_management | head -1)" \
        php /usr/local/lib/campus/setup-kibana.php 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "Kibana pas encore prêt : relancer ce script plus tard pour créer les tableaux de bord." >&2
        break
    fi
    sleep 10
done

echo
echo "Gestion   : http://gestion.$CAMPUS_BASE_DOMAIN$PORT_SUFFIX (admin / admin)"
echo "Support   : http://support.$CAMPUS_BASE_DOMAIN$PORT_SUFFIX"
echo "Kibana    : http://kibana.$CAMPUS_BASE_DOMAIN$PORT_SUFFIX"
echo "Portainer : http://portainer.$CAMPUS_BASE_DOMAIN$PORT_SUFFIX (admin / PORTAINER_ADMIN_PASSWORD dans .env)"
