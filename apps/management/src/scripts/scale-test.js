(() => {

    const panel = document.getElementById('scale-test');
    const campus = document.getElementById('usage-campus');
    const status = document.getElementById('scale-status');
    const high = document.getElementById('scale-high');
    const low = document.getElementById('scale-low');

    if (!panel || !campus) return;

    let pending = false;
    let knownName = '';

    function disable() {
        high.disabled = true;
        low.disabled = true;
    }

    async function request(action = 'status') {
        if (pending || location.hash !== '#consommation' || document.hidden) return;

        const name = campus.value;
        if (!name) {
            knownName = '';
            disable();
            status.textContent = 'Sélectionnez une université ci-dessus.';
            return;
        }

        pending = true;
        disable();

        if (action !== 'status') status.textContent = name + ' : application du seuil simulé…';

        try {
            const response = await fetch('scale-test.php', {
                method: 'POST', cache: 'no-store', credentials: 'same-origin',
                body: new URLSearchParams({ csrf: panel.dataset.csrf, name, action }),
            });
            if (response.status === 401) {
                location.replace('index.php');
                return;
            }
            const data = await response.json();

            if (!response.ok) throw new Error(data.error || 'Demande non confirmée.');

            if (campus.value !== name) return;

            knownName = name;

            const load = data.simulated_load === null ? 'Aucune charge simulée' : 'Simulation : ' + data.simulated_load + ' %';
            const state = data.blocked ? ' · Mise à jour : ' + data.update_state
                : data.running === data.desired ? ' · Objectif atteint' : ' · Déploiement en cours';
            status.textContent = name + ' — ' + load + ' · Portail : ' + data.running
                + '/' + data.desired + ' réplicas en cours d’exécution' + state;
            const allowed = !data.blocked && [1, 2].includes(data.desired);
            high.disabled = !allowed;
            low.disabled = !allowed;

        } catch (error) {
            if (campus.value === name) {
                status.textContent = name + ' : ' + error.message + ' Vérification automatique dans 10 s.';
            }
        } finally {
            pending = false;
            if (campus.value !== name) request();
        }
    }

    campus.addEventListener('change', () => { disable(); request(); });
    high.addEventListener('click', () => request('high'));
    low.addEventListener('click', () => request('low'));
    window.addEventListener('hashchange', () => request());
    document.addEventListener('visibilitychange', () => request());
    // La liste de consommation se reconstruit après chaque relev

    new MutationObserver(() => {
        if (campus.value !== knownName) { disable(); request(); }
    }).observe(campus, { childList: true });
    
    request();
    setInterval(request, 10000);
})();
