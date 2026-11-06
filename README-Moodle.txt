MOODLE - ACCES DE TEST

Apres avoir clone le depot, ouvrir PowerShell a la racine.

1. Si le fichier .env est absent, lancer :
   Copy-Item .env.example .env

2. Demarrer le projet :
   docker compose up -d --build

3. Apres le premier demarrage, ouvrir :
   http://elearn.localhost/login/index.php

ACCES SUR UNE BASE NEUVE AVEC LE .env.example FOURNI

Administrateur : admin
Mot de passe   : UnitA-Admin-Test-2026!

Enseignante    : unita.enseignant
Etudiant 1     : unita.etudiant1
Etudiant 2     : unita.etudiant2
Mot de passe commun : UnitA-Demo-Test-2026!

Cours de demonstration : Introduction au cloud computing.

Les comptes sont crees automatiquement. Les mots de passe ci-dessus
servent uniquement aux tests. Pour une vraie instance, choisir ses
propres mots de passe et laisser MOODLE_DEMO_PASSWORD vide avant
le premier demarrage. Sur une base existante, les comptes gardent
leurs mots de passe actuels.
