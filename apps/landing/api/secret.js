import { readFileSync } from 'node:fs';

// Lit un secret Docker : si NOM_FILE est défini (fichier monté dans /run/secrets),
// on lit ce fichier ; sinon on retombe sur la variable d'environnement NOM.
export function readSecret(name, env = process.env) {
  const file = env[`${name}_FILE`];
  if (file) return readFileSync(file, 'utf8').trim();
  return env[name];
}