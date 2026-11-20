(() => {
    const campus = document.getElementById('usage-campus');
    const period = document.getElementById('usage-period');
    const status = document.getElementById('usage-status');
    const rows = document.getElementById('usage-rows');
    const format = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });
    let samples = [];
    let dashboards = {};
    let kibanaUrl = null;
    let pending = false;

    function updateLinks() {
        period.disabled = !kibanaUrl;
        // La période et la sélection accompagnent l’administrateur dans les deux dashboards.
        const query = campus.value ? 'university: "' + campus.value + '"' : '';
        const global =
            '(refreshInterval:(pause:!f,value:30000),time:(from:now-' + period.value + ',to:now))';
        const local = "(query:(language:kuery,query:'" + query + "'))";
        for (const [link, key] of [
            ['resources-link', 'campus-resources'],
            ['network-link', 'campus-network'],
        ]) {
            const id = dashboards[key];
            if (!id || !kibanaUrl) {
                document.getElementById(link).removeAttribute('href');
                document.getElementById(link).title = 'Tableaux de bord Kibana non configurés';
                continue;
            }
            document.getElementById(link).removeAttribute('title');
            document.getElementById(link).href =
                kibanaUrl.replace(/\/$/, '') + '/app/dashboards#/view/' +
                id +
                '?_g=' +
                encodeURIComponent(global) +
                '&_a=' +
                encodeURIComponent(local);
        }
    }

    // Un relevé ancien reste visible avec son heure, pour éviter de le confondre avec une mesure actuelle.
    function render() {
        rows.replaceChildren();
        for (const sample of samples) {
            if (campus.value && campus.value !== sample.university) continue;
            const row = rows.insertRow();
            row.insertCell().textContent = sample.university;
            for (const [key, unit] of [
                ['cpu_pct', '%'],
                ['memory_mib', 'Mio'],
                ['rx_kib_s', 'Kio/s'],
                ['tx_kib_s', 'Kio/s'],
            ]) {
                row.insertCell().textContent =
                    sample[key] == null ? '—' : format.format(sample[key]) + ' ' + unit;
            }
            const coverage = {
                stopped: 'Université arrêtée',
                waiting: 'En attente de tâches actives',
                incomplete: 'Mesure incomplète : nœud absent, relevé ancien ou tâche remplacée',
                warming: 'Premier relevé · calcul des débits en cours',
            }[sample.coverage];
            if (coverage) {
                row.insertCell().textContent = coverage;
                continue;
            }
            const time = new Date(sample['@timestamp']);
            const stale = Date.now() - time.getTime() > 90000;
            row.insertCell().textContent = stale
                ? 'Ancien · ' + time.toLocaleString('fr-FR')
                : time.toLocaleTimeString('fr-FR');
        }
        updateLinks();
    }

    async function refresh() {
        if (location.hash !== '#consommation' || document.hidden || pending) return;
        pending = true;
        try {
            const response = await fetch('consumption.php', { cache: 'no-store' });
            if (response.status === 401) {
                location.replace('index.php');
                return;
            }
            if (!response.ok) throw new Error();
            const data = await response.json();
            samples = data.samples;
            dashboards = data.dashboards;
            kibanaUrl = data.kibana_url;
            const selected = campus.value;
            campus.replaceChildren(new Option('Toutes les universités', ''));
            for (const sample of samples)
                campus.add(new Option(sample.university, sample.university));
            if (samples.some((sample) => sample.university === selected)) campus.value = selected;
            status.textContent = samples.length
                ? 'Relevés Swarm · CPU : 100 % = un cœur · Actualisation toutes les 30 s'
                : 'En attente des premiers relevés universitaires.';
            render();
        } catch {
            samples = samples.map(sample => ({ ...sample, coverage: 'incomplete',
                cpu_pct: null, memory_mib: null, rx_kib_s: null, tx_kib_s: null }));
            status.textContent = 'Collecte ou inventaire Swarm indisponible. Nouvelle tentative dans 30 s.';
            render();
        } finally {
            pending = false;
        }
    }
    campus.addEventListener('change', render);
    period.addEventListener('change', updateLinks);
    window.addEventListener('hashchange', refresh);
    document.addEventListener('visibilitychange', refresh);
    updateLinks();
    refresh();
    setInterval(refresh, 30000);
})();
