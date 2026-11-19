import test from 'node:test';
import assert from 'node:assert/strict';
import { validateContact } from '../validate.js';

const valid = {
  university: 'Université de Pau et des Pays de l’Adour',
  country: 'FR',
  city: 'Pau',
  contactName: 'Eneko Nevil',
  role: 'Directrice du numérique',
  email: 'nevil.eneko@univ-pau.fr',
  students: '15000-30000',
  message: 'Nous aimerions une démonstration de la plateforme.',
  consent: true
};

test('accepte une demande complète', () => {
  const r = validateContact(valid);
  assert.equal(r.ok, true);
  assert.equal(r.data.email, 'nevil.eneko@univ-pau.fr');
});

test('la fonction et la tranche sont facultatives', () => {
  const r = validateContact({ ...valid, role: undefined, students: undefined });
  assert.equal(r.ok, true);
  assert.equal(r.data.role, '');
});

test('rejette un e-mail invalide', () => {
  const r = validateContact({ ...valid, email: 'pas-un-email' });
  assert.equal(r.ok, false);
  assert.ok(r.errors.email);
});

test('exige le consentement', () => {
  const r = validateContact({ ...valid, consent: false });
  assert.ok(r.errors.consent);
});

test('rejette un message trop court et un champ manquant', () => {
  const r = validateContact({ ...valid, message: 'court', university: undefined });
  assert.ok(r.errors.message);
  assert.ok(r.errors.university);
});

test('rejette une tranche inconnue', () => {
  const r = validateContact({ ...valid, students: '1-2' });
  assert.ok(r.errors.students);
});

test('détecte le honeypot', () => {
  const r = validateContact({ ...valid, website: 'http://spam.example' });
  assert.equal(r.spam, true);
});

test('ignore un corps qui n’est pas un objet', () => {
  assert.equal(validateContact(null).ok, false);
  assert.equal(validateContact('texte').ok, false);
});

test('refuse les valeurs qui ne sont pas des chaînes', () => {
  const r = validateContact({ ...valid, university: { $ne: '' } });
  assert.ok(r.errors.university);
});

test('le pays doit être un code de la liste, pas un texte libre', () => {
  assert.ok(validateContact({ ...valid, country: 'France' }).errors.country);
  assert.ok(validateContact({ ...valid, country: 'fr' }).errors.country);
  assert.ok(validateContact({ ...valid, country: undefined }).errors.country);
  assert.equal(validateContact({ ...valid, country: 'MA' }).ok, true);
});

test('la ville est facultative et limitée à 120 caractères', () => {
  const sans = validateContact({ ...valid, city: undefined });
  assert.equal(sans.ok, true);
  assert.equal(sans.data.city, '');
  assert.ok(validateContact({ ...valid, city: 'x'.repeat(121) }).errors.city);
});