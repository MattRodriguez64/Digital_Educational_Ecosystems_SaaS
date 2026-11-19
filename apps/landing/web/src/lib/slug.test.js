import test from 'node:test';
import assert from 'node:assert/strict';
import { universitySlug, EXAMPLE_SLUG } from './slug.js';

const VALID = /^univ-[a-z0-9]+(?:-[a-z0-9]+)*$/;

test('retire les mots vides et les accents', () => {
  assert.equal(universitySlug("Université de Pau et des Pays de l'Adour"), 'univ-pau-pays-adour');
});

test('renvoie l’exemple quand le champ est vide', () => {
  assert.equal(universitySlug(''), EXAMPLE_SLUG);
  assert.equal(universitySlug('   '), EXAMPLE_SLUG);
  assert.equal(universitySlug('Université de la'), EXAMPLE_SLUG);
});

test('produit toujours un identifiant accepté par le serveur de gestion', () => {
  for (const name of ['École Nationale Supérieure des Mines de Rabat', 'Kraków – Uniwersytet', '  ***  ', "L'Université d'Évry", 'A'.repeat(300)]) {
    assert.match(universitySlug(name), VALID);
    assert.ok(universitySlug(name).length <= 32);
  }
});