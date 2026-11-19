import test from 'node:test';
import assert from 'node:assert/strict';
import { copyText } from './clipboard.js';

function withNavigator(value, run) {
  const original = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
  Object.defineProperty(globalThis, 'navigator', { value, configurable: true });
  return run().finally(() => {
    if (original) Object.defineProperty(globalThis, 'navigator', original);
    else delete globalThis.navigator;
  });
}

test('copie avec l’API moderne quand elle existe', async () => {
  let copied = null;
  const ok = await withNavigator({ clipboard: { writeText: async (text) => { copied = text; } } }, () => copyText('elearn.univ-pau.localhost'));
  assert.equal(ok, true);
  assert.equal(copied, 'elearn.univ-pau.localhost');
});

test('renvoie false si rien ne permet de copier', async () => {
  const ok = await withNavigator({}, () => copyText('x'));
  assert.equal(ok, false);
});

test('renvoie false si le navigateur refuse la copie', async () => {
  const ok = await withNavigator({ clipboard: { writeText: async () => { throw new Error('refusé'); } } }, () => copyText('x'));
  assert.equal(ok, false);
});