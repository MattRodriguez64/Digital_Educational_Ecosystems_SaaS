<?php
// Le contrôleur prépare les données avant d’afficher cette page.
if (!isset($csrf)) {
    http_response_code(404);
    exit;
}
?>

<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Supervision universitaire · Campus</title>
        <link rel="stylesheet" href="styles/dashboard.css?v=<?= filemtime(__DIR__ . '/../styles/dashboard.css') ?>" />
        <script defer src="scripts/dashboard.js?v=<?= filemtime(__DIR__ . '/../scripts/dashboard.js') ?>"></script>
        <script defer src="scripts/consumption.js"></script>
    </head>
    <body>
        <aside>
            <div class="brand">
                <b class="mark" aria-hidden="true">▥</b>
                Campus
            </div>
            <p class="nav-label">ADMINISTRATION</p>
            <nav aria-label="Gestion de la plateforme">
                <a href="#accueil">
                    <span aria-hidden="true">⌂</span>
                    Accueil
                </a>
                <a href="#universites">
                    <span aria-hidden="true">▦</span>
                    Universités
                </a>
                <a href="#consommation">
                    <span aria-hidden="true">◴</span>
                    Consommation
                </a>
                <a href="billing.php">
                    <span aria-hidden="true">€</span>
                    Facturation
                </a>
                <a href="#deploiement">
                    <span aria-hidden="true">＋</span>
                    Déploiement
                </a>
            </nav>
            <footer>
                <i></i>
                Espace administrateur
            </footer>
        </aside>
        <main>
            <header>
                <span>
                    Console
                    <em>/</em>
                    <strong id="breadcrumb">Accueil</strong>
                </span>
                <span class="account"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
            </header>
            <?php if ($notice !== ''): ?>
                <p class="notice" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <section id="overview">
                <!-- Le script remplit les indicateurs après réception du relevé Portainer. -->
                <div class="hero">
                    <div class="hero-copy">
                        <small>SUPERVISION UNIVERSITAIRE</small>
                        <h1>Vue d’ensemble</h1>
                        <p>Suivez vos universités et leurs services depuis un seul espace.</p>
                        <a class="button" href="#deploiement">＋ Déployer une université</a>
                    </div>
                    <!-- Illustration vectorielle décorative, sans dépendance externe. -->
                    <svg class="campus" viewBox="0 0 260 140" fill="none" aria-hidden="true">
                        <g stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                            <path
                                d="m20 70 100-47 111 44-97 49z M40 79v27l94 36 76-38V78 M134 116v24 M20 70v30"
                            />
                            <path
                                d="m59 67 63-30 68 28-59 30z M69 75v13l62 26 50-25V71 M131 95v19"
                            />
                            <circle cx="20" cy="103" r="3" />
                        </g>
                    </svg>
                </div>
                <div class="metrics">
                    <article>
                        <small>Universités</small>
                        <strong id="total">—</strong>
                        <span>Stacks identifiées</span>
                    </article>
                    <article>
                        <small>En service</small>
                        <strong id="active">—</strong>
                        <span>Tous les conteneurs observés actifs</span>
                    </article>
                    <article>
                        <small>À vérifier</small>
                        <strong id="attention">—</strong>
                        <span>Arrêtées, partielles ou sans conteneur</span>
                    </article>
                </div>
            </section>
            <section id="inventory" class="panel">
                <!-- L’accueil et la recherche d’universités partagent ce tableau. -->
                <div class="panel-head">
                    <div>
                        <small>INFRASTRUCTURE</small>
                        <h2 id="list-title">État des stacks</h2>
                    </div>
                    <button type="button" id="refresh">↻ Actualiser</button>
                </div>
                <div id="filters" hidden>
                    <label for="query">Rechercher une université</label>
                    <input type="search" id="query" placeholder="Nom, stack ou pays" />
                </div>
                <p id="sync" role="status">Connexion à Portainer…</p>
                <div class="scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Université</th>
                                <th>État observé</th>
                                <th>Conteneurs actifs</th>
                                <th>Pays</th>
                            </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <p id="empty" hidden></p>
                <dialog id="stack-dialog" aria-labelledby="stack-title">
                    <form action="stack-action.php" method="post" id="stack-form">
                        <h2 id="stack-title"></h2>
                        <p id="stack-description"></p>
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="id" />
                        <input type="hidden" name="name" />
                        <input type="hidden" name="action" />
                        <label id="stack-confirm-label">
                            Recopiez le nom exact de la stack pour confirmer.
                            <input name="confirmation" autocomplete="off" />
                        </label>
                        <div class="stack-buttons">
                            <button type="button" id="stack-cancel" autofocus>Annuler</button>
                            <button type="submit" id="stack-submit"></button>
                        </div>
                    </form>
                </dialog>
            </section>
            <section id="usage" class="panel" hidden>
                <small>OBSERVABILITÉ · ELK</small>
                <h1>Consommation</h1>
                <p>Comparez les ressources utilisées et explorez leur évolution dans Kibana.</p>
                <div class="usage-controls">
                    <label>
                        Université
                        <select id="usage-campus">
                            <option value="">Toutes les universités</option>
                        </select>
                    </label>
                    <label>
                        Historique Kibana
                        <select id="usage-period">
                            <option value="1h">Dernière heure</option>
                            <option value="24h">Dernières 24 heures</option>
                            <option value="7d">7 derniers jours</option>
                            <option value="30d">30 derniers jours</option>
                        </select>
                    </label>
                </div>
                <div class="usage-links">
                    <a class="button" id="resources-link" target="_blank" rel="noopener">
                        Courbes CPU et mémoire ↗
                    </a>
                    <a class="button" id="network-link" target="_blank" rel="noopener">
                        Courbes réseau ↗
                    </a>
                </div>
                <h2>Dernières mesures</h2>
                <p id="usage-status" role="status">Chargement des mesures…</p>
                <div class="scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Stack</th>
                                <th>CPU</th>
                                <th>Mémoire</th>
                                <th>Réception</th>
                                <th>Émission</th>
                                <th>Relevé</th>
                            </tr>
                        </thead>
                        <tbody id="usage-rows"></tbody>
                    </table>
                </div>
                <p class="usage-note">
                    100 % de CPU correspond à un cœur. La mémoire exclut le cache de fichiers
                    inactif. Les débits sont calculés entre deux relevés.
                </p>
            </section>
            <section id="deployment" class="panel" hidden>
                <small>PROVISIONNEMENT</small>
                <h1>Déployer une université</h1>
                <p>Créez une stack dédiée sur l’environnement Portainer.</p>
                <form action="deploy.php" method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>" />
                    <label for="name">Nom de l’université</label>
                    <input
                        id="name"
                        name="univ-name"
                        required
                        placeholder="Université de Bordeaux"
                    />
                    <label for="slug">Identifiant de la stack</label>
                    <input
                        id="slug"
                        name="univ-slug"
                        required
                        pattern="univ-[a-z0-9]+(-[a-z0-9]+)*"
                        placeholder="univ-bordeaux"
                        aria-describedby="slug-help"
                    />
                    <span id="slug-help">
                        Commencez par univ- puis utilisez des minuscules, chiffres ou tirets.
                    </span>
                    <label for="country">Pays</label>
                    <input id="country" name="univ-country" required placeholder="France" />
                    <label for="billing-email">E-mail de facturation</label>
                    <input
                        id="billing-email"
                        name="billing-email"
                        type="email"
                        maxlength="254"
                        required
                        placeholder="facturation@universite.fr"
                    />
                    <button class="button">Déployer la stack</button>
                </form>
            </section>
        </main>
    </body>
</html>
