// Les contacts enregistrés restent accessibles si Portainer est hors ligne.
const list = document.getElementById('known-stacks');
const slug = document.getElementById('contact-slug');
slug.addEventListener('change', () => {
    const match = Array.from(list.options).find((option) => option.value === slug.value);
    for (const field of ['name', 'email', 'address']) {
        document.getElementById('contact-' + field).value = match?.dataset[field] || '';
    }
});
const campus = document.getElementById('bill-campus');
const status = document.getElementById('campus-status');
const previewButton = campus.form.querySelector('button');
// Une stack déjà associée à un contact peut passer directement à la préparation de la facture.
const saved = new Set(Array.from(list.options, (option) => option.value));

campus.addEventListener('change', () => {
    const missing = campus.value !== '' && !saved.has(campus.value);
    previewButton.disabled = missing;
    status.textContent = missing
        ? 'Renseignez l’e-mail dans le contact de facturation ci-dessous, puis enregistrez-le.'
        : '';
    if (campus.value) {
        slug.value = campus.value;
        slug.dispatchEvent(new Event('change'));
        if (missing) slug.closest('details').open = true;
    }
});

// Un contact enregistré et une stack Portainer représentent la même université.
// Le slug sert de clé pour compléter les deux listes sans doublon.
async function loadUniversities() {
    try {
        const response = await fetch('stack-status.php', { cache: 'no-store' });
        if (!response.ok) throw new Error();
        const { stacks } = await response.json();
        for (const stack of stacks) {
            if (saved.has(stack.name)) continue;
            const option = new Option(stack.label, stack.name);
            option.dataset.name = stack.label;
            list.append(option);
            const title =
                stack.label === stack.name ? stack.name : stack.label + ' · ' + stack.name;
            campus.add(new Option(title + ' — contact à compléter', stack.name));
        }
        if (!campus.value)
            status.textContent = campus.options.length > 1 ? '' : 'Aucune université détectée.';
    } catch {
        status.textContent =
            'Inventaire Portainer indisponible. Les contacts déjà enregistrés restent accessibles.';
    }
}
loadUniversities();
