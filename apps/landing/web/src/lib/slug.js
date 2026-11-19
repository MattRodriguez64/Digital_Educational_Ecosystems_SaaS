// Même règle que apps/management/src/deploy.php : l'identifiant d'une stack commence par "univ-" puis des mots en minuscules sans accents, séparés par des tirets
const IGNORED = new Set([
  'de', 'des', 'du', 'la', 'le', 'les', 'l', 'd', 'et', 'of', 'the', 'and',
  'universite', 'university', 'universidad', 'universita'
]);

export const EXAMPLE_SLUG = 'univ-pau';

export function universitySlug(name) {
  const words = String(name)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()
    .split(' ')
    .filter((word) => word && !IGNORED.has(word));

  if (words.length === 0) return EXAMPLE_SLUG;
  return `univ-${words.slice(0, 3).join('-')}`.slice(0, 32).replace(/-+$/, '');
}