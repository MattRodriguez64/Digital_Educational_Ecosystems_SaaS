import test from 'node:test';
import assert from 'node:assert/strict';
import { COUNTRY_CODES, countryName, isCountryCode } from '../countries.js';
import { COUNTRY_CODES as WEB_CODES } from '../../web/src/lib/countries.js';

test("la liste de l'API est identique à celle du formulaire", () => {
  assert.deepEqual(COUNTRY_CODES, WEB_CODES);
});

test('chaque code a un nom français', () => {
  for (const code of COUNTRY_CODES) {
    const name = countryName(code);
    assert.ok(name && name !== code, `pas de nom pour ${code}`);
  }
  assert.equal(countryName('MA'), 'Maroc');
  assert.equal(countryName('FR'), 'France');
});

test('un code inconnu ou un texte libre sont refusés', () => {
  assert.equal(isCountryCode('ZZ'), false);
  assert.equal(isCountryCode('France'), false);
  assert.equal(countryName('ZZ'), null);
});