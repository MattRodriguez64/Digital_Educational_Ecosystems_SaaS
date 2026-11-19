import { createHmac } from 'node:crypto';
import express from 'express';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import mysql from 'mysql2/promise';
import { validateContact } from './validate.js';
import { readSecret } from './secret.js';
import { createMailer } from './mailer.js';
import { createCityLookup } from './cities.js';
import { isCountryCode } from './countries.js';

const PORT = Number(process.env.PORT || 3000);

function log(level, msg, extra = {}) {
  console.log(JSON.stringify({ '@timestamp': new Date().toISOString(), service: 'landing-api', level, msg, ...extra }));
}

const dbPassword = readSecret('DB_PASSWORD');

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'mariadb',
  user: process.env.DB_USER,
  password: dbPassword,
  database: process.env.DB_NAME || 'landing',
  charset: 'utf8mb4',
  waitForConnections: true,
  connectionLimit: 5,
  connectTimeout: 5000
});

const mailer = createMailer();

const cities = createCityLookup({ username: readSecret('GEONAMES_USERNAME'), url: process.env.GEONAMES_URL || undefined });

const MAX_REQUESTS = 5;
const WINDOW_MINUTES = 15;
const ipHash = (ip) => createHmac('sha256', dbPassword || 'landing').update(String(ip)).digest('hex');

const app = express();

app.set('trust proxy', 1);
app.use(helmet());
app.use(express.json({ limit: '10kb' }));

app.use((req, res, next) => {
  const start = process.hrtime.bigint();
  res.on('finish', () => {
    const ms = Number(process.hrtime.bigint() - start) / 1e6;
    log('info', 'request', { method: req.method, path: req.path, status: res.statusCode, ms: Math.round(ms) });
  });
  next();
});

const tooMany = (res) =>
  res.status(429).json({ ok: false, error: 'rate_limited', message: 'Trop de demandes envoyées. Réessayez dans quelques minutes.' });

const floodLimiter = rateLimit({
  windowMs: WINDOW_MINUTES * 60 * 1000,
  limit: 30,
  standardHeaders: true,
  legacyHeaders: false,
  handler: (_req, res) => tooMany(res)
});

const cityLimiter = rateLimit({
  windowMs: 60 * 1000,
  limit: 60,
  standardHeaders: true,
  legacyHeaders: false,
  handler: (_req, res) => res.status(429).json({ ok: false, error: 'rate_limited', cities: [] })
});

app.get('/api/health', async (_req, res) => {
  try {
    await pool.query('SELECT 1');
    res.json({ status: 'ok' });
  } catch (err) {
    log('error', 'health_db_failed', { code: err.code });
    res.status(503).json({ status: 'unavailable' });
  }
});

app.post('/api/contact', floodLimiter, async (req, res) => {
  const result = validateContact(req.body);

  if (result.spam) {
    log('warn', 'honeypot_triggered');
    return res.status(201).json({ ok: true });
  }
  if (!result.ok) {
    return res.status(422).json({ ok: false, error: 'validation', errors: result.errors });
  }

  const { university, country, city, contactName, role, email, students, message } = result.data;
  const visitor = ipHash(req.ip);

  try {
    const [recent] = await pool.execute(
      `SELECT COUNT(*) AS n FROM contact_requests WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL ${WINDOW_MINUTES} MINUTE)`,
      [visitor]
    );
    if (Number(recent[0].n) >= MAX_REQUESTS) {
      log('warn', 'contact_rate_limited');
      return tooMany(res);
    }

    const [insert] = await pool.execute(
      'INSERT INTO contact_requests (university, country, city, contact_name, role, email, students, message, ip_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [university, country, city || null, contactName, role || null, email, students || null, message, visitor]
    );
    log('info', 'contact_request_created', { id: insert.insertId, country });
    res.status(201).json({ ok: true });

    if (mailer) {
      mailer
        .sendConfirmation(result.data)
        .then(() => log('info', 'confirmation_email_sent', { id: insert.insertId }))
        .catch((err) => log('warn', 'confirmation_email_failed', { id: insert.insertId, code: err.code }));
    }
  } catch (err) {
    log('error', 'contact_insert_failed', { code: err.code });
    res.status(503).json({ ok: false, error: 'unavailable', message: "Votre demande n'a pas pu être enregistrée. Réessayez dans un instant." });
  }
});

app.get('/api/cities', cityLimiter, async (req, res) => {
  if (!cities) return res.json({ available: false, cities: [] });

  const country = String(req.query.country || '').toUpperCase();
  if (!isCountryCode(country)) return res.status(422).json({ ok: false, error: 'validation', cities: [] });

  try {
    const list = await cities.search(country, req.query.q);
    res.set('Cache-Control', 'private, max-age=300');
    res.json({ available: true, cities: list });
  } catch (err) {
    log('warn', 'cities_failed', { code: err.code || err.name });
    res.status(502).json({ ok: false, error: 'cities_unavailable', cities: [] });
  }
});

app.use('/api', (_req, res) => res.status(404).json({ ok: false, error: 'not_found' }));

app.use((err, _req, res, _next) => {
  if (err.type === 'entity.too.large') return res.status(413).json({ ok: false, error: 'too_large' });
  if (err.type === 'entity.parse.failed') return res.status(400).json({ ok: false, error: 'bad_json' });
  log('error', 'unhandled', { message: err.message });
  res.status(500).json({ ok: false, error: 'internal' });
});

const server = app.listen(PORT, () => log('info', 'listening', { port: PORT, mail: Boolean(mailer) }));

// Arrêt propre quand Docker envoie SIGTERM
function shutdown(signal) {
  log('info', 'shutdown', { signal });
  server.close(async () => {
    await pool.end().catch(() => {});
    process.exit(0);
  });
  setTimeout(() => process.exit(1), 8000).unref();
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));