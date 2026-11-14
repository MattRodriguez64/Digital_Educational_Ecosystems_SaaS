<?php

// en attendant de voir pour l'authent
$est_admin = true;
$utilisateur = 'admin';
$universite = 'Université de Pau -UPPA';
//

$pdo = new PDO('mysql:host=mariadb;dbname=support;charset=utf8mb4', 'support', 'support');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? '';

if ($est_admin && $action === 'pdf') {
    require __DIR__ . '/fpdf/fpdf.php';

    function compter($pdo, $colonne, $where, $params)
    {
        $stmt = $pdo->prepare("SELECT $colonne AS cle, COUNT(*) AS nb FROM tickets $where GROUP BY $colonne");
        $stmt->execute($params);

        $resultat = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $resultat[$ligne['cle']] = $ligne['nb'];
        }
        return $resultat;
    }

    function latin($texte)
    {
        return mb_convert_encoding((string) $texte, 'ISO-8859-1', 'UTF-8');
    }

    function section($pdf, $titre, $lignes)
    {
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(0, 9, latin($titre), 0, 1);
        $pdf->SetFont('Helvetica', '', 11);
        foreach ($lignes as $libelle => $nombre) {
            $pdf->Cell(90, 7, latin($libelle), 1);
            $pdf->Cell(30, 7, $nombre, 1, 1, 'R');
        }
        $pdf->Ln(5);
    }


    $uni = $_GET['universite'] ?? '';
    $where = '';
    $params = [];
    if ($uni !== '') {
        $where = 'WHERE university = ?';
        $params[] = $uni;
    }

    
    $st = compter($pdo, 'status', $where, $params);
    $pr = compter($pdo, 'priority', $where, $params);
    $ca = compter($pdo, 'category', $where, $params);

    $stmt = $pdo->prepare("SELECT * FROM tickets $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->Cell(0, 12, latin('Rapport de support'), 0, 1);
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->Cell(0, 7, latin('Périmètre : ' . ($uni !== '' ? $uni : 'Global')), 0, 1);
    $pdf->Cell(0, 7, latin('Généré le ' . date('d/m/Y à H:i') . ' par ' . $utilisateur), 0, 1);
    $pdf->Ln(6);

    section($pdf, 'Synthèse', [
        'Total des tickets' => array_sum($st),
        'Ouverts' => $st[0] ?? 0,
        'En cours' => $st[1] ?? 0,
        'Clôturés' => $st[2] ?? 0,
    ]);
    section($pdf, 'Par priorité', [
        'Haute' => $pr['Haute'] ?? 0,
        'Moyenne' => $pr['Moyenne'] ?? 0,
        'Basse' => $pr['Basse'] ?? 0,
    ]);
    section($pdf, 'Par catégorie', $ca);

    if ($uni === '') {
        $pu = [];
        foreach (compter($pdo, 'university', $where, $params) as $nom => $nombre) {
            $pu[$nom !== '' ? $nom : 'Non renseignée'] = $nombre;
        }
        section($pdf, 'Par université', $pu);
    }

    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->Cell(0, 9, latin('Liste des tickets'), 0, 1);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(24, 7, 'Date', 1);
    $pdf->Cell(76, 7, 'Titre', 1);
    $pdf->Cell(36, 7, 'Utilisateur', 1);
    $pdf->Cell(24, 7, latin('Priorité'), 1);
    $pdf->Cell(30, 7, 'Statut', 1, 1);

    $ls = ['Ouvert', 'En cours', 'Clôturé'];
    $pdf->SetFont('Helvetica', '', 10);
    foreach ($tickets as $t) {
        $pdf->Cell(24, 7, substr($t['created_at'], 0, 10), 1);
        $pdf->Cell(76, 7, latin(mb_strimwidth($t['title'], 0, 42, '...')), 1);
        $pdf->Cell(36, 7, latin(mb_strimwidth($t['created_by'], 0, 18, '...')), 1);
        $pdf->Cell(24, 7, latin($t['priority']), 1);
        $pdf->Cell(30, 7, latin($ls[$t['status']]), 1, 1);
    }

    $pdf->Output('I', 'rapport-support.pdf');
    exit;
}

if ($action) {
    header('Content-Type: application/json');

    if ($action === 'mes_tickets') {
        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE created_by = ? ORDER BY created_at DESC');
        $stmt->execute([$utilisateur]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'creer') {

        $titre = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($titre === '' || $description === '') {
            echo json_encode(['ok' => false, 'erreur' => 'Le sujet et la description sont obligatoires !']);
            exit;

        }
        $stmt = $pdo->prepare('INSERT INTO tickets (uuid, title, category, priority, description, created_by, university) VALUES (UUID(), ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$titre, $_POST['category'], $_POST['priority'], $description, $utilisateur, $universite]);
        echo json_encode(['ok' => true]);

    } elseif ($est_admin && $action === 'filtres') {
        echo json_encode([
            'users' => $pdo->query('SELECT DISTINCT created_by FROM tickets ORDER BY created_by')->fetchAll(PDO::FETCH_COLUMN),
            'universites' => $pdo->query('SELECT DISTINCT university FROM tickets WHERE university IS NOT NULL ORDER BY university')->fetchAll(PDO::FETCH_COLUMN),
        ]);
    } elseif ($est_admin && $action === 'tous') {
        $sql = 'SELECT * FROM tickets WHERE 1 = 1';
        $params = [];
        if (!empty($_GET['date'])) {
            $sql .= ' AND DATE(created_at) = ?';
            $params[] = $_GET['date'];
        }

        if (!empty($_GET['user'])) {
            $sql .= ' AND created_by = ?';
            $params[] = $_GET['user'];
        }

        if (!empty($_GET['universite'])) {
            $sql .= ' AND university = ?';
            $params[] = $_GET['universite'];
        }
        $stmt = $pdo->prepare($sql . ' ORDER BY status = 2, created_at DESC');
        $stmt->execute($params);

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($est_admin && $action === 'prendre') {
        $stmt = $pdo->prepare('UPDATE tickets SET status = 1, handled_by = ? WHERE uuid = ? AND status IN (0, 1)');
        $stmt->execute([$utilisateur, $_POST['uuid']]);

        echo json_encode(['ok' => true]);
    } elseif ($est_admin && $action === 'traiter') {
        $stmt = $pdo->prepare('SELECT status, handled_by FROM tickets WHERE uuid = ?');
        $stmt->execute([$_POST['uuid']]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket || $ticket['status'] != 1 || $ticket['handled_by'] !== $utilisateur) {
            echo json_encode(['ok' => false, 'erreur' => 'Ce ticket n\'est pas pris en charge par vous!.']);
            exit;
        }

        $solution = trim($_POST['solution'] ?? '');
        $status = (int) $_POST['status'] === 2 ? 2 : 1;
        if ($status === 2 && $solution === '') {
            echo json_encode(['ok' => false, 'erreur' => 'Une solution est obligatoire pour clôturer.']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE tickets SET solution = ?, status = ? WHERE uuid = ?');
        $stmt->execute([$solution, $status, $_POST['uuid']]);

        echo json_encode(['ok' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['ok' => false]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>

<body>
    <nav class="navbar navbar-dark bg-dark">
        <span class="navbar-brand">Support</span>
        <div class="d-flex align-items-center">
            <a href="sla.php" class="btn btn-outline-light btn-sm mr-3" id="btn-sla">SLA</a>
            <a href="http://localhost" class="navbar-text"><?= htmlspecialchars($utilisateur . ($est_admin ? ' (admin)' : '')) ?></a>
        </div>
    </nav>

    <div class="container my-4">
        <div class="row">
            <div class="col-md-5 mb-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Nouveau ticket</h5>
                        <form id="formulaire">
                            <div class="form-group">
                                <label>Sujet</label>
                                <input class="form-control" name="title" maxlength="120" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-6">
                                    <label>Catégorie</label>
                                    <select class="form-control" name="category">
                                        <option>Technique</option>
                                        <option>Compte</option>
                                        <option>Autre</option>
                                    </select>
                                </div>
                                <div class="form-group col-6">
                                    <label>Priorité</label>
                                    <select class="form-control" name="priority">
                                        <option>Basse</option>
                                        <option>Moyenne</option>
                                        <option>Haute</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control" name="description" rows="6" required></textarea>
                            </div>
                            <p class="text-warning" id="message"></p>
                            <button class="btn btn-primary btn-block">Envoyer</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7 mb-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Mes tickets</h5>
                        <div class="list-group" id="liste"></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($est_admin) { ?>
            <div class="card bg-secondary text-white mt-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h5 class="card-title">Gestion des tickets</h5>
                        <button class="btn btn-light btn-sm mb-2" data-toggle="modal" data-target="#modal-stats">Statistiques</button>
                    </div>
                    <div class="form-row mb-3">
                        <div class="col-md-3">
                            <label>Date</label>
                            <input type="date" class="form-control" id="filtre-date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label>Utilisateur</label>
                            <select class="form-control" id="filtre-user">
                                <option value="">Tous</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Université</label>
                            <select class="form-control" id="filtre-universite">
                                <option value="">Toutes</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn btn-secondary btn-block" id="filtre-reset">Reset</button>
                        </div>
                    </div>
                    <p class="text-white-50" id="compteur"></p>
                    <div id="liste-admin"></div>
                </div>
            </div>

            <div class="modal fade" id="modal-stats" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-secondary text-white">
                            <h5 class="modal-title">Statistiques</h5>
                            <button class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <label>Périmètre du rapport</label>
                            <select class="form-control" id="pdf-universite">
                                <option value="">Global</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-dark" data-dismiss="modal">Fermer</button>
                            <button class="btn btn-primary" onclick="genererPdf()">Générer le PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="modal fade" id="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="modal-titre"></h5>
                    <button class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <span class="badge" id="modal-statut"></span>
                    <small class="d-block text-muted my-2" id="modal-infos"></small>
                    <p id="modal-description" style="white-space: pre-wrap"></p>
                    <div class="alert alert-success" id="modal-solution" style="white-space: pre-wrap"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="toasts" style="position: fixed; top: 70px; right: 20px; z-index: 1080"></div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const estAdmin = <?= $est_admin ? 'true' : 'false' ?>;
        const utilisateur = <?= json_encode($utilisateur) ?>;
        const statuts = ['Ouvert', 'En cours', 'Clôturé'];
        const couleurs = ['badge-danger', 'badge-warning', 'badge-success'];
        let mesTickets = [];
        let tousTickets = [];

        function esc(texte) {
            const div = document.createElement('div');
            div.textContent = texte ?? '';
            return div.innerHTML;
        }

        function toast(message, couleur = 'bg-danger') {
            const div = document.createElement('div');
            div.className = 'toast text-white ' + couleur;
            div.setAttribute('role', 'alert');
            div.dataset.delay = 4000;
            div.innerHTML = `<div class="toast-body">${esc(message)}</div>`;

            document.getElementById('toasts').appendChild(div);
            $(div).on('hidden.bs.toast', () => div.remove());
            
            $(div).toast('show');
        }

        function badge(s) {
            return `<span class="badge ${couleurs[s]}">${statuts[s]}</span>`;
        }

        function afficherMesTickets() {
            const liste = document.getElementById('liste');
            if (!mesTickets.length) {

                liste.innerHTML = '<p class="text-white-50">Aucun ticket pour le moment.</p>';
                return;
            }
            liste.innerHTML = mesTickets.map((t, i) => `
                <a href="#" class="list-group-item list-group-item-action bg-light text-dark" onclick="ouvrir(${i}); return false;">
                    <div class="d-flex justify-content-between">
                        <strong>${esc(t.title)}</strong>
                        ${badge(t.status)}
                    </div>
                    <small>${esc(t.category)} · Priorité ${esc(t.priority)} · ${esc(t.created_at)}</small>
                </a>`).join('');

        }

        function afficherAdmin() {
            const liste = document.getElementById('liste-admin');
            if (!tousTickets.length) {
                liste.innerHTML = '<p class="text-white-50">Aucun ticket pour ces filtres.</p>';
                return;
            }

            liste.innerHTML = tousTickets.map((t, i) => `
                <div class="card bg-light text-dark mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <strong>${esc(t.title)}</strong>
                            ${badge(t.status)}
                        </div>
                        <small>${esc(t.category)} · Priorité ${esc(t.priority)} · par ${esc(t.created_by)} (${esc(t.university)}) le ${esc(t.created_at)}</small>
                        <p class="mt-2" style="white-space: pre-wrap">${esc(t.description)}</p>
                        ${zoneTraitement(t, i)}
                    </div>
                </div>`).join('');
        }

        function zoneTraitement(t, i) {
            if (t.status == 0) {
                return `<button class="btn btn-primary" onclick="prendre(${i})">Prendre en charge</button>`;
            }

            if (t.status == 1 && t.handled_by === utilisateur) {
                return `
                    <textarea class="form-control mb-2" id="solution-${i}" rows="3" placeholder="Solution">${esc(t.solution)}</textarea>
                    <button class="btn btn-dark" onclick="traiter(${i}, 1)">Enregistrer</button>
                    <button class="btn btn-success" onclick="traiter(${i}, 2)">Clôturer</button>`;
            }

            if (t.status == 1) {
                return `
                    <p class="mb-2">Pris en charge par ${esc(t.handled_by)}</p>
                    <button class="btn btn-warning" onclick="prendre(${i})">Reprendre</button>`;
            }

            return `<div class="alert alert-success mb-0" style="white-space: pre-wrap">Solution : ${esc(t.solution)}</div>`;
        }

        function ouvrir(i) {
            const t = mesTickets[i];
            document.getElementById('modal-titre').textContent = t.title;

            const etat = document.getElementById('modal-statut');
            etat.textContent = statuts[t.status];
            etat.className = 'badge ' + couleurs[t.status];

            document.getElementById('modal-infos').textContent =
                t.category + ' · Priorité ' + t.priority + ' · ' + t.created_at +
                (t.handled_by ? ' · Pris en charge par ' + t.handled_by : '');

            document.getElementById('modal-description').textContent = t.description;
            const solution = document.getElementById('modal-solution');
            solution.style.display = t.solution ? 'block' : 'none';

            solution.textContent = t.solution ? 'Solution : ' + t.solution : '';
            $('#modal').modal('show');
        }

        async function traiter(i, status) {
            const solution = document.getElementById('solution-' + i).value.trim();

            if (status == 2 && solution === '') {
                toast('Écris une solution avant de clôturer le ticket.');
                return;
            }
            const d = new FormData();

            d.append('uuid', tousTickets[i].uuid);
            d.append('status', status);
            d.append('solution', solution);

            const r = await fetch('?action=traiter', {
                method: 'POST',
                body: d
            });
            const res = await r.json();

            if (!res.ok) {
                toast(res.erreur);
            }
            charger();
        }

        async function prendre(i) {
            const d = new FormData();
            d.append('uuid', tousTickets[i].uuid);

            await fetch('?action=prendre', {
                method: 'POST',
                body: d
            });
            charger();
        }

        function remplir(id, valeurs, libelle) {
            const select = document.getElementById(id);
            const courant = select.value;
            select.innerHTML = `<option value="">${libelle}</option>` + valeurs.map(v => `<option>${esc(v)}</option>`).join('');
            select.value = courant;
        }

        async function chargerFiltres() {
            const r = await fetch('?action=filtres');
            const filtres = await r.json();
            remplir('filtre-user', filtres.users, 'Tous');
            remplir('filtre-universite', filtres.universites, 'Toutes');
            remplir('pdf-universite', filtres.universites, 'Global');
        }

        function genererPdf() {

            const universite = document.getElementById('pdf-universite').value;
            window.open('?action=pdf&universite=' + encodeURIComponent(universite), '_blank');
        }

        async function chargerAdmin() {

            const params = new URLSearchParams({
                date: document.getElementById('filtre-date').value,
                user: document.getElementById('filtre-user').value,
                universite: document.getElementById('filtre-universite').value
            });
            const url = '?action=tous&' + params;
            const r = await fetch(url);
            tousTickets = await r.json();
            document.getElementById('compteur').textContent = tousTickets.length + ' ticket(s)';
            afficherAdmin();
        }

        async function charger() {

            const r = await fetch('?action=mes_tickets');
            mesTickets = await r.json();
            afficherMesTickets();
            if (estAdmin) {
                await chargerFiltres();
                chargerAdmin();
            }
        }

        if (estAdmin) {
            
            ['filtre-date', 'filtre-user', 'filtre-universite'].forEach(id => {
                document.getElementById(id).addEventListener('change', chargerAdmin);
            });
            document.getElementById('filtre-reset').addEventListener('click', () => {
                document.getElementById('filtre-date').value = new Date().toLocaleDateString('sv-SE');
                document.getElementById('filtre-user').value = '';
                document.getElementById('filtre-universite').value = '';
                chargerAdmin();
            });
        }

        document.getElementById('formulaire').addEventListener('submit', async (e) => {
            e.preventDefault();
            const d = new FormData(e.target);
            const r = await fetch('?action=creer', {
                method: 'POST',
                body: d
            });
            const res = await r.json();
            document.getElementById('message').textContent = res.ok ? '' : res.erreur;
            if (res.ok) {
                e.target.reset();
                charger();

            }
        });

        charger();
    </script>
</body>

</html>