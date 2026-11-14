const byId = (id) => document.getElementById(id);
const states = {
    active: 'En service',
    partial: 'Partielle',
    stopped: 'Arrêtée',
    empty: 'Sans conteneur',
};
let snapshot;
let pending = false;
const actions = {
    start: ['Démarrer', 'Portainer va démarrer les services de cette université.'],
    stop: ['Arrêter', 'Les services de cette université seront indisponibles jusqu’au redémarrage de la stack.'],
    delete: ['Supprimer', 'La stack sera retirée de Portainer et ses services seront supprimés.'],
};

// Conserver l’identité sélectionnée dans le formulaire pendant les actualisations du tableau.
function confirmAction(stack, action) {
    const form = byId('stack-form');
    const removing = action === 'delete';
    const [verb, description] = actions[action];
    form.elements.namedItem('id').value = stack.id;
    form.elements.namedItem('name').value = stack.name;
    form.elements.namedItem('action').value = action;
    const confirmation = form.elements.namedItem('confirmation');
    confirmation.value = '';
    confirmation.required = removing;
    confirmation.setCustomValidity('');
    byId('stack-confirm-label').hidden = !removing;
    byId('stack-title').textContent = verb + ' ' + stack.name + ' ?';
    byId('stack-description').textContent = description;
    byId('stack-submit').textContent = verb;
    byId('stack-submit').disabled = false;
    byId('stack-dialog').showModal();
}

// Le fragment de l’adresse (#accueil, par exemple) détermine la section à afficher.
function navigate() {
    const pages = {
        accueil: 'Accueil',
        universites: 'Universités',
        consommation: 'Consommation',
        deploiement: 'Déploiement',
    };
    const hash = location.hash.slice(1);
    const page = Object.hasOwn(pages, hash) ? hash : 'accueil';
    byId('breadcrumb').textContent = pages[page];
    byId('overview').hidden = page !== 'accueil';
    byId('inventory').hidden = !['accueil', 'universites'].includes(page);
    byId('usage').hidden = page !== 'consommation';
    byId('deployment').hidden = page !== 'deploiement';
    byId('filters').hidden = page !== 'universites';
    byId('list-title').textContent = page === 'universites' ? 'Universités' : 'État des stacks';
    for (const link of document.querySelectorAll('nav a')) {
        if (link.hash === '#' + page) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
    }
    render();
}

// textContent permet d’afficher les noms fournis par Portainer sans les interpréter comme du HTML.
function element(tag, text, className) {
    const node = document.createElement(tag);
    node.textContent = text;
    if (className) node.className = className;
    return node;
}

// Les cartes résument toutes les stacks ; la recherche filtre seulement les lignes du tableau.
function render() {
    if (!snapshot) return;
    const stacks = snapshot.stacks;
    const active = stacks.filter((stack) => stack.state === 'active').length;
    byId('total').textContent = stacks.length;
    byId('active').textContent = active;
    byId('attention').textContent = stacks.length - active;
    const query = byId('filters').hidden ? '' : byId('query').value.trim().toLocaleLowerCase('fr');
    const rows = [];
    for (const stack of stacks) {
        if (
            ![stack.label, stack.name, stack.country]
                .join(' ')
                .toLocaleLowerCase('fr')
                .includes(query)
        )
            continue;
        const row = document.createElement('tr');
        const name = row.insertCell();
        name.append(element('strong', stack.label));
        if (stack.name !== stack.label) name.append(element('code', stack.name));
        row.insertCell().append(element('span', states[stack.state], 'badge ' + stack.state));
        row.insertCell().textContent = stack.running + ' / ' + stack.total;
        row.insertCell().textContent = stack.country || '—';
        // Placer les commandes sous le nom pour qu’elles restent visibles sur un écran étroit.
        if (stack.id) {
            const buttons = element('div', '', 'stack-buttons');
            for (const [action, [label]] of Object.entries(actions)) {
                const button = element('button', label);
                button.type = 'button';
                // Une stack marquée active peut encore avoir des conteneurs Docker arrêtés.
                const stopped = stack.managed === 2;
                const needsStart = stopped || (stack.managed === 1 && (!stack.total || stack.running < stack.total));
                button.disabled = (action === 'start' && !needsStart) || (action === 'stop' && stopped);
                button.addEventListener('click', () => confirmAction(stack, action));
                buttons.append(button);
            }
            name.append(buttons);
        } else {
            name.append(element('small', 'Hors gestion Portainer'));
        }
        rows.push(row);
    }
    byId('rows').replaceChildren(...rows);
    byId('empty').hidden = rows.length > 0;
    byId('empty').textContent = query
        ? 'Aucune université ne correspond à la recherche.'
        : 'Aucune stack univ-* sur cet environnement.';
}

async function refresh() {
    // Une seule requête à la fois, même si l’utilisateur clique pendant l’actualisation automatique.
    if (pending || document.hidden) return;
    pending = true;
    byId('refresh').disabled = true;
    try {
        const response = await fetch('stack-status.php', { cache: 'no-store' });
        if (response.status === 401) {
            location.replace('index.php');
            return;
        }
        if (!response.ok) throw new Error('Relevé indisponible');
        snapshot = await response.json();
        render();
        const time = new Date(snapshot.at).toLocaleTimeString('fr-FR');
        byId('sync').textContent = 'Dernier relevé à ' + time + ' · Actualisation toutes les 15 s';
    } catch {
        byId('sync').textContent = snapshot
            ? 'Connexion interrompue · Dernier relevé conservé à ' +
              new Date(snapshot.at).toLocaleTimeString('fr-FR')
            : 'Relevé indisponible. Nouvelle tentative dans 15 s.';
    } finally {
        pending = false;
        byId('refresh').disabled = false;
    }
}

window.addEventListener('hashchange', navigate);
byId('stack-cancel').addEventListener('click', () => byId('stack-dialog').close());
byId('stack-form').addEventListener('input', (event) => event.target.setCustomValidity(''));
byId('stack-form').addEventListener('submit', (event) => {
    const fields = event.currentTarget.elements;
    if (fields.namedItem('action').value === 'delete'
        && fields.namedItem('confirmation').value !== fields.namedItem('name').value) {
        event.preventDefault();
        fields.namedItem('confirmation').setCustomValidity('Le nom doit correspondre exactement.');
        fields.namedItem('confirmation').reportValidity();
        return;
    }
    byId('stack-submit').disabled = true;
});
byId('query').addEventListener('input', render);
byId('refresh').addEventListener('click', refresh);
document.addEventListener('visibilitychange', refresh);
navigate();
refresh();
setInterval(refresh, 15000);
