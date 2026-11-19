// Validation côté serveur du formulaire de contact.
// Le front valide aussi, mais seul ce fichier fait foi.

import { isCountryCode } from './countries.js';

export const STUDENT_RANGES = ['lt-5000', '5000-15000', '15000-30000', 'gt-30000'];

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const CONTROL_RE = /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g;

function clean(value) {
  return typeof value === 'string' ? value.replace(CONTROL_RE, '').trim() : null;
}

function checkLength(errors, key, value, min, max, label) {
  if (value === null || value.length < min) {
    errors[key] = min <= 1 ? `${label} est obligatoire.` : `${label} doit contenir au moins ${min} caractères.`;
  } else if (value.length > max) {
    errors[key] = `${label} ne doit pas dépasser ${max} caractères.`;
  }
}

export function validateContact(body) {
  const input = body && typeof body === 'object' ? body : {};

  if (typeof input.website === 'string' && input.website.trim() !== '') {
    return { ok: false, spam: true };
  }

  const data = {
    university: clean(input.university),
    country: clean(input.country),
    city: clean(input.city) || '',
    contactName: clean(input.contactName),
    role: clean(input.role) || '',
    email: clean(input.email),
    students: clean(input.students) || '',
    message: clean(input.message)
  };

  const errors = {};
  checkLength(errors, 'university', data.university, 2, 180, "Le nom de l'université");
  // Le pays arrive sous forme de code ISO.
  if (!isCountryCode(data.country)) errors.country = 'Choisissez un pays dans la liste.';
  if (data.city.length > 120) errors.city = 'La ville ne doit pas dépasser 120 caractères.';
  checkLength(errors, 'contactName', data.contactName, 2, 120, 'Votre nom');
  if (data.role.length > 120) errors.role = 'La fonction ne doit pas dépasser 120 caractères.';
  checkLength(errors, 'message', data.message, 10, 2000, 'Le message');

  if (data.email === null || data.email.length > 254 || !EMAIL_RE.test(data.email)) {
    errors.email = 'Saisissez une adresse e-mail valide.';
  }
  if (data.students !== '' && !STUDENT_RANGES.includes(data.students)) {
    errors.students = 'Choisissez une des tranches proposées.';
  }
  if (input.consent !== true) {
    errors.consent = 'Vous devez accepter le traitement de votre demande pour envoyer le formulaire.';
  }

  return Object.keys(errors).length > 0 ? { ok: false, errors } : { ok: true, data };
}