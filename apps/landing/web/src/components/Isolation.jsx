import { useEffect, useRef, useState } from 'react';
import { universitySlug } from '../lib/slug.js';

const SERVICES = ['elearn', 'nextcloud', 'support', 'moncompte'];

function Stack({ title, name, mine }) {
  return (
    <div className={`topo-stack${mine ? ' mine' : ''}`}>
      <h3>{title}</h3>
      <p className="topo-name">{name}</p>
      <ul className="topo-services">
        {SERVICES.map((service) => (
          <li key={service}>{service}</li>
        ))}
      </ul>
      <div className="topo-db">
        <strong>MariaDB</strong>
        <span>Réseau interne : joignable seulement depuis cette pile</span>
      </div>
      {mine && (
        <>
          <span className="pulse pulse-in" aria-hidden="true" />
          <span className="pulse pulse-out" aria-hidden="true" />
        </>
      )}
    </div>
  );
}

export default function Isolation({ university }) {
  const slug = universitySlug(university);

  const figure = useRef(null);
  const [phase, setPhase] = useState('idle');
  const [sent, setSent] = useState(false);

  function sendRequest() {
    const el = figure.current;
    if (el) {
      el.classList.remove('run');
      void el.offsetWidth; // relance l'animation si on clique une 2eme fois
      el.classList.add('run');
    }
    setSent(true);
  }
  useEffect(() => {
    const el = figure.current;
    if (!el || typeof IntersectionObserver === 'undefined') return undefined;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return undefined;
    setPhase('armed');
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setPhase('go');
          observer.disconnect();
        }
      },
      { threshold: 0.35 }
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <section className="section isolation" id="isolation" aria-labelledby="isolation-title">
      <div className="section-head">
        <h2 id="isolation-title">Chaque campus a sa propre base de données</h2>
        <p>
          Votre campus tourne dans sa propre pile de conteneurs. Seuls les services de cette pile
          atteignent sa base : un autre établissement n'y a pas accès.
        </p>
      </div>

      <figure className={`topology ${phase}`} ref={figure}>
        <div className="topo-gate">
          <strong>Traefik</strong>
          <span>Une seule entrée, une adresse par service</span>
        </div>

        <div className="topo-stacks">
          <Stack title="Votre campus" name={slug} mine />
          <Stack title="Un autre campus" name="univ-…" />
          <Stack title="Un autre campus" name="univ-…" />
        </div>

        <div className="topo-meter">
          <strong>Supervision</strong>
          <span>Processeur, mémoire et réseau mesurés pour chaque campus</span>
        </div>

        <figcaption className="topo-actions">
          <button type="button" className="run-button" onClick={sendRequest}>
            {sent ? 'Renvoyer une requête' : 'Envoyer une requête'}
          </button>
          <p className="topo-status" role="status">
            {sent
              ? "Traefik l'a reçue et seul votre campus l'a traitée. Les bases des autres campus n'ont pas été contactées."
              : 'Suivez le trajet d\'une requête vers votre campus.'}
          </p>
        </figcaption>
      </figure>

      <dl className="promises">
        <div>
          <dt>Des données séparées</dt>
          <dd>MariaDB, fichiers Nextcloud et données Moodle sont dans des volumes propres à votre campus.</dd>
        </div>
        <div>
          <dt>Une consommation mesurée</dt>
          <dd>Ces mesures servent de base à votre facture mensuelle.</dd>
        </div>
        <div>
          <dt>Un modèle unique</dt>
          <dd>Chaque campus naît du même modèle : mêmes services, mêmes réglages, déploiement reproductible.</dd>
        </div>
      </dl>
    </section>
  );
}