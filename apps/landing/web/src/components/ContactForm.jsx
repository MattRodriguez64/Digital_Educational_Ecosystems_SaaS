import { useEffect, useRef, useState } from 'react';
import { sendContact } from '../lib/api.js';
import { countryOptions, isCountryCode } from '../lib/countries.js';
import { useCitySuggestions } from '../lib/useCities.js';

const RANGES = [
  ['', 'Non précisé'],
  ['lt-5000', 'Moins de 5 000 étudiants'],
  ['5000-15000', 'De 5 000 à 15 000 étudiants'],
  ['15000-30000', 'De 15 000 à 30 000 étudiants'],
  ['gt-30000', 'Plus de 30 000 étudiants']
];

const COUNTRIES = countryOptions('fr');

const OPTIONAL = new Set(['city', 'students', 'role']);

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

const INITIAL = {
  country: '',
  city: '',
  contactName: '',
  role: '',
  email: '',
  students: '',
  message: '',
  consent: false,
  website: '' // honeypot : doit rester vide
};

function validate(values, university) {
  const errors = {};
  const length = (key, value, min, max, label) => {
    const text = value.trim();
    if (text.length < min) errors[key] = min <= 1 ? `${label} est obligatoire.` : `${label} doit contenir au moins ${min} caractères.`;
    else if (text.length > max) errors[key] = `${label} ne doit pas dépasser ${max} caractères.`;
  };
  length('university', university, 2, 180, "Le nom de l'université");
  if (!isCountryCode(values.country)) errors.country = 'Choisissez un pays dans la liste.';
  if (values.city.trim().length > 120) errors.city = 'La ville ne doit pas dépasser 120 caractères.';
  length('contactName', values.contactName, 2, 120, 'Votre nom');
  length('message', values.message, 10, 2000, 'Le message');
  if (values.role.trim().length > 120) errors.role = 'La fonction ne doit pas dépasser 120 caractères.';
  if (!EMAIL_RE.test(values.email.trim())) errors.email = 'Saisissez une adresse e-mail valide.';
  if (!values.consent) errors.consent = 'Vous devez accepter le traitement de votre demande pour envoyer le formulaire.';
  return errors;
}

function Field({ id, label, error, optional, children }) {
  return (
    <div className={`field${error ? ' has-error' : ''}`}>
      <label htmlFor={id}>
        {label}
        {!optional && <span className="req" aria-hidden="true"> *</span>}
      </label>
      {children}
      {error && (
        <p className="error" id={`${id}-error`}>{error}</p>
      )}
    </div>
  );
}

export default function ContactForm({ university, onUniversityChange }) {
  const [values, setValues] = useState(INITIAL);
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState('idle'); // idle | sending | sent
  const [serverError, setServerError] = useState('');
  const [focusTick, setFocusTick] = useState(0);
  const formRef = useRef(null);
  const citySuggestions = useCitySuggestions(values.country, values.city);
  const successRef = useRef(null);

  useEffect(() => {
    if (focusTick > 0) formRef.current?.querySelector('[aria-invalid="true"]')?.focus();
  }, [focusTick]);

  useEffect(() => {
    if (status === 'sent') successRef.current?.focus();
  }, [status]);

  const set = (key) => (event) => {
    const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
    setValues((current) => ({ ...current, [key]: value }));
  };

  const fieldProps = (key) => ({
    id: `contact-${key}`,
    'aria-required': OPTIONAL.has(key) ? undefined : 'true',
    'aria-invalid': errors[key] ? 'true' : undefined,
    'aria-describedby': errors[key] ? `contact-${key}-error` : undefined
  });

  async function onSubmit(event) {
    event.preventDefault();
    setServerError('');

    const found = validate(values, university);
    if (Object.keys(found).length > 0) {
      setErrors(found);
      setFocusTick((tick) => tick + 1);
      return;
    }
    setErrors({});
    setStatus('sending');

    const result = await sendContact({
      university: university.trim(),
      country: values.country,
      city: values.city.trim(),
      contactName: values.contactName.trim(),
      role: values.role.trim(),
      email: values.email.trim(),
      students: values.students,
      message: values.message.trim(),
      consent: values.consent,
      website: values.website
    });

    if (result.ok) {
      setStatus('sent');
      return;
    }
    setStatus('idle');
    if (result.kind === 'validation') {
      setErrors(result.errors);
      setFocusTick((tick) => tick + 1);
    } else {
      setServerError(result.message);
    }
  }

  function reset() {
    setValues(INITIAL);
    onUniversityChange('');
    setErrors({});
    setServerError('');
    setStatus('idle');
  }

  return (
    <section className="section contact" id="contact" aria-labelledby="contact-title">
      <div className="contact-intro">
        <h2 id="contact-title">Parlons de votre campus</h2>
        <p>
          Ce formulaire s'adresse aux universités et aux établissements d'enseignement supérieur.
          Dites-nous qui vous êtes et ce que vous cherchez : nous revenons vers vous par e-mail.
        </p>
        <p className="small">Vos informations servent uniquement à répondre à votre demande.</p>
      </div>

      {status === 'sent' ? (
        <div className="sent" role="status">
          <h3 ref={successRef} tabIndex={-1}>Demande envoyée</h3>
          <p>Merci. Nous vous répondons à l'adresse {values.email.trim()}.</p>
          <button type="button" className="button secondary" onClick={reset}>Envoyer une autre demande</button>
        </div>
      ) : (
        <form ref={formRef} className="form" onSubmit={onSubmit} noValidate>
          <p className="form-note">Les champs marqués d'un * sont obligatoires.</p>
          <fieldset>
            <legend>Votre établissement</legend>
            <Field id="contact-university" label="Nom de l'université" error={errors.university}>
              <input
                {...fieldProps('university')}
                type="text"
                value={university}
                onChange={(event) => onUniversityChange(event.target.value)}
                autoComplete="organization"
                maxLength={180}
              />
            </Field>
            <div className="row">
              <Field id="contact-country" label="Pays" error={errors.country}>
                <select {...fieldProps('country')} value={values.country} onChange={set('country')} autoComplete="country">
                  <option value="">Choisir un pays</option>
                  {COUNTRIES.map(({ code, name }) => (
                    <option key={code} value={code}>{name}</option>
                  ))}
                </select>
              </Field>
              <Field id="contact-city" label="Ville" optional error={errors.city}>
                <input
                  {...fieldProps('city')}
                  type="text"
                  list="contact-city-suggestions"
                  value={values.city}
                  onChange={set('city')}
                  autoComplete="off"
                  maxLength={120}
                />
                <datalist id="contact-city-suggestions">
                  {citySuggestions.map(({ name, region }) => (
                    <option key={`${name}|${region}`} value={name}>{region}</option>
                  ))}
                </datalist>
              </Field>
            </div>
            <Field id="contact-students" label="Taille de l'établissement" optional error={errors.students}>
              <select {...fieldProps('students')} value={values.students} onChange={set('students')}>
                {RANGES.map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            </Field>
          </fieldset>

          <fieldset>
            <legend>Vos coordonnées</legend>
            <div className="row">
              <Field id="contact-contactName" label="Votre nom" error={errors.contactName}>
                <input {...fieldProps('contactName')} type="text" value={values.contactName} onChange={set('contactName')} autoComplete="name" maxLength={120} />
              </Field>
              <Field id="contact-role" label="Votre fonction" optional error={errors.role}>
                <input {...fieldProps('role')} type="text" value={values.role} onChange={set('role')} autoComplete="organization-title" maxLength={120} />
              </Field>
            </div>
            <Field id="contact-email" label="Adresse e-mail professionnelle" error={errors.email}>
              <input {...fieldProps('email')} type="email" value={values.email} onChange={set('email')} autoComplete="email" maxLength={254} />
            </Field>
          </fieldset>

          <fieldset>
            <legend>Votre projet</legend>
            <Field id="contact-message" label="Votre demande" error={errors.message}>
              <textarea {...fieldProps('message')} rows={5} value={values.message} onChange={set('message')} maxLength={2000} />
            </Field>
          </fieldset>

          <div className="trap" aria-hidden="true">
            <label htmlFor="contact-website">Ne pas remplir ce champ</label>
            <input id="contact-website" type="text" tabIndex={-1} autoComplete="off" value={values.website} onChange={set('website')} />
          </div>

          <div className={`field consent${errors.consent ? ' has-error' : ''}`}>
            <label className="check" htmlFor="contact-consent">
              <input
                {...fieldProps('consent')}
                type="checkbox"
                checked={values.consent}
                onChange={set('consent')}
              />
              <span>J'accepte que ces informations soient utilisées pour répondre à ma demande.<span className="req" aria-hidden="true"> *</span></span>
            </label>
            {errors.consent && <p className="error" id="contact-consent-error">{errors.consent}</p>}
          </div>

          {serverError && <p className="error banner" role="alert">{serverError}</p>}

          <button className="button" type="submit" disabled={status === 'sending'}>
            {status === 'sending' ? 'Envoi en cours…' : 'Envoyer la demande'}
          </button>
        </form>
      )}
    </section>
  );
}