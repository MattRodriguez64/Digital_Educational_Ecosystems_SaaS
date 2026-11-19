import { isCountryCode } from './countries.js';

const DEFAULT_URL = 'https://secure.geonames.org/searchJSON';
const TTL_MS = 60 * 60 * 1000; // une heure
const MAX_ENTRIES = 500;
const MIN_LENGTH = 2;
const MAX_LENGTH = 60;

export function createCityLookup({ username, url = DEFAULT_URL } = {}, fetchImpl = fetch) {
  if (!username) return null;

  const cache = new Map();

  async function search(country, query) {
    const text = String(query ?? '').replace(/\s+/g, ' ').trim().slice(0, MAX_LENGTH);
    if (!isCountryCode(country) || text.length < MIN_LENGTH) return [];

    const key = `${country}:${text.toLowerCase()}`;
    const hit = cache.get(key);
    if (hit && hit.expires > Date.now()) return hit.cities;

    const params = new URLSearchParams({
      name_startsWith: text,
      country,
      featureClass: 'P', // villes et villages
      orderby: 'population',
      lang: 'fr',
      username
    });
    const res = await fetchImpl(`${url}?${params}`, { signal: AbortSignal.timeout(3000) });
    if (!res.ok) throw Object.assign(new Error('geonames_http'), { code: `HTTP_${res.status}` });

    const body = await res.json();
    // GeoNames répond 200 avec un bloc "status" en cas d'erreur (compte refusé, quota atteint...).
    if (body.status) throw Object.assign(new Error('geonames_status'), { code: `GEONAMES_${body.status.value}` });

    const seen = new Set();
    const cities = [];
    for (const item of body.geonames || []) {
      const region = item.adminName1 || '';
      const id = `${item.name}|${region}`;
      if (!item.name || seen.has(id)) continue;
      seen.add(id);
      cities.push({ name: item.name, region });
    }

    if (cache.size >= MAX_ENTRIES) cache.delete(cache.keys().next().value);
    cache.set(key, { cities, expires: Date.now() + TTL_MS });
    return cities;
  }

  return { search };
}