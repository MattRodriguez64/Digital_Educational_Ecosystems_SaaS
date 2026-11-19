import test from 'node:test';
import assert from 'node:assert/strict';
import { COUNTRY_CODES, countryOptions, isCountryCode } from './countries.js';

test('les codes sont uniques et sur deux lettres majuscules', () => {
  assert.equal(new Set(COUNTRY_CODES).size, COUNTRY_CODES.length);
  assert.ok(COUNTRY_CODES.every((code) => /^[A-Z]{2}$/.test(code)));
});

test('la liste affichée est en français et triée', () => {
  const options = countryOptions('fr');
  assert.equal(options.length, COUNTRY_CODES.length);
  assert.equal(options.find((o) => o.code === 'MA').name, 'Maroc');
  assert.equal(options.find((o) => o.code === 'ES').name, 'Espagne');
  const names = options.map((o) => o.name);
  assert.deepEqual(names, [...names].sort((a, b) => a.localeCompare(b, 'fr')));
});

test('isCountryCode refuse ce qui ne vient pas de la liste', () => {
  assert.equal(isCountryCode('FR'), true);
  assert.equal(isCountryCode('France'), false);
  assert.equal(isCountryCode('fr'), false);
  assert.equal(isCountryCode(undefined), false);
});