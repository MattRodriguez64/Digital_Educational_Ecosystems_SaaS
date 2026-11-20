#!/bin/sh
set -eu
export MSYS_NO_PATHCONV=1

COUNT=${1:-2}
DIND_IMAGE=${DIND_IMAGE:-docker:29-dind}
NETWORK=campus-workers

docker network inspect "$NETWORK" >/dev/null 2>&1 || docker network create "$NETWORK" >/dev/null
GATEWAY=$(docker network inspect "$NETWORK" -f '{{(index .IPAM.Config 0).Gateway}}')

if [ "$(docker info --format '{{.Swarm.ControlAvailable}}')" != "true" ]; then
    echo "Ce script doit être lancé sur un manager Swarm actif : docker swarm init --advertise-addr $GATEWAY" >&2
    exit 1
fi

MANAGER_ADDR=$(docker info --format '{{.Swarm.NodeAddr}}')
if [ "$MANAGER_ADDR" != "$GATEWAY" ]; then
    echo "Le manager est annoncé sur $MANAGER_ADDR : les workers locaux ne peuvent pas échanger le trafic overlay avec lui." >&2
    echo "Réinitialiser le Swarm (services supprimés, volumes conservés) :" >&2
    echo "  docker swarm leave --force" >&2
    echo "  docker swarm init --advertise-addr $GATEWAY" >&2
    echo "puis relancer ce script et scripts/deploy-swarm.sh." >&2
    exit 1
fi
TOKEN=$(docker swarm join-token -q worker)

i=1
while [ "$i" -le "$COUNT" ]; do
    name="campus-worker$i"

    if [ -z "$(docker ps -aq -f name="^$name\$")" ]; then
        docker run -d --privileged --name "$name" --hostname "$name" \
            --network "$NETWORK" --restart unless-stopped \
            -v "$name-docker:/var/lib/docker" "$DIND_IMAGE" >/dev/null
        echo "Conteneur créé : $name"
    else
        docker start "$name" >/dev/null
    fi

    n=0
    until docker exec "$name" docker info >/dev/null 2>&1; do
        n=$((n + 1))
        if [ "$n" -ge 60 ]; then
            echo "Le démon Docker de $name ne répond pas." >&2
            exit 1
        fi
        sleep 2
    done

    for stale in $(docker node ls --filter role=worker --format '{{.ID}} {{.Hostname}} {{.Status}}' \
            | awk -v h="$name" '$2 == h && $3 == "Down" { print $1 }'); do
        docker node rm "$stale" >/dev/null
    done

    state=$(docker exec "$name" docker info --format '{{.Swarm.LocalNodeState}}')
    managers=$(docker exec "$name" docker info --format '{{range .Swarm.RemoteManagers}}{{.Addr}} {{end}}')
    case " $managers" in
        *" $MANAGER_ADDR:2377 "*) ;;
        *)
            if [ "$state" != "inactive" ]; then
                docker exec "$name" docker swarm leave --force >/dev/null
            fi
            docker exec "$name" docker swarm join --token "$TOKEN" "$MANAGER_ADDR:2377"
            ;;
    esac
    echo "Worker prêt : $name"
    i=$((i + 1))
done

docker node ls
echo
echo "Relancer ensuite : sh scripts/deploy-swarm.sh"
