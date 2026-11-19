import test from 'node:test';
import assert from 'node:assert/strict';
import nodemailer from 'nodemailer';
import { buildConfirmation, createMailer } from '../mailer.js';
import { readSecret } from '../secret.js';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

const data = {
  contactName: 'Eneko Nevil',
  university: 'Université de Pau',
  country: 'MA',
  city: 'Rabat',
  email: 'nevil.eneko@univ-pau.fr',
  message: 'Une démonstration, svp.'
};

test('le message de confirmation reprend la demande', () => {
  const mail = buildConfirmation(data, 'Digital Educational System SaaS <no-reply@x.test>');
  assert.equal(mail.to, 'nevil.eneko@univ-pau.fr');
  assert.match(mail.text, /Eneko Nevil/);
  assert.match(mail.text, /Université de Pau/);
  assert.match(mail.text, /Une démonstration/);
});

test("l'e-mail donne le nom français du pays, et la ville si elle est connue", () => {
  const mail = buildConfirmation(data, 'x@x.test');
  assert.match(mail.text, /Pays : Maroc/);
  assert.match(mail.text, /Ville : Rabat/);
  assert.doesNotMatch(buildConfirmation({ ...data, city: '' }, 'x@x.test').text, /Ville :/);
});

test('pas de SMTP_HOST : pas de mailer', () => {
  assert.equal(createMailer({}), null);
});

test('envoie le message au transport', async () => {
  const mailer = createMailer({ SMTP_HOST: 'mailpit' }, nodemailer.createTransport({ jsonTransport: true }));
  const info = await mailer.sendConfirmation(data);
  const sent = JSON.parse(info.message);
  assert.equal(sent.to[0].address, 'nevil.eneko@univ-pau.fr');
  assert.equal(sent.subject, 'Votre demande a bien été reçue');
});

test('readSecret lit le fichier puis la variable', () => {
  const dir = mkdtempSync(join(tmpdir(), 'sec-'));
  const file = join(dir, 'pw');
  writeFileSync(file, 'motdepasse\r\n');
  assert.equal(readSecret('DB_PASSWORD', { DB_PASSWORD_FILE: file }), 'motdepasse');
  assert.equal(readSecret('DB_PASSWORD', { DB_PASSWORD: 'depuis-env' }), 'depuis-env');
});