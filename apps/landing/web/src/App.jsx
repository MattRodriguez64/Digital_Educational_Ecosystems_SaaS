import { useState } from 'react';
import Hero from './components/Hero.jsx';
import Services from './components/Services.jsx';
import Isolation from './components/Isolation.jsx';
import Process from './components/Process.jsx';
import ServiceStatus from './components/ServiceStatus.jsx';
import Faq from './components/Faq.jsx';
import ContactForm from './components/ContactForm.jsx';
import { useActiveSection } from './lib/useActiveSection.js';
import { ADMIN_URL } from './lib/links.js';

const CAP_PATH =
  'm21.45 8.61-9-4.5a1 1 0 0 0-.89 0l-6 3-3 1.5-1 .5a1 1 0 0 0-.55.89v6h2v-5.38l2 1v3.83c0 2.06 3.12 4.56 7 4.56s7-2.49 7-4.56v-3.83l2.45-1.22c.34-.17.55-.52.55-.89s-.21-.72-.55-.89Zm-15 .29L12 6.12l6.76 3.38L12 12.88 5.24 9.5l1.21-.61ZM17 15.45c0 .76-2.11 2.56-5 2.56s-5-1.79-5-2.56v-2.83l4.55 2.28c.14.07.29.11.45.11s.31-.04.45-.11L17 12.62z';

const NAV = [
  ['services', 'Services'],
  ['isolation', 'Isolation'],
  ['deroulement', 'Déroulement'],
  ['faq', 'Questions & contact']
];

const SECTION_IDS = [...NAV.map(([id]) => id), 'contact'];

function Logo() {
  return (
    <a className="logo" href="#top" aria-label="Digital Educational System SaaS, retour en haut de page">
      <svg width="36" height="36" viewBox="-4 -4 32 32" aria-hidden="true" focusable="false">
        <rect x="-4" y="-4" width="32" height="32" rx="6" fill="#0B2239" />
        <path d={CAP_PATH} fill="#74D4FF" />
      </svg>
      <span>Digital Educational System SaaS</span>
    </a>
  );
}

export default function App() {
  const [university, setUniversity] = useState('');
  const [menuOpen, setMenuOpen] = useState(false);
  const active = useActiveSection(SECTION_IDS);
  const current = (id) => (active === id || (id === 'faq' && active === 'contact') ? 'location' : undefined);

  return (
    <>
      <a className="skip-link" href="#main">Aller au contenu</a>
      <div className="top" id="top">
        <header className="site-header" onKeyDown={(event) => event.key === 'Escape' && setMenuOpen(false)}>
          <Logo />
          <button
            type="button"
            className="menu-toggle"
            aria-expanded={menuOpen}
            aria-controls="main-nav"
            onClick={() => setMenuOpen((open) => !open)}
          >
            {menuOpen ? 'Fermer' : 'Menu'}
          </button>
          <nav id="main-nav" aria-label="Navigation principale" className={menuOpen ? 'open' : ''} onClick={() => setMenuOpen(false)}>
            {NAV.map(([id, label]) => (
              <a key={id} href={`#${id}`} aria-current={current(id)}>{label}</a>
            ))}
            <a className="nav-login" href={ADMIN_URL}>Se connecter</a>
          </nav>
        </header>
        <Hero university={university} onUniversityChange={setUniversity} />
      </div>
      <main id="main" tabIndex={-1}>
        <Services />
        <Isolation university={university} />
        <Process />
        <Faq />
        <ContactForm university={university} onUniversityChange={setUniversity} />
      </main>
      <footer className="site-footer">
        <p>Digital Educational System SaaS, la plateforme d'écosystèmes éducatifs numériques.</p>
        <a href={ADMIN_URL}>Se connecter</a>
        <ServiceStatus />
        <p>© {new Date().getFullYear()}</p>
      </footer>
    </>
  );
}