import { universitySlug } from '../lib/slug.js';
import CopyButton from './CopyButton.jsx';

const ADDRESSES = [
  { label: 'Cours en ligne', prefix: 'elearn' },
  { label: 'Fichiers', prefix: 'nextcloud' },
  { label: 'Support', prefix: 'support' },
  { label: 'Portail', prefix: 'moncompte' }
];

export default function Hero({ university, onUniversityChange }) {
  const slug = universitySlug(university);

  return (
    <section className="hero" aria-labelledby="hero-title">
      <div className="hero-copy">
        <h1 id="hero-title">Le campus numérique de votre université, prêt à l'emploi.</h1>
        <p className="lead">
          Cours en ligne, stockage de fichiers, support et supervision. Chaque établissement reçoit son
          propre environnement, sur un domaine à son nom.
        </p>
        <div className="try">
          <label htmlFor="hero-university">Nom de votre université</label>
          <input
            id="hero-university"
            type="text"
            value={university}
            onChange={(event) => onUniversityChange(event.target.value)}
            placeholder="Université de Pau et des Pays de l'Adour"
            autoComplete="organization"
            maxLength={180}
          />
          <p className="hint">Les adresses de votre campus apparaissent à droite.</p>
        </div>
        <a className="button" href="#contact">Demander une démonstration</a>
      </div>

      <figure className="preview" aria-live="polite">
        <figcaption>Adresses de votre campus</figcaption>
        <ul>
          {ADDRESSES.map(({ label, prefix }) => {
            const host = `${prefix}.${slug}.localhost`;
            return (
              <li key={prefix}>
                <div>
                  <span className="preview-label">{label}</span>
                  <span className="preview-url">
                    {prefix}.<mark key={slug}>{slug}</mark>.localhost
                  </span>
                </div>
                <CopyButton text={host} />
              </li>
            );
          })}
        </ul>
        <p className="preview-note">Aperçu généré à partir du nom saisi. Chaque service a sa propre adresse.</p>
      </figure>
    </section>
  );
}