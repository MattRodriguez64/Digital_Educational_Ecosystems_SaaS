import test from 'node:test';
import assert from 'node:assert/strict';
import { createCityLookup } from '../cities.js';

const reply = (body, ok = true, status = 200) => ({ ok, status, json: async () => body });

function fakeFetch(body, calls) {
  return async (url) => {
    calls.push(new URL(url));
    return reply(body);
  };
}

const payload = {
  geonames: [
    { name: 'Bayonne', adminName1: 'Nouvelle-Aquitaine' },
    { name: 'Bayonne', adminName1: 'Nouvelle-Aquitaine' },
    { name: 'Bayeux', adminName1: 'Normandie' },
    { adminName1: 'Sans nom' }
  ]
};

test('sans nom d’utilisateur GeoNames, le service est désactivé', () => {
  assert.equal(createCityLookup({ username: '' }), null);
  assert.equal(createCityLookup({}), null);
});

test('interroge GeoNames avec le pays et renvoie des villes sans doublon', async () => {
  const calls = [];
  const lookup = createCityLookup({ username: 'demo-user' }, fakeFetch(payload, calls));
  const cities = await lookup.search('FR', '  bay ');
  assert.deepEqual(cities, [
    { name: 'Bayonne', region: 'Nouvelle-Aquitaine' },
    { name: 'Bayeux', region: 'Normandie' }
  ]);
  const params = calls[0].searchParams;
  assert.equal(params.get('country'), 'FR');
  assert.equal(params.get('name_startsWith'), 'bay');
  assert.equal(params.get('featureClass'), 'P');
  assert.equal(params.get('username'), 'demo-user');
});

test('la même recherche est servie par le cache', async () => {
  const calls = [];
  const lookup = createCityLookup({ username: 'u' }, fakeFetch(payload, calls));
  await lookup.search('FR', 'bay');
  await lookup.search('FR', 'BAY');
  assert.equal(calls.length, 1);
  await lookup.search('MA', 'bay');
  assert.equal(calls.length, 2);
});

test('une recherche trop courte ou un pays invalide ne sortent pas du serveur', async () => {
  const calls = [];
  const lookup = createCityLookup({ username: 'u' }, fakeFetch(payload, calls));
  assert.deepEqual(await lookup.search('FR', 'b'), []);
  assert.deepEqual(await lookup.search('France', 'bay'), []);
  assert.equal(calls.length, 0);
});

test('une erreur GeoNames (quota, compte refusé) fait échouer la recherche', async () => {
  const quota = createCityLookup({ username: 'u' }, async () => reply({ status: { message: 'limit', value: 19 } }));
  await assert.rejects(quota.search('FR', 'bay'), { code: 'GEONAMES_19' });

  const down = createCityLookup({ username: 'u' }, async () => reply({}, false, 503));
  await assert.rejects(down.search('FR', 'bay'), { code: 'HTTP_503' });
});