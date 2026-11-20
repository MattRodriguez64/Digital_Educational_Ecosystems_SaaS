# Digital Educational Ecosystems SaaS

Plateforme multi-universités sur **Docker Swarm**. Une console de **gestion** déploie, via Portainer, une stack par université : un portail avec ses comptes, Moodle, Nextcloud et une base MariaDB. Le support, la supervision (Elasticsearch / Kibana) et la facturation sont communs à toutes les universités.

---

## Lancer le projet

**Prérequis :** Docker (Desktop ou Engine), environ 8 Go de RAM pour Docker et un accès Internet. Sous Windows, lancer les scripts depuis **Git Bash**, ou depuis PowerShell avec `& "C:\Program Files\Git\bin\bash.exe" <script>`.

**1. Initialiser le Swarm.** Sur Docker Desktop, initialiser directement sur le réseau des workers locaux :

```sh
docker network create campus-workers
docker swarm init --advertise-addr "$(docker network inspect campus-workers -f '{{(index .IPAM.Config 0).Gateway}}')"
```

Sur un serveur Linux : `docker swarm init --advertise-addr <IP du serveur>`.

**2. Créer le `.env`.**

```sh
cp .env.example .env
```

Remplir les mots de passe (`DB_PASSWORD`, `DB_ROOT_PASSWORD`, `NEXTCLOUD_*_PASSWORD`, `MOODLE_ADMIN_PASSWORD`) et `SUPPORT_SECRET` (une longue chaîne aléatoire). `MOODLE_ADMIN_PASSWORD` doit contenir un caractère spécial.

Pour Docker Desktop, les valeurs par défaut conviennent : `CAMPUS_BASE_DOMAIN=localhost`, `PUBLIC_PORT=80`, `DATA_NODE_HOSTNAME=docker-desktop`. Sur un serveur, indiquer `<IP>.sslip.io` ou un vrai domaine, et le nom du nœud donné par `docker node ls`.

Laisser vides les variables `PORTAINER_*` : le script les remplit lui-même.

**3. (Optionnel) Ajouter 2 workers locaux.**

```sh
sh scripts/add-local-workers.sh 2
```

**4. Déployer.**

```sh
sh scripts/deploy-swarm.sh
```

Le script fait tout :
- construction des images et publication dans le registre local `localhost:5000` ;
- labels des nœuds ;
- déploiement de toutes les stacks ;
- initialisation de Portainer (compte admin, environnement, clé API enregistrée dans `.env`) ;
- tableaux de bord Kibana.

Il se relance sans risque, par exemple après une modification du code.

---

## Accès

| Service | URL | Connexion |
|---|---|---|
| Gestion | http://gestion.localhost | `admin` / `admin` |
| Portainer | http://portainer.localhost | `admin` / `PORTAINER_ADMIN_PASSWORD` du `.env` |
| Kibana | http://kibana.localhost | aucune |
| Support | http://support.localhost | depuis le portail d'une université |

Si `PUBLIC_PORT` ne vaut pas 80, ajouter le port aux adresses, par exemple `http://gestion.localhost:18081`.

## Créer une université

1. Dans la gestion, ouvrir l'onglet **Déploiement**.
2. Indiquer un identifiant de la forme `univ-xxx` (minuscules, chiffres, tirets), puis l'e-mail et le mot de passe du compte admin de l'université.
3. Attendre environ **5 minutes**, le temps que Moodle et Nextcloud s'installent.

L'université est ensuite disponible :

| Service | URL |
|---|---|
| Portail | http://univ-xxx.localhost |
| Moodle | http://elearn.univ-xxx.localhost |
| Nextcloud | http://nextcloud.univ-xxx.localhost |

Le compte admin saisi dans le formulaire fonctionne sur le portail, Moodle, Nextcloud et le support. Depuis le portail, il crée les autres comptes (enseignants, étudiants) dans la tuile **Utilisateurs**.

---

## Répartition sur les nœuds

| Où | Services |
|---|---|
| Nœud de données (`DATA_NODE_HOSTNAME`) | bases, Moodle, Nextcloud, gestion, Elasticsearch, Kibana |
| Manager | Traefik, Portainer, registre |
| Workers (label `campus_web`) | portails des universités, support. Sans worker, ils vont sur le nœud de données. |
| Tous les nœuds | collector (mesure de la consommation) |

Swarm place et relance les services automatiquement. Le bouton **Simuler 90 %** de l'onglet Consommation passe le portail d'une université à 2 réplicas, répartis sur les workers.

Pour ajouter de vrais serveurs comme workers :
1. récupérer la commande avec `docker swarm join-token worker` ;
2. l'exécuter sur chaque serveur ;
3. ouvrir les ports `2377/tcp`, `7946/tcp+udp` et `4789/udp` entre les nœuds ;
4. relancer `deploy-swarm.sh`.

---

## Problèmes fréquents

| Problème | Solution |
|---|---|
| Une université ne démarre pas (« Access denied » dans les logs MariaDB) | Des volumes d'une ancienne université du même nom existent. Supprimer la stack dans Portainer, puis `docker volume rm univ-xxx_db_data univ-xxx_moodledata univ-xxx_nextcloud_data univ-xxx_nextcloud_html`, et la recréer. |
| Une université disparaît quelques minutes après sa création | Portainer a annulé le déploiement. La recréer. |
| `add-local-workers.sh` refuse de continuer | Le Swarm n'a pas été initialisé sur le réseau `campus-workers` (étape 1). Le script affiche les commandes pour le réinitialiser. Les volumes sont conservés. |
| Le script ne peut pas se connecter à Portainer | Indiquer dans `PORTAINER_ADMIN_PASSWORD` le mot de passe du compte admin existant. |

Pour diagnostiquer : `docker service ls`, `docker service ps --no-trunc <service>` et `docker service logs <service>`.

---

## Sécurité (à revoir avant une mise en production)

- Le compte de la gestion est `admin` / `admin` (`infrastructure/database/init.sql`).
- La base support utilise `support` / `support`, écrit en dur dans le code.
- Tout le trafic passe en HTTP, et le registre local n'a pas d'authentification.

---

## Organisation du dépôt

```
apps/management/        Console de gestion (universités, consommation, facturation, tickets)
apps/portal-university/ Portail d'université (connexion, comptes, accès au support)
apps/support/           Support (tickets, PDF, SLA)
services/moodle/        Image Moodle
services/nextcloud/     Image Nextcloud
infrastructure/         Base centrale, supervision
swarm/                  Stacks de la plateforme
template/university/    Modèle de stack d'une université
scripts/                deploy-swarm.sh, add-local-workers.sh
```
