# Nextcloud : stockage et partage de fichiers

Chaque établissement dispose de son propre Nextcloud pour stocker, partager et
travailler à plusieurs sur ses documents. Ce dossier ajoute Nextcloud au projet
sans modifier le `docker-compose.yml` principal : il réutilise Traefik, MariaDB et
les réseaux `proxy` et `backend` déjà définis.

## Contenu du dossier

| Fichier | Rôle |
|---|---|
| `compose.nextcloud.yml` | Les trois services Nextcloud, ajoutés au compose principal |
| `Dockerfile` | Image `digital-campus-nextcloud:v1` : Nextcloud 34 + nos scripts |
| `hooks/before-starting/10-campus-logs.sh` | Envoie les journaux vers la sortie du conteneur |
| `hooks/before-starting/20-campus-provision.sh` | Groupes, quotas, partage, applications, espaces, comptes de démo |
| `hooks/repair.sh` | Migrations de types MIME, après installation ou mise à jour |
| `.env.example` | Modèle du fichier `.env` |

Les trois services :

- `nextcloud-db-init` crée la base `nextcloud` dans la MariaDB du projet, puis s'arrête.
- `nextcloud` est l'application, accessible sur http://nextcloud.localhost.
- `nextcloud-cron` exécute les tâches de fond toutes les 5 minutes.

## Lancer en local avec Docker Desktop

### 1. Créer le fichier `.env`

Créer un fichier `.env` **à la racine du projet**, à côté de `docker-compose.yml`
(pas dans le dossier `nextcloud/`). Exemple :

```env
# Compose principal (MariaDB, Moodle)
DB_NAME=moodle
DB_USER=moodle
DB_PASSWORD=moodle
DB_ROOT_PASSWORD=Root-Campus-2026

# Nextcloud
NEXTCLOUD_DB_PASSWORD=Base-Nextcloud-2026
NEXTCLOUD_ADMIN_USER=admin
NEXTCLOUD_ADMIN_PASSWORD=Admin-Campus-2026
NEXTCLOUD_DEMO_PASSWORD=Demo-Campus-2026
```

`NEXTCLOUD_DEMO_PASSWORD` crée les comptes `enseignant1`, `etudiant1` et `etudiant2`.
Le laisser vide pour ne pas les créer. Un `$` dans un mot de passe s'écrit `$$`.
Ce fichier contient des mots de passe : ne jamais le commiter.

### 2. Démarrer

Ouvrir Docker Desktop, puis un terminal à la racine du projet :

```
docker compose -f docker-compose.yml -f nextcloud/compose.nextcloud.yml up -d --build traefik nextcloud nextcloud-cron
```

Seuls Traefik, MariaDB et Nextcloud démarrent. Moodle et la stack ELK ne sont pas lancés.

### 3. Attendre la fin de l'installation

La première fois, compter une à trois minutes. Suivre l'avancement :

```
docker compose -f docker-compose.yml -f nextcloud/compose.nextcloud.yml logs -f nextcloud
```

C'est prêt quand la ligne `[campus-nextcloud] Configuration appliquée` apparaît.
`Ctrl+C` quitte l'affichage sans arrêter le service.

### 4. Ouvrir Nextcloud

http://nextcloud.localhost, avec `NEXTCLOUD_ADMIN_USER` et `NEXTCLOUD_ADMIN_PASSWORD`.

### Autres commandes utiles

| Besoin | Commande |
|---|---|
| Voir l'état des services | `docker compose -f docker-compose.yml -f nextcloud/compose.nextcloud.yml ps` |
| Appliquer une modification des scripts | `docker compose -f docker-compose.yml -f nextcloud/compose.nextcloud.yml up -d --build nextcloud nextcloud-cron` |
| Arrêter (les données sont conservées) | `docker compose -f docker-compose.yml -f nextcloud/compose.nextcloud.yml down` |
| Repartir de zéro (efface **toutes** les données du projet, Moodle compris) | `docker compose -f docker-compose.yml -f nextcloud/compose.nextcloud.yml down -v` |

## Ce qui est configuré automatiquement

Le script `20-campus-provision.sh` s'exécute à chaque démarrage. Il vérifie l'état
avant d'agir : un réglage ci-dessous modifié dans l'interface reprend sa valeur au
redémarrage. Pour le changer durablement, modifier le `.env` ou le script.

| Domaine | Configuration |
|---|---|
| Groupes | `etudiants`, `enseignants` (et `admin`, créé par Nextcloud) |
| Quotas | 2 GB par défaut (étudiants), 10 GB pour les enseignants |
| Partage | Liens publics avec mot de passe obligatoire, expiration à 30 jours |
| Applications | Deck, Agenda, Team folders, en plus de Fichiers, Text, Activité, Commentaires |
| Espaces partagés | « Ressources de cours » (enseignants en écriture, étudiants en lecture) et « Salle des enseignants » |
| Tâches de fond | Mode cron, tâches lourdes entre 1 h et 5 h (UTC) |
| Désactivé | AppAPI (applications externes) et Logreader (les journaux partent vers ELK) |

## Liens pour tester

Se connecter d'abord en admin. Pour les vues enseignant et étudiant, utiliser une
fenêtre de navigation privée avec le mot de passe de démo.

| Lien | Ce qu'on y vérifie |
|---|---|
| http://nextcloud.localhost | Page de connexion |
| http://nextcloud.localhost/settings/users | Comptes, groupes et quotas |
| http://nextcloud.localhost/settings/admin/groupfolders | Espaces partagés et droits par groupe |
| http://nextcloud.localhost/settings/admin/sharing | Règles de partage |
| http://nextcloud.localhost/settings/admin | Tâches de fond en mode « Cron » |
| http://nextcloud.localhost/settings/admin/overview | Avertissements de sécurité et de configuration |
| http://nextcloud.localhost/settings/apps | Applications actives |
| http://nextcloud.localhost/apps/files | Fichiers de l'utilisateur connecté |
| http://nextcloud.localhost/apps/deck | Tableaux collaboratifs |
| http://nextcloud.localhost/status.php | Disponibilité du service (`"installed":true`) |
| http://traefik.localhost | Tableau de bord Traefik : routeur `nextcloud` |

Points à vérifier :

- `etudiant1` voit « Ressources de cours » en lecture seule et pas la « Salle des enseignants ».
- Un lien de partage public demande obligatoirement un mot de passe.
- Dans la vue d'ensemble, restent attendus en local : HTTPS et HSTS (à venir avec
  Traefik), verrouillage des fichiers par la base (Redis possible plus tard), double
  authentification non imposée, e-mail non configuré.

## Journaux

### Ce qui reprend le travail de l'équipe

- **Format des accès HTTP** : Nextcloud écrit ses accès au format Apache `combined`,
  celui que lit déjà le filtre `grok COMBINEDAPACHELOG` de `logstash.conf`.
- **Métriques** : le module `docker` de `metricbeat.yml` collecte déjà CPU et mémoire
  de chaque conteneur, Nextcloud compris. Rien à ajouter.
- **Nomenclature de Matt** (branche `branch-matt`) : image `digital-campus-*`, service
  d'initialisation sur le modèle de `campus-billing-init`, préfixe `campus`. Son
  collecteur (`campus/monitoring/collector.php`) regroupe les conteneurs par stack
  `univ-*` : une fois Nextcloud dans le template par université, sa consommation est
  comptée dans le suivi et la facturation sans autre changement.

### Où les trouver

Il n'y a pas de fichier `nextcloud.log` : tout part dans la sortie du conteneur.

- Terminal : `docker compose -f docker-compose.yml -f nextcloud/compose.nextcloud.yml logs nextcloud`
- Docker Desktop : onglet Containers, conteneur `nextcloud`, onglet Logs.

### Comment ça fonctionne

Le réglage est fait par `hooks/before-starting/10-campus-logs.sh` à chaque démarrage.

```
Nextcloud ──> PHP error_log ──> Apache ──> sortie du conteneur ──> Docker
                                                                     │
                     Kibana <── Elasticsearch <── Logstash <── Filebeat (à brancher)
```

| Sortie | Contenu | Format |
|---|---|---|
| stdout | Accès HTTP | Apache `combined` |
| stderr | Événements de l'application et audit (connexions, partages, fichiers) | `[nextcloud][<app>][<niveau>] {json}` |

Le JSON contient notamment `time`, `level`, `user`, `app`, `method`, `url`, `message`
et `remoteAddr`. Le niveau se règle avec `NEXTCLOUD_LOGLEVEL` dans le `.env`
(0 debug, 1 info par défaut, 2 avertissement, 3 erreur). L'étiquette Docker
`campus.service=nextcloud` permet de filtrer ces conteneurs dans Filebeat.

## Intégration avec le reste du projet

- **Portail** : remplacer `'url' => '#'` par `http://nextcloud.localhost`.
- **Stack par université** : copier les trois services dans le template de Matt en
  remplaçant `nextcloud.localhost` par `nextcloud.${UNIV_DOMAIN}`.
- **HTTPS** : passer `OVERWRITEPROTOCOL` à `https` quand Traefik gérera les certificats.

## Sauvegarde et restauration

À faire plus tard.
