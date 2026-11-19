const SERVICES = [
  {
    name: 'Cours en ligne',
    tech: 'Moodle',
    text: "Une plateforme d'apprentissage avec des cours et des comptes de démonstration, prête dès le premier jour."
  },
  {
    name: 'Fichiers partagés',
    tech: 'Nextcloud',
    text: 'Stockage et partage de documents, avec des quotas distincts pour les étudiants, les enseignants et les dossiers de cours.'
  },
  {
    name: 'Support',
    tech: 'Application web',
    text: 'Des tickets classés par catégorie et par priorité, le suivi des délais de réponse et un rapport PDF.'
  },
  {
    name: 'Supervision',
    tech: 'Kibana',
    text: 'Des tableaux de bord pour suivre les performances de votre campus.'
  },
  {
    name: 'Portail',
    tech: 'Site web',
    text: 'Un point d\'entrée unique qui regroupe tous les services au nom de votre établissement.'
  }
];

export default function Services() {
  return (
    <section className="section" id="services" aria-labelledby="services-title">
      <div className="section-head">
        <h2 id="services-title">Ce que reçoit chaque université</h2>
        <p>Cinq services déployés ensemble, chacun sur sa propre adresse.</p>
      </div>
      <ul className="services">
        {SERVICES.map((service) => (
          <li key={service.name}>
            <h3>{service.name}</h3>
            <p>{service.text}</p>
            <span className="tech">{service.tech}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}