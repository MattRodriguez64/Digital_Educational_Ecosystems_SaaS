# Administration des universités

Depuis la racine du dépôt, `docker compose up -d --build` charge automatiquement la surcharge
`docker-compose.override.yml`. Le dashboard est accessible à **http://gestion.localhost**.
Le portail, le support, Moodle et les pages déjà routées par Traefik gardent leurs adresses.

## Configuration locale

- Le fichier `.env` reste local. Conserver les variables MariaDB du dépôt.
- Pour l’inventaire, le déploiement, le démarrage, l’arrêt et la suppression des stacks,
  renseigner `PORTAINER_API_KEY` avec une clé de **cette instance** de Portainer et
  `PORTAINER_ENDPOINT_ID` avec l’identifiant de son environnement Docker (valeur par défaut : 3).
- La base de gestion est initialisée dans le volume Compose `campus_admin_data`.
  Le compte de démonstration initial est `admin` / `admin`.
- Les courbes utilisent le Kibana du projet sur **http://localhost:5601**.
  Le pipeline Logstash existant continue de recevoir les événements Beats sur le port 5044.
- Les images universitaires sont construites par les services portail, support et elearn.
  Leurs tags locaux commencent par `digital-campus-`.
- La copie du Bureau et ce projet publient tous deux Traefik sur le port 80. Une seule de ces
  instances peut occuper ce port à la fois. L’import Git ne migre pas de volumes Docker.
