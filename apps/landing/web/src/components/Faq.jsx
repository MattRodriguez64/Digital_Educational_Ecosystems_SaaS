const QUESTIONS = [
  {
    question: "Mes données sont-elles mélangées avec celles d'autres universités ?",
    answer:
      "Non. Chaque université a sa propre base MariaDB et ses propres volumes, dans une pile dédiée. La base n'est joignable que depuis les services de votre campus."
  },
  {
    question: 'Que contient mon campus au départ ?',
    answer:
      'Un compte administrateur et des comptes de démonstration pour essayer chaque service, avec des cours de démonstration dans la plateforme de cours en ligne.'
  },
  {
    question: 'Comment est calculée la facture ?',
    answer:
      "Un forfait mensuel, auquel s'ajoutent la consommation mesurée (processeur, mémoire, réseau) et la TVA. Les tarifs vous sont communiqués avec la réponse à votre demande."
  },
  {
    question: 'Que devient le message que j’envoie ?',
    answer:
      "Il est enregistré uniquement pour vous répondre, et vous recevez un e-mail de confirmation. Nous ne gardons pas votre adresse IP : seulement une empreinte qui sert à limiter les envois répétés (cinq par quart d'heure)."
  }
];

export default function Faq() {
  return (
    <section className="section faq" id="faq" aria-labelledby="faq-title">
      <div className="section-head">
        <h2 id="faq-title">Avant de nous écrire</h2>
      </div>
      <div className="faq-list">
        {QUESTIONS.map(({ question, answer }) => (
          <details key={question}>
            <summary>{question}</summary>
            <p>{answer}</p>
          </details>
        ))}
      </div>
    </section>
  );
}