import nodemailer from 'nodemailer';
import { countryName } from './countries.js';

// Contenu de l'e-mail de confirmation envoyé à l'université après sa demande
export function buildConfirmation({ contactName, university, country, city, email, message }, from) {
  const text = [
    `Bonjour ${contactName},`,
    '',
    `Nous avons bien reçu votre demande pour ${university}. Nous vous répondons à cette adresse.`,
    '',
    'Récapitulatif de votre demande :',
    `Établissement : ${university}`,
    `Pays : ${countryName(country) || country}`,
    ...(city ? [`Ville : ${city}`] : []),
    `Message : ${message}`,
    '',
    "Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer ce message.",
    '',
    'Digital Educational System SaaS'
  ].join('\n');

  return { from, to: email, subject: 'Votre demande a bien été reçue', text };
}

// Sans SMTP_HOST, l'envoi d'e-mails est désactivé
export function createMailer(env = process.env, transport = null) {
  if (!env.SMTP_HOST) return null;

  const from = env.MAIL_FROM || 'Digital Educational System SaaS <no-reply@digital-educational-system.localhost>';
  const smtp =
    transport ||
    nodemailer.createTransport({
      host: env.SMTP_HOST,
      port: Number(env.SMTP_PORT || 1025),
      secure: false,
      ignoreTLS: true,
      connectionTimeout: 5000,
      greetingTimeout: 5000,
      socketTimeout: 10000
    });

  return { sendConfirmation: (data) => smtp.sendMail(buildConfirmation(data, from)) };
}