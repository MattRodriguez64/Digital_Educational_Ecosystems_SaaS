# Landing page

La page d'accueil du projet, avec un formulaire de contact pour les universités.
Une université la visite, voit ce que le SaaS lui apporte, et nous écrit.

## Ce qu'on y trouve

- **La page** (`web/`) : présentation des services, schéma de l'isolation entre campus, déroulement, questions fréquentes et formulaire. Le lien « Se connecter » mène à l'application de gestion de l'équipe.
- **Le serveur** (`api/`) : il reçoit le formulaire, le vérifie, l'enregistre en base et envoie un e-mail de confirmation.
- **La base** (`db/`) : une base `landing` dans le MariaDB du projet, avec un utilisateur qui n'a accès qu'à elle.

## Lancer

Depuis la racine du projet :

```powershell
# 1. Créer les deux secrets (fichiers ignorés par git)
-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ }) | Set-Content -NoNewline apps/landing/secrets/landing_db_password.txt
New-Item -ItemType File -Force apps/landing/secrets/geonames_username.txt

# 2. Démarrer
$env:COMPOSE_FILE="docker-compose.yml;apps/landing/compose.landing.yml"
docker compose up -d --build traefik mariadb mailpit landing-api landing-web
```

Puis :

- la page : <http://landing.localhost>
- les e-mails envoyés (boîte de test) : <http://mail.landing.localhost>

Si le volume MariaDB existait déjà avant la landing, lancer aussi une fois :

```powershell
docker compose exec mariadb bash /docker-entrypoint-initdb.d/zy-landing.sh
```

## Voir les demandes reçues

```powershell
"SELECT id, created_at, university, country, city, email FROM contact_requests ORDER BY id DESC;" | docker compose exec -T mariadb sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" landing'
```

## Ce qu'on a fait, et pourquoi

- **Un fichier Compose à part** (`compose.landing.yml`) : il s'ajoute à celui de l'équipe sans le modifier.
- **Deux copies de la page et du serveur** : si l'une tombe, l'autre répond. Traefik répartit les visiteurs.
- **Mot de passe en secret Docker** : il est dans un fichier, jamais dans le code ni dans Git.
- **Base protégée** : l'utilisateur de la landing peut seulement lire, ajouter et modifier dans sa propre base. Le reste du projet lui est inaccessible.
- **Pas de spam** : 5 envois par visiteur toutes les 15 minutes, sans garder son adresse IP.
- **Ville facultative avec suggestions** (service GeoNames, désactivé tant que son nom d'utilisateur n'est pas dans `secrets/geonames_username.txt`) : le formulaire marche très bien sans.
- **Santé des conteneurs vérifiée** : Docker surveille chaque service. Si le serveur s'arrête, le pied de page affiche « Service de contact indisponible ».

## Tester le code

```powershell
cd apps/landing/api; npm test
cd ../web; npm test
```