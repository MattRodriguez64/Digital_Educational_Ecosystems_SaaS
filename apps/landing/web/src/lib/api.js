// Envoie la demande au service landing-api
export async function sendContact(payload) {
  let res;
  try {
    res = await fetch('/api/contact', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
  } catch {
    return { ok: false, kind: 'network', message: 'Connexion impossible. Vérifiez votre réseau, puis réessayez.' };
  }

  let body = null;
  try {
    body = await res.json();
  } catch {
  }

  if (res.ok) return { ok: true };
  if (res.status === 422 && body?.errors) return { ok: false, kind: 'validation', errors: body.errors };
  return {
    ok: false,
    kind: 'server',
    message: body?.message || "Votre demande n'a pas pu être envoyée. Réessayez dans un instant."
  };
}

export async function fetchCities(country, query, signal) {
  try {
    const params = new URLSearchParams({ country, q: query });
    const res = await fetch(`/api/cities?${params}`, { signal });
    if (!res.ok) return { available: true, cities: [] };
    const body = await res.json();
    return { available: body.available !== false, cities: Array.isArray(body.cities) ? body.cities : [] };
  } catch {
    return { available: true, cities: [] };
  }
}

// État du service de contact : l'API répond 200 quand elle et sa base de données fonctionnent
export async function checkHealth(signal) {
  try {
    const res = await fetch('/api/health', { signal, cache: 'no-store' });
    if (!res.ok) return false;
    const body = await res.json();
    return body?.status === 'ok';
  } catch {
    return false;
  }
}