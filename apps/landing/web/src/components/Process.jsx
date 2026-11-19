const STEPS = [
  { title: 'Vous nous écrivez', text: 'Décrivez votre établissement et vos besoins avec le formulaire ci-dessous.' },
  { title: 'Nous déployons votre campus', text: 'Une pile dédiée est créée à partir de notre modèle, sur un domaine à votre nom.' },
  { title: 'Vous recevez vos accès', text: 'Un compte administrateur et des comptes de démonstration pour essayer chaque service.' },
  { title: 'Nous suivons la consommation', text: "La supervision mesure l'usage réel, et la facture mensuelle en découle." }
];

export default function Process() {
  return (
    <section className="section process" id="deroulement" aria-labelledby="process-title">
      <div className="section-head">
        <h2 id="process-title">Du premier message à votre campus en ligne</h2>
      </div>
      <ol className="steps">
        {STEPS.map((step, index) => (
          <li key={step.title}>
            <span className="step-number" aria-hidden="true">{index + 1}</span>
            <h3>{step.title}</h3>
            <p>{step.text}</p>
          </li>
        ))}
      </ol>
    </section>
  );
}