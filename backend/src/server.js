const express = require('express');
const helmet = require('helmet');
const cors = require('cors');
const { Pool } = require('pg');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');
const { randomUUID, randomBytes, createHmac, timingSafeEqual } = require('crypto');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const { getVehicleStats } = require('./services/stats');
const { getVehicleReminders } = require('./services/reminders');
const { encryptSecret, sendUserMail, resolveSmtpHost, ALLOWED_PORTS, loadDefaultSmtp, withDefaultSmtp } = require('./services/mail');
const notifier = require('./services/notifier');

// Media Upload Konfiguration
// Standard-Uploadpfad: im Backend-Projektordner unter ../uploads (z. B. /srv/www/.../uploads)
const BACKEND_ROOT = path.resolve(__dirname, '..');
const UPLOAD_DIR = process.env.UPLOAD_DIR
  ? (path.isAbsolute(process.env.UPLOAD_DIR)
    ? process.env.UPLOAD_DIR
    : path.resolve(BACKEND_ROOT, process.env.UPLOAD_DIR))
  : path.resolve(BACKEND_ROOT, 'uploads');
const MEDIA_BASE_URL = process.env.MEDIA_BASE_URL || '/uploads';

const app = express();

app.use(helmet());
app.use(cors());
// 2 MB erlaubt größere CSV-Importe (Frontend sendet max. 500 Zeilen pro Anfrage)
app.use(express.json({ limit: '2mb' }));

// Statischer File-Server für Uploads
// Nur Bilddateien ausliefern; nosniff + CSP verhindern, dass Browser Uploads als HTML/Skript ausführen
app.use(MEDIA_BASE_URL, (req, res, next) => {
  if (!/\.(jpe?g|png|gif|webp)$/i.test(req.path)) return res.status(404).end();
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; sandbox");
  return next();
}, express.static(UPLOAD_DIR));

const PORT = process.env.PORT || 3000;

if (!process.env.DATABASE_URL) {
  throw new Error('DATABASE_URL fehlt');
}
if (!process.env.JWT_SECRET || process.env.JWT_SECRET.length < 16) {
  throw new Error('JWT_SECRET fehlt oder ist zu kurz');
}

const pool = new Pool({ connectionString: process.env.DATABASE_URL });

// Upload-Verzeichnis erstellen falls nicht vorhanden
if (!fs.existsSync(UPLOAD_DIR)) {
  fs.mkdirSync(UPLOAD_DIR, { recursive: true });
}

const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, UPLOAD_DIR);
  },
  // Nie den Dateinamen des Uploaders übernehmen: Endung wird erst nach Prüfung des Inhalts vergeben
  filename: (req, file, cb) => {
    cb(null, `${randomUUID()}.upload`);
  }
});

// Bildtyp anhand des Dateiinhalts (Magic Bytes) bestimmen – der vom Client gesendete Typ ist nicht vertrauenswürdig
function detectImageType(buf) {
  if (buf.length >= 3 && buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return { ext: '.jpg', mime: 'image/jpeg' };
  if (buf.length >= 8 && buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return { ext: '.png', mime: 'image/png' };
  if (buf.length >= 6 && ['GIF87a', 'GIF89a'].includes(buf.subarray(0, 6).toString('ascii'))) return { ext: '.gif', mime: 'image/gif' };
  if (buf.length >= 12 && buf.subarray(0, 4).toString('ascii') === 'RIFF' && buf.subarray(8, 12).toString('ascii') === 'WEBP') return { ext: '.webp', mime: 'image/webp' };
  return null;
}

// Nach multer: nur echte Bilder behalten und mit passender Endung speichern
async function verifyUploadedImage(req, res, next) {
  if (!req.file) return next();
  try {
    const fd = await fs.promises.open(req.file.path, 'r');
    const { buffer, bytesRead } = await fd.read(Buffer.alloc(16), 0, 16, 0);
    await fd.close();
    const type = detectImageType(buffer.subarray(0, bytesRead));
    if (!type) {
      await fs.promises.unlink(req.file.path).catch(() => {});
      return res.status(400).json({ error: 'invalid_image' });
    }
    const filename = req.file.filename.replace(/\.upload$/, type.ext);
    const target = path.join(UPLOAD_DIR, filename);
    await fs.promises.rename(req.file.path, target);
    Object.assign(req.file, { filename, path: target, mimetype: type.mime });
    return next();
  } catch (e) {
    await fs.promises.unlink(req.file.path).catch(() => {});
    return res.status(500).json({ error: 'server_error' });
  }
}

const upload = multer({
  storage,
  limits: {
    fileSize: 20 * 1024 * 1024 // 20 MB
  },
  fileFilter: (req, file, cb) => {
    const allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (allowedMimes.includes(file.mimetype)) {
      cb(null, true);
    } else {
      cb(new Error('Nur JPEG, PNG, GIF und WebP Bilder sind erlaubt.'), false);
    }
  }
});

function signToken(user) {
  const jti = randomUUID();
  return jwt.sign(
    { sub: user.id, email: user.email, jti },
    process.env.JWT_SECRET,
    { expiresIn: '7d' }
  );
}

// "Zuletzt aktiv" für die Benutzerverwaltung: höchstens einmal pro Stunde und Benutzer in die Datenbank schreiben
const lastSeenWritten = new Map();
const LAST_SEEN_INTERVAL_MS = 60 * 60 * 1000;

function touchLastSeen(userId) {
  const now = Date.now();
  if (!userId || now - (lastSeenWritten.get(userId) || 0) < LAST_SEEN_INTERVAL_MS) return;
  lastSeenWritten.set(userId, now);
  pool.query(
    `UPDATE users SET last_seen_at = now()
     WHERE id = $1 AND (last_seen_at IS NULL OR last_seen_at < now() - interval '1 hour')`,
    [userId]
  ).catch((e) => console.error('[last_seen]', e.message));
}

async function recordLogin(userId) {
  lastSeenWritten.set(String(userId), Date.now());
  await pool.query('UPDATE users SET last_login_at = now(), last_seen_at = now() WHERE id = $1', [userId])
    .catch((e) => console.error('[last_login]', e.message));
}

async function auth(req, res, next) {
  const h = req.header('Authorization') || '';
  const m = h.match(/^Bearer\s+(.+)$/i);
  if (!m) return res.status(401).json({ error: 'missing_token' });

  try {
    const payload = jwt.verify(m[1], process.env.JWT_SECRET, { algorithms: ['HS256'] });

    // Falls Token eine jti besitzt, prüfen wir, ob es widerrufen wurde
    if (payload.jti) {
      const revoked = await pool.query(
        'SELECT 1 FROM revoked_tokens WHERE jti = $1',
        [payload.jti]
      );
      if (revoked.rowCount > 0) {
        return res.status(401).json({ error: 'token_revoked' });
      }
    }

    // User-weite Revocation: alle Tokens vor revoked_after sind ungültig
    if (payload.sub) {
      const r2 = await pool.query(
        'SELECT revoked_after FROM user_token_revocations WHERE user_id = $1',
        [payload.sub]
      );
      if (r2.rowCount === 1 && payload.iat) {
        const revokedAfter = new Date(r2.rows[0].revoked_after);
        const tokenIat = new Date(payload.iat * 1000); // iat ist in Sekunden
        if (tokenIat < revokedAfter) {
          return res.status(401).json({ error: 'token_revoked_for_user' });
        }
      }
    }

    req.user = payload;
    touchLastSeen(String(payload.sub || ''));
    return next();
  } catch {
    return res.status(401).json({ error: 'invalid_token' });
  }
}

// --- TOTP (RFC 6238) für 2FA: 30 s, 6 Stellen, SHA1 – kompatibel mit gängigen Authenticator-Apps ---
const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Encode(buf) {
  let bits = 0;
  let value = 0;
  let out = '';
  for (const byte of buf) {
    value = (value << 8) | byte;
    bits += 8;
    while (bits >= 5) {
      out += BASE32_ALPHABET[(value >>> (bits - 5)) & 31];
      bits -= 5;
    }
  }
  if (bits > 0) out += BASE32_ALPHABET[(value << (5 - bits)) & 31];
  return out;
}

function base32Decode(str) {
  const clean = String(str).toUpperCase().replace(/[^A-Z2-7]/g, '');
  let bits = 0;
  let value = 0;
  const out = [];
  for (const ch of clean) {
    value = (value << 5) | BASE32_ALPHABET.indexOf(ch);
    bits += 5;
    if (bits >= 8) {
      out.push((value >>> (bits - 8)) & 255);
      bits -= 8;
    }
  }
  return Buffer.from(out);
}

function totpCode(secretBase32, counter) {
  const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(BigInt(counter));
  const hmac = createHmac('sha1', base32Decode(secretBase32)).update(msg).digest();
  const offset = hmac[hmac.length - 1] & 0xf;
  const num = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1000000;
  return String(num).padStart(6, '0');
}

// Erlaubt ±1 Zeitfenster (je 30 s) für leicht abweichende Uhren
function verifyTotp(secretBase32, code) {
  const given = String(code || '').replace(/\s/g, '');
  if (!secretBase32 || !/^\d{6}$/.test(given)) return false;
  const counter = Math.floor(Date.now() / 1000 / 30);
  for (const delta of [-1, 0, 1]) {
    const expected = Buffer.from(totpCode(secretBase32, counter + delta));
    if (timingSafeEqual(expected, Buffer.from(given))) return true;
  }
  return false;
}

// --- Schutz vor Durchprobieren (Passwort, 2FA-Code): Fehlversuche je Schlüssel zählen ---
// Im Speicher des Prozesses; reicht für eine API-Instanz. Zählt nur Fehlversuche, Erfolg setzt zurück.
const failedAttempts = new Map();
const ATTEMPT_WINDOW_MS = 15 * 60 * 1000;

function isLockedOut(key, max) {
  const entry = failedAttempts.get(key);
  if (!entry || entry.resetAt < Date.now()) return false;
  return entry.count >= max;
}

function recordFailure(key) {
  const now = Date.now();
  const entry = failedAttempts.get(key);
  if (!entry || entry.resetAt < now) failedAttempts.set(key, { count: 1, resetAt: now + ATTEMPT_WINDOW_MS });
  else entry.count++;
}

function clearFailures(key) {
  failedAttempts.delete(key);
}

setInterval(() => {
  const now = Date.now();
  for (const [key, entry] of failedAttempts) if (entry.resetAt < now) failedAttempts.delete(key);
}, 10 * 60 * 1000).unref();

const LOGIN_MAX_FAILURES = 10;
const MFA_MAX_FAILURES = 5;

function tooManyAttempts(res) {
  return res.status(429).json({ error: 'too_many_attempts', retry_after_minutes: 15 });
}

// Kurzlebiges Token für den 2FA-Schritt (eigener Schlüssel, kann nicht als Zugangstoken benutzt werden)
const MFA_TOKEN_SECRET = `${process.env.JWT_SECRET}:mfa-step`;

// Interne Fehler nur ins Log schreiben, nicht an den Client (verrät sonst Tabellen/Spalten)
function serverError(res, e) {
  console.error('[server_error]', e);
  return res.status(500).json({ error: 'server_error' });
}

// Optionale Zahl aus dem Request: undefined bleibt undefined, null/'' wird null
function optionalNumber(value) {
  if (value === undefined) return undefined;
  if (value === null || value === '') return null;
  return Number(value);
}

// Optionaler Text aus dem Request: undefined bleibt undefined, null/leer wird null
function optionalText(value) {
  if (value === undefined) return undefined;
  if (value === null) return null;
  return String(value).trim() || null;
}

// Beta-Phase: an, bis BETA_PHASE=false gesetzt wird. Neue Konten werden in der Beta als Beta-Tester markiert.
function isBetaPhase() {
  return process.env.BETA_PHASE !== 'false';
}

// Admins werden über ADMIN_USER_IDS (kommagetrennte User-IDs) in der .env festgelegt
function isAdmin(req) {
  const adminIds = String(process.env.ADMIN_USER_IDS || '')
    .split(',')
    .map((id) => id.trim())
    .filter(Boolean);
  return adminIds.includes(String(req.user?.sub || ''));
}

function requireAdmin(req, res, next) {
  if (!isAdmin(req)) return res.status(403).json({ error: 'forbidden' });
  return next();
}

// Zugriff nur auf den eigenen User (:userId), außer für Admins
function requireSelfOrAdmin(req, res, next) {
  const userId = String(req.params.userId || '').trim();
  if (userId !== String(req.user?.sub || '') && !isAdmin(req)) {
    return res.status(403).json({ error: 'forbidden' });
  }
  return next();
}

// Healthcheck
app.get('/api/v1/health', (req, res) => {
  res.status(200).json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    service: 'fuelstat-api-v2',
    registration_enabled: process.env.REGISTRATION_ENABLED !== 'false',
    beta_phase: isBetaPhase()
  });
});

// Whoami - zeigt aktuell angemeldeten User
app.get('/api/v1/whoami', auth, async (req, res) => {
  try {
    // Zusätzlich User-Daten aus DB holen für vollständige Info
    const r = await pool.query(
      `SELECT id, email, username, mfa_enabled, profile_image_url, beta_tester, created_at, updated_at
       FROM users WHERE id = $1`,
      [req.user.sub]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'user_not_found' });
    }

    res.json({
      id: r.rows[0].id,
      email: r.rows[0].email,
      username: r.rows[0].username,
      mfa_enabled: r.rows[0].mfa_enabled,
      profile_image_url: r.rows[0].profile_image_url,
      beta_tester: r.rows[0].beta_tester,
      created_at: r.rows[0].created_at,
      updated_at: r.rows[0].updated_at,
      is_admin: isAdmin(req),
      token: {
        jti: req.user.jti || null,
        iat: req.user.iat ? new Date(req.user.iat * 1000).toISOString() : null,
        exp: req.user.exp ? new Date(req.user.exp * 1000).toISOString() : null
      }
    });
  } catch (e) {
    serverError(res, e);
  }
});

// DB Ping
app.get('/api/v1/db-ping', async (req, res) => {
  try {
    const r = await pool.query('SELECT 1 AS ok');
    res.json({ ok: true, result: r.rows[0] });
  } catch (e) {
    console.error('[db-ping]', e);
    res.status(500).json({ ok: false });
  }
});

// Register
app.post('/api/v1/auth/register', async (req, res) => {
  // Öffentliche Registrierung abschaltbar (z. B. für eine private Installation)
  if (process.env.REGISTRATION_ENABLED === 'false') return res.status(403).json({ error: 'registration_disabled' });
  const email = String(req.body?.email || '').trim().toLowerCase();
  const password = String(req.body?.password || '');

  if (!email || !email.includes('@')) return res.status(400).json({ error: 'invalid_email' });
  if (password.length < 8) return res.status(400).json({ error: 'password_too_short' });

  try {
    const passwordHash = await bcrypt.hash(password, 12);
    const r = await pool.query(
      `INSERT INTO users (email, password_hash, beta_tester)
       VALUES ($1, $2, $3)
       RETURNING id, email, username, mfa_enabled, profile_image_url, beta_tester, created_at, updated_at`,
      [email, passwordHash, isBetaPhase()]
    );
    const user = r.rows[0];
    await recordLogin(user.id);
    res.status(201).json({ token: signToken(user), user });
  } catch (e) {
    if (e.code === '23505') return res.status(409).json({ error: 'email_already_exists' });
    serverError(res, e);
  }
});

// Login
app.post('/api/v1/auth/login', async (req, res) => {
  const email = String(req.body?.email || '').trim().toLowerCase();
  const password = String(req.body?.password || '');

  if (!email || !password) return res.status(400).json({ error: 'missing_credentials' });

  try {
    const r = await pool.query(
      `SELECT id, email, password_hash, username, mfa_enabled, mfa_secret, profile_image_url, created_at, updated_at
       FROM users WHERE email = $1`,
      [email]
    );
    const loginKey = `login:${email}`;
    if (isLockedOut(loginKey, LOGIN_MAX_FAILURES)) return tooManyAttempts(res);

    if (r.rowCount === 0) {
      recordFailure(loginKey);
      return res.status(401).json({ error: 'invalid_credentials' });
    }

    const user = r.rows[0];
    const ok = await bcrypt.compare(password, user.password_hash);
    if (!ok) {
      recordFailure(loginKey);
      return res.status(401).json({ error: 'invalid_credentials' });
    }
    clearFailures(loginKey);

    // 2FA: erst nach korrektem Passwort nach dem Code fragen
    if (user.mfa_enabled) {
      if (req.body?.code === undefined || req.body?.code === '') {
        // mfaToken erlaubt den zweiten Schritt (POST /auth/login/mfa), ohne das Passwort erneut zu senden
        const mfaToken = jwt.sign({ sub: user.id }, MFA_TOKEN_SECRET, { expiresIn: '5m' });
        return res.status(401).json({ error: 'mfa_required', mfaToken });
      }
      const mfaKey = `mfa:${user.id}`;
      if (isLockedOut(mfaKey, MFA_MAX_FAILURES)) return tooManyAttempts(res);
      if (!verifyTotp(user.mfa_secret, req.body.code)) {
        recordFailure(mfaKey);
        return res.status(401).json({ error: 'invalid_mfa_code' });
      }
      clearFailures(mfaKey);
    }

    delete user.password_hash;
    delete user.mfa_secret;
    await recordLogin(user.id);
    res.json({ token: signToken(user), user });
  } catch (e) {
    serverError(res, e);
  }
});

// 2FA-Login, Schritt 2: mfaToken aus der mfa_required-Antwort + Code aus der Authenticator-App
app.post('/api/v1/auth/login/mfa', async (req, res) => {
  let payload;
  try {
    payload = jwt.verify(String(req.body?.mfaToken || ''), MFA_TOKEN_SECRET, { algorithms: ['HS256'] });
  } catch {
    return res.status(401).json({ error: 'invalid_mfa_token' });
  }
  const mfaKey = `mfa:${payload.sub}`;
  if (isLockedOut(mfaKey, MFA_MAX_FAILURES)) return tooManyAttempts(res);
  try {
    const r = await pool.query(
      `SELECT id, email, username, mfa_enabled, mfa_secret, profile_image_url, created_at, updated_at
       FROM users WHERE id = $1`,
      [payload.sub]
    );
    if (r.rowCount === 0 || !r.rows[0].mfa_enabled) return res.status(401).json({ error: 'invalid_mfa_token' });
    const user = r.rows[0];
    if (!verifyTotp(user.mfa_secret, req.body?.code)) {
      recordFailure(mfaKey);
      return res.status(401).json({ error: 'invalid_mfa_code' });
    }
    clearFailures(mfaKey);
    delete user.mfa_secret;
    await recordLogin(user.id);
    res.json({ token: signToken(user), user });
  } catch (e) {
    serverError(res, e);
  }
});

// Change own password (current password required). Revokes all existing tokens and returns a new one.
app.post('/api/v1/auth/password', auth, async (req, res) => {
  const currentPassword = String(req.body?.currentPassword || '');
  const newPassword = String(req.body?.newPassword || '');
  if (!currentPassword || !newPassword) return res.status(400).json({ error: 'missing_password' });
  if (newPassword.length < 8) return res.status(400).json({ error: 'password_too_short' });

  try {
    const r = await pool.query('SELECT id, email, password_hash FROM users WHERE id = $1', [req.user.sub]);
    if (r.rowCount === 0) return res.status(404).json({ error: 'user_not_found' });
    const pwKey = `password:${req.user.sub}`;
    if (isLockedOut(pwKey, LOGIN_MAX_FAILURES)) return tooManyAttempts(res);
    const ok = await bcrypt.compare(currentPassword, r.rows[0].password_hash);
    if (!ok) {
      recordFailure(pwKey);
      return res.status(401).json({ error: 'invalid_current_password' });
    }
    clearFailures(pwKey);

    const passwordHash = await bcrypt.hash(newPassword, 12);
    await pool.query('UPDATE users SET password_hash = $1, updated_at = now() WHERE id = $2', [passwordHash, req.user.sub]);
    await pool.query(
      `INSERT INTO user_token_revocations (user_id, revoked_after, reason)
       VALUES ($1, now(), 'password_changed')
       ON CONFLICT (user_id) DO UPDATE SET revoked_after = now(), reason = 'password_changed'`,
      [req.user.sub]
    );
    // JWT-iat hat Sekundengenauigkeit: kurz warten, damit das neue Token nach revoked_after liegt
    await new Promise((resolve) => setTimeout(resolve, 1000));
    res.json({ token: signToken(r.rows[0]) });
  } catch (e) {
    serverError(res, e);
  }
});

// 2FA einrichten: neues Secret erzeugen (noch nicht aktiv, erst nach /mfa/enable)
app.post('/api/v1/auth/mfa/setup', auth, async (req, res) => {
  try {
    const r = await pool.query('SELECT email, mfa_enabled FROM users WHERE id = $1', [req.user.sub]);
    if (r.rowCount === 0) return res.status(404).json({ error: 'user_not_found' });
    if (r.rows[0].mfa_enabled) return res.status(409).json({ error: 'mfa_already_enabled' });

    const secret = base32Encode(randomBytes(20));
    await pool.query('UPDATE users SET mfa_secret = $1, updated_at = now() WHERE id = $2', [secret, req.user.sub]);
    const label = encodeURIComponent(`Fuelstat:${r.rows[0].email}`);
    res.json({
      secret,
      otpauthUrl: `otpauth://totp/${label}?secret=${secret}&issuer=Fuelstat&algorithm=SHA1&digits=6&period=30`
    });
  } catch (e) {
    serverError(res, e);
  }
});

// 2FA aktivieren: Code aus der Authenticator-App bestätigen
app.post('/api/v1/auth/mfa/enable', auth, async (req, res) => {
  try {
    const r = await pool.query('SELECT mfa_secret, mfa_enabled FROM users WHERE id = $1', [req.user.sub]);
    if (r.rowCount === 0) return res.status(404).json({ error: 'user_not_found' });
    if (r.rows[0].mfa_enabled) return res.status(409).json({ error: 'mfa_already_enabled' });
    if (!r.rows[0].mfa_secret) return res.status(400).json({ error: 'mfa_not_set_up' });
    const mfaKey = `mfa:${req.user.sub}`;
    if (isLockedOut(mfaKey, MFA_MAX_FAILURES)) return tooManyAttempts(res);
    if (!verifyTotp(r.rows[0].mfa_secret, req.body?.code)) {
      recordFailure(mfaKey);
      return res.status(400).json({ error: 'invalid_mfa_code' });
    }
    clearFailures(mfaKey);

    await pool.query('UPDATE users SET mfa_enabled = true, updated_at = now() WHERE id = $1', [req.user.sub]);
    res.json({ mfa_enabled: true });
  } catch (e) {
    serverError(res, e);
  }
});

// 2FA deaktivieren: Passwort und aktueller Code erforderlich
app.post('/api/v1/auth/mfa/disable', auth, async (req, res) => {
  try {
    const r = await pool.query('SELECT password_hash, mfa_secret, mfa_enabled FROM users WHERE id = $1', [req.user.sub]);
    if (r.rowCount === 0) return res.status(404).json({ error: 'user_not_found' });
    if (!r.rows[0].mfa_enabled) return res.status(400).json({ error: 'mfa_not_enabled' });
    const mfaKey = `mfa:${req.user.sub}`;
    if (isLockedOut(mfaKey, MFA_MAX_FAILURES)) return tooManyAttempts(res);
    const ok = await bcrypt.compare(String(req.body?.password || ''), r.rows[0].password_hash);
    if (!ok || !verifyTotp(r.rows[0].mfa_secret, req.body?.code)) {
      recordFailure(mfaKey);
      return res.status(ok ? 400 : 401).json({ error: ok ? 'invalid_mfa_code' : 'invalid_current_password' });
    }
    clearFailures(mfaKey);

    await pool.query('UPDATE users SET mfa_enabled = false, mfa_secret = NULL, updated_at = now() WHERE id = $1', [req.user.sub]);
    res.json({ mfa_enabled: false });
  } catch (e) {
    serverError(res, e);
  }
});

// List all users (Admin-Endpoint)
app.get('/api/v1/auth/users', auth, requireAdmin, async (req, res) => {
  try {
    const r = await pool.query(
      `SELECT
         id,
         email,
         username,
         mfa_enabled,
         profile_image_url,
         beta_tester,
         created_at,
         updated_at,
         last_login_at,
         last_seen_at,
         (SELECT COUNT(*)::int FROM vehicles v WHERE v.user_id = users.id) AS vehicle_count,
         -- Wann zuletzt etwas eingetragen wurde (Erfassungszeit, nicht das Datum des Eintrags – Importe zählen also auch)
         GREATEST(
           (SELECT MAX(f.created_at) FROM fillups f WHERE f.user_id = users.id),
           (SELECT MAX(e.created_at) FROM expenses e WHERE e.user_id = users.id)
         ) AS last_entry_at
       FROM users
       ORDER BY created_at ASC`
    );
    res.json(r.rows);
  } catch (e) {
    serverError(res, e);
  }
});

// Beta-Tester-Status setzen (nur Admin)
app.put('/api/v1/auth/users/:userId/beta', auth, requireAdmin, async (req, res) => {
  if (typeof req.body?.betaTester !== 'boolean') return res.status(400).json({ error: 'invalid_betaTester' });
  try {
    const r = await pool.query(
      'UPDATE users SET beta_tester = $2, updated_at = now() WHERE id = $1 RETURNING id, email, beta_tester',
      [String(req.params.userId || '').trim(), req.body.betaTester]
    );
    if (r.rowCount === 0) return res.status(404).json({ error: 'user_not_found' });
    res.json(r.rows[0]);
  } catch (e) {
    if (e.code === '22P02') return res.status(404).json({ error: 'user_not_found' });
    serverError(res, e);
  }
});

// Get single user (eigener User oder Admin)
app.get('/api/v1/auth/users/:userId', auth, requireSelfOrAdmin, async (req, res) => {
  const userId = String(req.params.userId || '').trim();
  if (!userId) return res.status(400).json({ error: 'missing_userId' });

  try {
    const r = await pool.query(
      `SELECT
         id,
         email,
         username,
         mfa_enabled,
         profile_image_url,
         created_at,
         updated_at
       FROM users
       WHERE id = $1`,
      [userId]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'user_not_found' });
    }

    res.json(r.rows[0]);
  } catch (e) {
    serverError(res, e);
  }
});

// Patch single user (eigener User oder Admin)
app.patch('/api/v1/auth/users/:userId', auth, requireSelfOrAdmin, async (req, res) => {
  const userId = String(req.params.userId || '').trim();
  if (!userId) return res.status(400).json({ error: 'missing_userId' });

  const email =
    req.body?.email !== undefined
      ? String(req.body.email).trim().toLowerCase()
      : undefined;
  const username = optionalText(req.body?.username);
  const profileImageUrl = optionalText(req.body?.profileImageUrl);

  if (email === undefined && username === undefined && profileImageUrl === undefined) {
    return res.status(400).json({ error: 'nothing_to_update' });
  }

  if (email !== undefined && (!email || !email.includes('@'))) {
    return res.status(400).json({ error: 'invalid_email' });
  }

  try {
    const fields = [];
    const values = [];
    let idx = 1;

    if (email !== undefined) {
      fields.push(`email = $${idx++}`);
      values.push(email);
    }
    if (username !== undefined) {
      fields.push(`username = $${idx++}`);
      values.push(username);
    }
    if (profileImageUrl !== undefined) {
      fields.push(`profile_image_url = $${idx++}`);
      values.push(profileImageUrl);
    }

    fields.push(`updated_at = now()`);
    values.push(userId);

    const r = await pool.query(
      `UPDATE users
       SET ${fields.join(', ')}
       WHERE id = $${idx}
       RETURNING id, email, username, mfa_enabled, profile_image_url, created_at, updated_at`,
      values
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'user_not_found' });
    }

    res.json(r.rows[0]);
  } catch (e) {
    if (e.code === '23505') {
      return res.status(409).json({ error: 'email_already_exists' });
    }
    serverError(res, e);
  }
});

// Delete single user (eigener User oder Admin)
app.delete('/api/v1/auth/users/:userId', auth, requireSelfOrAdmin, async (req, res) => {
  const userId = String(req.params.userId || '').trim();
  if (!userId) return res.status(400).json({ error: 'missing_userId' });

  try {
    // Hochgeladene Bilder (Profil und Fahrzeuge) merken, damit nach dem Löschen keine Dateien liegen bleiben
    const media = await pool.query(
      `SELECT url FROM user_media WHERE user_id = $1
       UNION SELECT url FROM vehicle_media WHERE user_id = $1
       UNION SELECT profile_image_url FROM users WHERE id = $1 AND profile_image_url IS NOT NULL`,
      [userId]
    );

    const r = await pool.query(
      'DELETE FROM users WHERE id = $1 RETURNING id',
      [userId]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'user_not_found' });
    }

    await Promise.all(media.rows.map(({ url }) => {
      const filename = path.basename(String(url || ''));
      return /^[\w.-]+$/.test(filename) ? fs.promises.unlink(path.join(UPLOAD_DIR, filename)).catch(() => {}) : null;
    }));

    return res.status(204).send();
  } catch (e) {
    serverError(res, e);
  }
});

// Revoke all tokens of a user (eigener User oder Admin)
app.post('/api/v1/auth/users/:userId/revoke-all', auth, requireSelfOrAdmin, async (req, res) => {
  const userId = String(req.params.userId || '').trim();
  if (!userId) return res.status(400).json({ error: 'missing_userId' });

  const reason =
    req.body?.reason !== undefined
      ? String(req.body.reason).trim()
      : null;

  try {
    // Sicherstellen, dass der User existiert
    const exists = await pool.query(
      'SELECT 1 FROM users WHERE id = $1',
      [userId]
    );
    if (exists.rowCount === 0) {
      return res.status(404).json({ error: 'user_not_found' });
    }

    const r = await pool.query(
      `INSERT INTO user_token_revocations (user_id, revoked_after, reason)
       VALUES ($1, now(), $2)
       ON CONFLICT (user_id) DO UPDATE
         SET revoked_after = now(),
             reason = COALESCE(EXCLUDED.reason, user_token_revocations.reason)
       RETURNING user_id, revoked_after, reason`,
      [userId, reason]
    );

    res.status(201).json({
      userId: r.rows[0].user_id,
      revokedAfter: r.rows[0].revoked_after,
      reason: r.rows[0].reason,
      message:
        'Alle bestehenden Tokens dieses Benutzers wurden widerrufen. Neue Logins erhalten wieder gültige Tokens.'
    });
  } catch (e) {
    serverError(res, e);
  }
});

// List revoked tokens (Admins: alle bzw. ?userId=, sonst nur die eigenen)
app.get('/api/v1/auth/tokens', auth, async (req, res) => {
  const userId = !isAdmin(req)
    ? String(req.user.sub)
    : req.query.userId !== undefined
      ? String(req.query.userId).trim()
      : null;

  try {
    let r;
    if (userId) {
      r = await pool.query(
        `SELECT jti, user_id, revoked_at, reason
         FROM revoked_tokens
         WHERE user_id = $1
         ORDER BY revoked_at DESC`,
        [userId]
      );
    } else {
      r = await pool.query(
        `SELECT jti, user_id, revoked_at, reason
         FROM revoked_tokens
         ORDER BY revoked_at DESC`
      );
    }

    res.json(r.rows);
  } catch (e) {
    serverError(res, e);
  }
});

// Revoke a token (eigene Tokens oder Admin)
app.post('/api/v1/auth/tokens/revoke', auth, async (req, res) => {
  const token = String(req.body?.token || '').trim();
  const reason =
    req.body?.reason !== undefined
      ? String(req.body.reason).trim()
      : null;

  if (!token) {
    return res.status(400).json({ error: 'missing_token' });
  }

  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);

    if (!payload.jti) {
      return res.status(400).json({
        error: 'cannot_revoke_legacy_token',
        message:
          'Dieses Token besitzt keine jti und kann daher serverseitig nicht widerrufen werden. Bitte den Benutzer neu einloggen lassen.'
      });
    }

    const jti = String(payload.jti);
    const userId = String(payload.sub);

    if (userId !== String(req.user.sub) && !isAdmin(req)) {
      return res.status(403).json({ error: 'forbidden' });
    }

    const r = await pool.query(
      `INSERT INTO revoked_tokens (jti, user_id, reason)
       VALUES ($1, $2, $3)
       ON CONFLICT (jti) DO UPDATE
         SET reason = COALESCE(EXCLUDED.reason, revoked_tokens.reason)`,
      [jti, userId, reason]
    );

    res.status(201).json({
      jti,
      userId,
      reason,
      message: 'Token wurde widerrufen und wird ab sofort abgelehnt.'
    });
  } catch (e) {
    if (e.name === 'JsonWebTokenError' || e.name === 'TokenExpiredError') {
      return res.status(400).json({ error: 'invalid_token', detail: e.message });
    }
    serverError(res, e);
  }
});

// Create vehicle
app.post('/api/v1/vehicles', auth, async (req, res) => {
  const name = String(req.body?.name || '').trim();
  const fuelType = String(req.body?.fuelType || '').trim().toLowerCase();

  const allowed = new Set(['diesel', 'e5', 'e10', 'lpg', 'cng', 'electric', 'hybrid', 'hydrogen', 'other']);
  if (!name) return res.status(400).json({ error: 'missing_name' });
  if (!allowed.has(fuelType)) return res.status(400).json({ error: 'invalid_fuelType' });

  const brand = req.body?.brand ? String(req.body.brand).trim() : null;
  const model = req.body?.model ? String(req.body.model).trim() : null;
  const year = optionalNumber(req.body?.year) ?? null;
  const imageUrl = req.body?.imageUrl ? String(req.body.imageUrl).trim() : null;
  const odometerKm = optionalNumber(req.body?.odometerKm) ?? null;
  const mileageKm = optionalNumber(req.body?.mileageKm) ?? null;
  const tankCapacity = optionalNumber(req.body?.tankCapacity) ?? null;
  const sortOrder = req.body?.sortOrder !== undefined ? Number(req.body.sortOrder) : 0;

  if (year !== null && (!Number.isFinite(year) || year < 1886 || year > 2100)) {
    return res.status(400).json({ error: 'invalid_year' });
  }
  if (odometerKm !== null && (!Number.isFinite(odometerKm) || odometerKm < 0)) {
    return res.status(400).json({ error: 'invalid_odometerKm' });
  }
  if (mileageKm !== null && (!Number.isFinite(mileageKm) || mileageKm < 0)) {
    return res.status(400).json({ error: 'invalid_mileageKm' });
  }
  if (tankCapacity !== null && (!Number.isFinite(tankCapacity) || tankCapacity <= 0)) {
    return res.status(400).json({ error: 'invalid_tankCapacity' });
  }
  if (!Number.isFinite(sortOrder)) {
    return res.status(400).json({ error: 'invalid_sortOrder' });
  }

  try {
    const r = await pool.query(
      `INSERT INTO vehicles (
         user_id, name, fuel_type, brand, model, year, image_url,
         odometer_km, mileage_km, tank_capacity, sort_order
       )
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
       RETURNING id, name, fuel_type, brand, model, year, image_url,
                 odometer_km, mileage_km, tank_capacity, sort_order,
                 created_at, updated_at`,
      [req.user.sub, name, fuelType, brand, model, year, imageUrl, odometerKm, mileageKm, tankCapacity, sortOrder]
    );
    res.status(201).json(r.rows[0]);
  } catch (e) {
    serverError(res, e);
  }
});

// List vehicles
app.get('/api/v1/vehicles', auth, async (req, res) => {
  try {
    const r = await pool.query(
      `SELECT id, name, fuel_type, brand, model, year, image_url,
              odometer_km, mileage_km, tank_capacity, sort_order,
              created_at, updated_at
       FROM vehicles
       WHERE user_id = $1
       ORDER BY sort_order ASC, created_at DESC`,
      [req.user.sub]
    );
    res.json(r.rows);
  } catch (e) {
    serverError(res, e);
  }
});

// Get single vehicle with details
app.get('/api/v1/vehicles/:vehicleId', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();

  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });

  try {
    const r = await pool.query(
      `SELECT
         id,
         name,
         fuel_type,
         brand,
         model,
         year,
         image_url,
         odometer_km,
         mileage_km,
         tank_capacity,
         sort_order,
         created_at,
         updated_at
       FROM vehicles
       WHERE id = $1 AND user_id = $2`,
      [vehicleId, req.user.sub]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'vehicle_not_found' });
    }

    res.json(r.rows[0]);
  } catch (e) {
    serverError(res, e);
  }
});

// Update vehicle (partial)
app.patch('/api/v1/vehicles/:vehicleId', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });

  let name =
    req.body?.name !== undefined ? String(req.body.name).trim() : undefined;
  let fuelType =
    req.body?.fuelType !== undefined
      ? String(req.body.fuelType).trim().toLowerCase()
      : undefined;
  const brand = optionalText(req.body?.brand);
  const model = optionalText(req.body?.model);
  const year = optionalNumber(req.body?.year);
  const imageUrl = optionalText(req.body?.imageUrl);
  const odometerKm = optionalNumber(req.body?.odometerKm);
  const mileageKm = optionalNumber(req.body?.mileageKm);
  const tankCapacity = optionalNumber(req.body?.tankCapacity);
  const sortOrder =
    req.body?.sortOrder !== undefined ? Number(req.body.sortOrder) : undefined;

  const allowedFuelTypes = new Set([
    'diesel',
    'e5',
    'e10',
    'lpg',
    'cng',
    'electric',
    'hybrid',
    'hydrogen',
    'other'
  ]);

  if (name !== undefined && !name) {
    return res.status(400).json({ error: 'invalid_name' });
  }
  if (fuelType !== undefined && !allowedFuelTypes.has(fuelType)) {
    return res.status(400).json({ error: 'invalid_fuelType' });
  }
  if (year !== undefined && year !== null && (!Number.isFinite(year) || year < 1886 || year > 2100)) {
    return res.status(400).json({ error: 'invalid_year' });
  }
  if (odometerKm !== undefined && odometerKm !== null && (!Number.isFinite(odometerKm) || odometerKm < 0)) {
    return res.status(400).json({ error: 'invalid_odometerKm' });
  }
  if (mileageKm !== undefined && mileageKm !== null && (!Number.isFinite(mileageKm) || mileageKm < 0)) {
    return res.status(400).json({ error: 'invalid_mileageKm' });
  }
  if (tankCapacity !== undefined && tankCapacity !== null && (!Number.isFinite(tankCapacity) || tankCapacity <= 0)) {
    return res.status(400).json({ error: 'invalid_tankCapacity' });
  }
  if (sortOrder !== undefined && !Number.isFinite(sortOrder)) {
    return res.status(400).json({ error: 'invalid_sortOrder' });
  }

  if (
    name === undefined &&
    fuelType === undefined &&
    brand === undefined &&
    model === undefined &&
    year === undefined &&
    imageUrl === undefined &&
    odometerKm === undefined &&
    mileageKm === undefined &&
    tankCapacity === undefined &&
    sortOrder === undefined
  ) {
    return res.status(400).json({ error: 'nothing_to_update' });
  }

  try {
    // Sicherstellen, dass das Fahrzeug dem User gehört
    const existing = await pool.query(
      'SELECT id FROM vehicles WHERE id = $1 AND user_id = $2',
      [vehicleId, req.user.sub]
    );
    if (existing.rowCount === 0) {
      return res.status(404).json({ error: 'vehicle_not_found' });
    }

    // Dynamisches Update je nach gesendeten Feldern
    const fields = [];
    const values = [];
    let idx = 1;

    if (name !== undefined) {
      fields.push(`name = $${idx++}`);
      values.push(name);
    }
    if (fuelType !== undefined) {
      fields.push(`fuel_type = $${idx++}`);
      values.push(fuelType);
    }
    if (brand !== undefined) {
      fields.push(`brand = $${idx++}`);
      values.push(brand);
    }
    if (model !== undefined) {
      fields.push(`model = $${idx++}`);
      values.push(model);
    }
    if (year !== undefined) {
      fields.push(`year = $${idx++}`);
      values.push(year);
    }
    if (imageUrl !== undefined) {
      fields.push(`image_url = $${idx++}`);
      values.push(imageUrl);
    }
    if (odometerKm !== undefined) {
      fields.push(`odometer_km = $${idx++}`);
      values.push(odometerKm);
    }
    if (mileageKm !== undefined) {
      fields.push(`mileage_km = $${idx++}`);
      values.push(mileageKm);
    }
    if (tankCapacity !== undefined) {
      fields.push(`tank_capacity = $${idx++}`);
      values.push(tankCapacity);
    }
    if (sortOrder !== undefined) {
      fields.push(`sort_order = $${idx++}`);
      values.push(sortOrder);
    }

    fields.push(`updated_at = now()`);
    values.push(vehicleId);
    values.push(req.user.sub);

    const r = await pool.query(
      `UPDATE vehicles
       SET ${fields.join(', ')}
       WHERE id = $${idx++} AND user_id = $${idx}
       RETURNING id, name, fuel_type, brand, model, year, image_url,
                 odometer_km, mileage_km, tank_capacity, sort_order,
                 created_at, updated_at`,
      values
    );

    res.json(r.rows[0]);
  } catch (e) {
    serverError(res, e);
  }
});

// Delete vehicle
app.delete('/api/v1/vehicles/:vehicleId', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });

  try {
    const r = await pool.query(
      'DELETE FROM vehicles WHERE id = $1 AND user_id = $2 RETURNING id',
      [vehicleId, req.user.sub]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'vehicle_not_found' });
    }

    // 204 No Content, keine Response-Payload nötig
    return res.status(204).send();
  } catch (e) {
    serverError(res, e);
  }
});

// Add fillup (generalisiert: amount + unit)
app.post('/api/v1/fillups', auth, async (req, res) => {
  const vehicleId = String(req.body?.vehicleId || '').trim();
  const odometerKm = Number(req.body?.odometerKm);

  // Backward-compatible: akzeptiere liters ODER amount
  const amount = Number(req.body?.amount ?? req.body?.liters);
  const totalCostEur = Number(req.body?.totalCostEur ?? req.body?.priceTotalEur);

  const station = req.body?.station ? String(req.body.station).trim() : null;
  const filledAt = req.body?.filledAt ? new Date(req.body.filledAt) : null;
  const isFull = req.body?.isFull !== undefined ? Boolean(req.body.isFull) : true;
  const skipPrevious = req.body?.skipPrevious !== undefined ? Boolean(req.body.skipPrevious) : false;

  // unit optional: wenn nicht gesetzt, wird es aus vehicle.fuel_type abgeleitet
  let unit = req.body?.unit ? String(req.body.unit).trim().toLowerCase() : null;

  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });
  if (!Number.isFinite(odometerKm) || odometerKm < 0) return res.status(400).json({ error: 'invalid_odometerKm' });
  if (!Number.isFinite(amount) || amount < 0) return res.status(400).json({ error: 'invalid_amount' });
  if (!Number.isFinite(totalCostEur) || totalCostEur < 0) return res.status(400).json({ error: 'invalid_totalCostEur' });
  if (filledAt && Number.isNaN(filledAt.getTime())) return res.status(400).json({ error: 'invalid_filledAt' });

  const allowedUnits = new Set(['l', 'kwh', 'kg']);

  try {
    // ownership + fuel_type
    const v = await pool.query(
      'SELECT fuel_type FROM vehicles WHERE id = $1 AND user_id = $2',
      [vehicleId, req.user.sub]
    );
    if (v.rowCount === 0) return res.status(404).json({ error: 'vehicle_not_found' });

    const fuelType = String(v.rows[0].fuel_type || '').toLowerCase();

    if (!unit) {
      if (fuelType === 'electric') unit = 'kwh';
      else if (fuelType === 'cng') unit = 'kg';
      else if (fuelType === 'hydrogen') unit = 'kg';
      else unit = 'l'; // diesel, e5, e10, hybrid, other
    }
    if (!allowedUnits.has(unit)) return res.status(400).json({ error: 'invalid_unit' });

    const r = await pool.query(
      `INSERT INTO fillups (
         user_id,
         vehicle_id,
         odometer_km,
         amount,
         unit,
         total_cost_eur,
         price_per_unit,
         station,
         filled_at,
         is_full,
         skip_previous
       )
       VALUES (
         $1,
         $2,
         $3,
         $4::numeric,
         $5,
         $6::numeric,
         CASE
           WHEN $4::numeric > 0
             THEN ROUND(($6::numeric / $4::numeric), 4)
           ELSE NULL
         END,
         $7,
         COALESCE($8, now()),
         $9,
         $10
       )
       RETURNING id, vehicle_id, odometer_km, amount, unit, total_cost_eur, price_per_unit, station, filled_at, created_at, is_full, skip_previous`,
      [req.user.sub, vehicleId, odometerKm, amount, unit, totalCostEur, station, filledAt, isFull, skipPrevious]
    );

    res.status(201).json(r.rows[0]);
  } catch (e) {
    serverError(res, e);
  }
});

// Get single fillup by id
app.get('/api/v1/fillups/:fillupId', auth, async (req, res) => {
  const fillupId = String(req.params.fillupId || '').trim();

  if (!fillupId) return res.status(400).json({ error: 'missing_fillupId' });

  try {
    const r = await pool.query(
      `SELECT
         id,
         user_id,
         vehicle_id,
         odometer_km,
         amount,
         unit,
         total_cost_eur,
         price_per_unit,
         station,
         filled_at,
         created_at,
         is_full,
         skip_previous
       FROM fillups
       WHERE id = $1 AND user_id = $2`,
      [fillupId, req.user.sub]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'fillup_not_found' });
    }

    const row = r.rows[0];

    // Für ältere Clients zusätzlich kompatible Felder anbieten
    const legacy = {
      liters: row.unit === 'l' ? row.amount : null,
      price_total_eur: row.total_cost_eur,
      price_per_liter: row.unit === 'l' ? row.price_per_unit : null
    };

    res.json({ ...row, ...legacy });
  } catch (e) {
    serverError(res, e);
  }
});

// Update fillup (partial, 14-Tage-Bearbeitungsfenster ab filled_at)
app.patch('/api/v1/fillups/:fillupId', auth, async (req, res) => {
  const fillupId = String(req.params.fillupId || '').trim();

  if (!fillupId) return res.status(400).json({ error: 'missing_fillupId' });

  try {
    // Bestehenden Datensatz laden + Besitz prüfen
    const existingRes = await pool.query(
      `SELECT
         id,
         user_id,
         vehicle_id,
         odometer_km,
         amount,
         unit,
         total_cost_eur,
         price_per_unit,
         station,
         filled_at,
         created_at,
         is_full,
         skip_previous
       FROM fillups
       WHERE id = $1 AND user_id = $2`,
      [fillupId, req.user.sub]
    );

    if (existingRes.rowCount === 0) {
      return res.status(404).json({ error: 'fillup_not_found' });
    }

    const existing = existingRes.rows[0];

    // 14 Tage Änderungsfenster ab filled_at
    const now = new Date();
    const filledAtOriginal = new Date(existing.filled_at);
    const diffMs = now.getTime() - filledAtOriginal.getTime();
    const maxMs = 14 * 24 * 60 * 60 * 1000; // 14 Tage

    if (diffMs > maxMs) {
      return res.status(409).json({
        error: 'fillup_edit_window_expired',
        message:
          'Tank-/Ladevorgänge können nur innerhalb von 14 Tagen nach dem Tank-/Ladedatum bearbeitet werden.'
      });
    }

    // Eingehende Änderungen parsen (teilweise, optional)
    const body = req.body || {};

    const patchOdometerKm =
      body.odometerKm !== undefined ? Number(body.odometerKm) : undefined;

    // Backward-kompatibel: amount oder liters
    const rawAmount =
      body.amount !== undefined ? body.amount : body.liters !== undefined ? body.liters : undefined;
    const patchAmount =
      rawAmount !== undefined ? Number(rawAmount) : undefined;

    // Backward-kompatibel: totalCostEur oder priceTotalEur
    const rawTotal =
      body.totalCostEur !== undefined
        ? body.totalCostEur
        : body.priceTotalEur !== undefined
        ? body.priceTotalEur
        : undefined;
    const patchTotalCostEur =
      rawTotal !== undefined ? Number(rawTotal) : undefined;

    const patchUnit =
      body.unit !== undefined ? String(body.unit).trim().toLowerCase() : undefined;

    const patchStation =
      body.station !== undefined ? String(body.station).trim() : undefined;

    const patchFilledAt =
      body.filledAt !== undefined ? new Date(body.filledAt) : undefined;

    const patchIsFull =
      body.isFull !== undefined ? Boolean(body.isFull) : undefined;

    const patchSkipPrevious =
      body.skipPrevious !== undefined ? Boolean(body.skipPrevious) : undefined;

    // Wenn nichts Sinnvolles geändert werden soll
    if (
      patchOdometerKm === undefined &&
      patchAmount === undefined &&
      patchTotalCostEur === undefined &&
      patchUnit === undefined &&
      patchStation === undefined &&
      patchFilledAt === undefined &&
      patchIsFull === undefined &&
      patchSkipPrevious === undefined
    ) {
      return res.status(400).json({ error: 'nothing_to_update' });
    }

    // Zielwerte (bestehend oder gepatcht)
    const newOdometerKm =
      patchOdometerKm !== undefined ? patchOdometerKm : existing.odometer_km;
    const newAmount =
      patchAmount !== undefined ? patchAmount : existing.amount;
    const newTotalCostEur =
      patchTotalCostEur !== undefined ? patchTotalCostEur : existing.total_cost_eur;
    const newUnit =
      patchUnit !== undefined ? patchUnit : existing.unit;
    const newStation =
      patchStation !== undefined ? patchStation : existing.station;
    const newFilledAt =
      patchFilledAt !== undefined ? patchFilledAt : new Date(existing.filled_at);
    const newIsFull =
      patchIsFull !== undefined ? patchIsFull : existing.is_full;
    const newSkipPrevious =
      patchSkipPrevious !== undefined ? patchSkipPrevious : existing.skip_previous;

    // Validierungen auf Zielwerten
    if (!Number.isFinite(newOdometerKm) || newOdometerKm < 0) {
      return res.status(400).json({ error: 'invalid_odometerKm' });
    }
    if (!Number.isFinite(newAmount) || newAmount < 0) {
      return res.status(400).json({ error: 'invalid_amount' });
    }
    if (!Number.isFinite(newTotalCostEur) || newTotalCostEur < 0) {
      return res.status(400).json({ error: 'invalid_totalCostEur' });
    }
    if (Number.isNaN(newFilledAt.getTime())) {
      return res.status(400).json({ error: 'invalid_filledAt' });
    }

    const allowedUnits = new Set(['l', 'kwh', 'kg']);
    if (!newUnit || !allowedUnits.has(newUnit)) {
      return res.status(400).json({ error: 'invalid_unit' });
    }

    const updateRes = await pool.query(
      `UPDATE fillups
       SET
         odometer_km   = $1,
         amount        = $2::numeric,
         unit          = $3,
         total_cost_eur = $4::numeric,
         price_per_unit = CASE
           WHEN $2::numeric > 0
             THEN ROUND(($4::numeric / $2::numeric), 4)
           ELSE NULL
         END,
         station       = $5,
         filled_at     = $6,
         is_full       = $7,
         skip_previous = $8
       WHERE id = $9 AND user_id = $10
       RETURNING
         id,
         user_id,
         vehicle_id,
         odometer_km,
         amount,
         unit,
         total_cost_eur,
         price_per_unit,
         station,
         filled_at,
         created_at,
         is_full,
         skip_previous`,
      [
        newOdometerKm,
        newAmount,
        newUnit,
        newTotalCostEur,
        newStation,
        newFilledAt,
        newIsFull,
        newSkipPrevious,
        fillupId,
        req.user.sub
      ]
    );

    const updated = updateRes.rows[0];

    const legacy = {
      liters: updated.unit === 'l' ? updated.amount : null,
      price_total_eur: updated.total_cost_eur,
      price_per_liter: updated.unit === 'l' ? updated.price_per_unit : null
    };

    res.json({ ...updated, ...legacy });
  } catch (e) {
    serverError(res, e);
  }
});

// Delete fillup (immer möglich; bei >14 Tagen wird nächster Fillup auf skip_previous=true gesetzt)
app.delete('/api/v1/fillups/:fillupId', auth, async (req, res) => {
  const fillupId = String(req.params.fillupId || '').trim();
  if (!fillupId) return res.status(400).json({ error: 'missing_fillupId' });

  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    // Zu löschenden Fillup laden + Besitz prüfen
    const existingRes = await client.query(
      `SELECT
         id,
         user_id,
         vehicle_id,
         odometer_km,
         filled_at
       FROM fillups
       WHERE id = $1 AND user_id = $2
       FOR UPDATE`,
      [fillupId, req.user.sub]
    );

    if (existingRes.rowCount === 0) {
      await client.query('ROLLBACK');
      return res.status(404).json({ error: 'fillup_not_found' });
    }

    const existing = existingRes.rows[0];
    const filledAt = new Date(existing.filled_at);
    const now = new Date();
    const diffMs = now.getTime() - filledAt.getTime();
    const maxMs = 14 * 24 * 60 * 60 * 1000; // 14 Tage
    const olderThan14Days = diffMs > maxMs;

    // Fillup löschen
    await client.query(
      'DELETE FROM fillups WHERE id = $1 AND user_id = $2',
      [fillupId, req.user.sub]
    );

    let nextUpdated = false;

    if (olderThan14Days) {
      // Nächsten Fillup anhand filled_at finden und skip_previous = true setzen
      const nextRes = await client.query(
        `SELECT id
         FROM fillups
         WHERE user_id = $1
           AND vehicle_id = $2
           AND filled_at > $3
         ORDER BY filled_at ASC
         LIMIT 1`,
        [req.user.sub, existing.vehicle_id, filledAt]
      );

      if (nextRes.rowCount === 1) {
        const nextId = nextRes.rows[0].id;
        await client.query(
          `UPDATE fillups
           SET skip_previous = true
           WHERE id = $1 AND user_id = $2`,
          [nextId, req.user.sub]
        );
        nextUpdated = true;
      }
    }

    await client.query('COMMIT');

    return res.status(200).json({
      deletedId: existing.id,
      olderThan14Days,
      nextUpdated,
      message: olderThan14Days
        ? 'Ein Tank-/Ladevorgang, der älter als 14 Tage ist, wurde gelöscht. Der nachfolgende Vorgang wird bei der Verbrauchsberechnung nicht mehr den vorherigen berücksichtigen (skip_previous = true).'
        : 'Tank-/Ladevorgang wurde gelöscht.'
    });
  } catch (e) {
    await client.query('ROLLBACK');
    serverError(res, e);
  } finally {
    client.release();
  }
});

// List fillups by vehicle
app.get('/api/v1/vehicles/:vehicleId/fillups', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  const limit = Math.min(200, Math.max(1, Number(req.query.limit || 50)));
  const offset = Math.max(0, Math.floor(Number(req.query.offset || 0)) || 0);

  try {
    const v = await pool.query('SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2', [vehicleId, req.user.sub]);
    if (v.rowCount === 0) return res.status(404).json({ error: 'vehicle_not_found' });

    const r = await pool.query(
      `SELECT
         id,
         odometer_km,
         amount,
         unit,
         total_cost_eur,
         price_per_unit,
         station,
         filled_at,
         created_at,
         is_full,
         skip_previous
       FROM fillups
       WHERE user_id = $1 AND vehicle_id = $2
       ORDER BY filled_at DESC, created_at DESC
       LIMIT $3 OFFSET $4`,
      [req.user.sub, vehicleId, limit, offset]
    );

    // Für ältere Clients zusätzlich kompatible Felder anbieten
    res.json(r.rows.map((row) => ({
      ...row,
      liters: row.unit === 'l' ? row.amount : null,
      price_total_eur: row.total_cost_eur,
      price_per_liter: row.unit === 'l' ? row.price_per_unit : null
    })));
  } catch (e) {
    serverError(res, e);
  }
});

// Create non-fuel expense entry
app.post('/api/v1/entries', auth, async (req, res) => {
  const vehicleId = String(req.body?.vehicleId || '').trim();
  const category = String(req.body?.category || '').trim();

  const amountEur = Number(req.body?.amountEur);
  const odometerKm = req.body?.odometerKm !== undefined ? Number(req.body.odometerKm) : null;
  const occurredAt = req.body?.occurredAt ? new Date(req.body.occurredAt) : null;

  const vendor = req.body?.vendor ? String(req.body.vendor).trim() : null;
  const description = req.body?.description ? String(req.body.description).trim() : null;

  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });
  if (!category) return res.status(400).json({ error: 'missing_category' });
  if (!Number.isFinite(amountEur) || amountEur < 0) {
    return res.status(400).json({ error: 'invalid_amountEur' });
  }
  if (odometerKm !== null && (!Number.isFinite(odometerKm) || odometerKm < 0)) {
    return res.status(400).json({ error: 'invalid_odometerKm' });
  }
  if (occurredAt && Number.isNaN(occurredAt.getTime())) {
    return res.status(400).json({ error: 'invalid_occurredAt' });
  }

  try {
    // Prüfen, ob das Fahrzeug zum User gehört
    const v = await pool.query(
      'SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2',
      [vehicleId, req.user.sub]
    );
    if (v.rowCount === 0) return res.status(404).json({ error: 'vehicle_not_found' });

    const r = await pool.query(
      `INSERT INTO expenses (
         user_id,
         vehicle_id,
         category,
         amount_eur,
         odometer_km,
         occurred_at,
         vendor,
         description
       )
       VALUES (
         $1,
         $2,
         $3,
         $4::numeric,
         $5,
         COALESCE($6, now()),
         $7,
         $8
       )
       RETURNING
         id,
         user_id,
         vehicle_id,
         category,
         amount_eur,
         odometer_km,
         occurred_at,
         vendor,
         description,
         created_at`,
      [req.user.sub, vehicleId, category, amountEur, odometerKm, occurredAt, vendor, description]
    );

    res.status(201).json(r.rows[0]);
  } catch (e) {
    serverError(res, e);
  }
});

// List expense entries by vehicle
const ENTRY_COLUMNS = `
  id,
  user_id,
  vehicle_id,
  category,
  amount_eur,
  odometer_km,
  occurred_at,
  vendor,
  description,
  created_at`;

// Get single entry (expense)
app.get('/api/v1/entries/:entryId', auth, async (req, res) => {
  const entryId = String(req.params.entryId || '').trim();
  try {
    const r = await pool.query(
      `SELECT ${ENTRY_COLUMNS} FROM expenses WHERE id = $1 AND user_id = $2`,
      [entryId, req.user.sub]
    );
    if (r.rowCount === 0) return res.status(404).json({ error: 'entry_not_found' });
    res.json(r.rows[0]);
  } catch (e) {
    if (e.code === '22P02') return res.status(404).json({ error: 'entry_not_found' });
    serverError(res, e);
  }
});

// Patch single entry (expense)
app.patch('/api/v1/entries/:entryId', auth, async (req, res) => {
  const entryId = String(req.params.entryId || '').trim();
  const body = req.body || {};
  const fields = [];
  const values = [];
  let idx = 1;

  if (body.category !== undefined) {
    const category = String(body.category).trim();
    if (!category) return res.status(400).json({ error: 'missing_category' });
    fields.push(`category = $${idx++}`);
    values.push(category);
  }
  if (body.amountEur !== undefined) {
    const amountEur = Number(body.amountEur);
    if (!Number.isFinite(amountEur) || amountEur < 0) return res.status(400).json({ error: 'invalid_amountEur' });
    fields.push(`amount_eur = $${idx++}`);
    values.push(amountEur);
  }
  if (body.odometerKm !== undefined) {
    const odometerKm = body.odometerKm === null || body.odometerKm === '' ? null : Number(body.odometerKm);
    if (odometerKm !== null && (!Number.isFinite(odometerKm) || odometerKm < 0)) {
      return res.status(400).json({ error: 'invalid_odometerKm' });
    }
    fields.push(`odometer_km = $${idx++}`);
    values.push(odometerKm);
  }
  if (body.occurredAt !== undefined) {
    const occurredAt = new Date(body.occurredAt);
    if (Number.isNaN(occurredAt.getTime())) return res.status(400).json({ error: 'invalid_occurredAt' });
    fields.push(`occurred_at = $${idx++}`);
    values.push(occurredAt);
  }
  if (body.vendor !== undefined) {
    fields.push(`vendor = $${idx++}`);
    values.push(body.vendor === null ? null : String(body.vendor).trim() || null);
  }
  if (body.description !== undefined) {
    fields.push(`description = $${idx++}`);
    values.push(body.description === null ? null : String(body.description).trim() || null);
  }

  if (fields.length === 0) return res.status(400).json({ error: 'nothing_to_update' });

  try {
    values.push(entryId, req.user.sub);
    const r = await pool.query(
      `UPDATE expenses SET ${fields.join(', ')}
       WHERE id = $${idx++} AND user_id = $${idx}
       RETURNING ${ENTRY_COLUMNS}`,
      values
    );
    if (r.rowCount === 0) return res.status(404).json({ error: 'entry_not_found' });
    res.json(r.rows[0]);
  } catch (e) {
    if (e.code === '22P02') return res.status(404).json({ error: 'entry_not_found' });
    serverError(res, e);
  }
});

// Delete single entry (expense)
app.delete('/api/v1/entries/:entryId', auth, async (req, res) => {
  const entryId = String(req.params.entryId || '').trim();
  try {
    const r = await pool.query(
      'DELETE FROM expenses WHERE id = $1 AND user_id = $2 RETURNING id',
      [entryId, req.user.sub]
    );
    if (r.rowCount === 0) return res.status(404).json({ error: 'entry_not_found' });
    res.status(204).send();
  } catch (e) {
    if (e.code === '22P02') return res.status(404).json({ error: 'entry_not_found' });
    serverError(res, e);
  }
});

app.get('/api/v1/vehicles/:vehicleId/entries', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  const limit = Math.min(200, Math.max(1, Number(req.query.limit || 50)));
  const offset = Math.max(0, Math.floor(Number(req.query.offset || 0)) || 0);

  try {
    // Fahrzeug-Besitz prüfen
    const v = await pool.query(
      'SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2',
      [vehicleId, req.user.sub]
    );
    if (v.rowCount === 0) return res.status(404).json({ error: 'vehicle_not_found' });

    const r = await pool.query(
      `SELECT
         id,
         user_id,
         vehicle_id,
         category,
         amount_eur,
         odometer_km,
         occurred_at,
         vendor,
         description,
         created_at
       FROM expenses
       WHERE user_id = $1 AND vehicle_id = $2
       ORDER BY occurred_at DESC, created_at DESC
       LIMIT $3 OFFSET $4`,
      [req.user.sub, vehicleId, limit, offset]
    );

    res.json(r.rows);
  } catch (e) {
    serverError(res, e);
  }
});

// Statistics of Vehicle
// Mengeneinheit aus dem Kraftstoff des Fahrzeugs
function unitForFuelType(fuelType) {
  const f = String(fuelType || '').toLowerCase();
  if (f === 'electric') return 'kwh';
  if (f === 'cng' || f === 'hydrogen') return 'kg';
  return 'l';
}

// Massen-Import (z. B. aus CSV): alle Zeilen in einer Transaktion, Duplikate werden übersprungen
app.post('/api/v1/vehicles/:vehicleId/import', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  const fillups = Array.isArray(req.body?.fillups) ? req.body.fillups : [];
  const entries = Array.isArray(req.body?.entries) ? req.body.entries : [];

  if (fillups.length + entries.length === 0) return res.status(400).json({ error: 'nothing_to_import' });
  if (fillups.length + entries.length > 1000) return res.status(400).json({ error: 'too_many_rows', max: 1000 });

  // Validierung vorab: bei Fehlern wird nichts importiert
  const errors = [];
  const validDate = (v) => v && !Number.isNaN(new Date(v).getTime());
  const nonNegative = (v) => Number.isFinite(Number(v)) && Number(v) >= 0;
  fillups.forEach((f, index) => {
    if (!validDate(f?.filledAt)) errors.push({ type: 'fillup', index, error: 'invalid_filledAt' });
    else if (!nonNegative(f?.odometerKm)) errors.push({ type: 'fillup', index, error: 'invalid_odometerKm' });
    else if (!nonNegative(f?.amount)) errors.push({ type: 'fillup', index, error: 'invalid_amount' });
    else if (!nonNegative(f?.totalCostEur)) errors.push({ type: 'fillup', index, error: 'invalid_totalCostEur' });
  });
  entries.forEach((e, index) => {
    if (!validDate(e?.occurredAt)) errors.push({ type: 'entry', index, error: 'invalid_occurredAt' });
    else if (!String(e?.category || '').trim()) errors.push({ type: 'entry', index, error: 'missing_category' });
    else if (!nonNegative(e?.amountEur)) errors.push({ type: 'entry', index, error: 'invalid_amountEur' });
    else if (e?.odometerKm !== undefined && e?.odometerKm !== null && e?.odometerKm !== '' && !nonNegative(e.odometerKm)) {
      errors.push({ type: 'entry', index, error: 'invalid_odometerKm' });
    }
  });
  if (errors.length) return res.status(400).json({ error: 'invalid_rows', rows: errors.slice(0, 50) });

  const client = await pool.connect();
  try {
    const v = await client.query('SELECT fuel_type FROM vehicles WHERE id = $1 AND user_id = $2', [vehicleId, req.user.sub]);
    if (v.rowCount === 0) return res.status(404).json({ error: 'vehicle_not_found' });
    const unit = unitForFuelType(v.rows[0].fuel_type);

    await client.query('BEGIN');
    const result = { imported: { fillups: 0, entries: 0 }, skipped: { fillups: 0, entries: 0 } };

    for (const f of fillups) {
      const filledAt = new Date(f.filledAt);
      const dup = await client.query(
        `SELECT 1 FROM fillups
         WHERE vehicle_id = $1 AND user_id = $2 AND odometer_km = $3 AND filled_at::date = $4::date`,
        [vehicleId, req.user.sub, Math.round(Number(f.odometerKm)), filledAt]
      );
      if (dup.rowCount > 0) { result.skipped.fillups++; continue; }
      await client.query(
        `INSERT INTO fillups (user_id, vehicle_id, odometer_km, amount, unit, total_cost_eur, price_per_unit, station, filled_at, is_full, skip_previous)
         VALUES ($1, $2, $3, $4::numeric, $5, $6::numeric,
                 CASE WHEN $4::numeric > 0 THEN ROUND($6::numeric / $4::numeric, 4) ELSE NULL END,
                 $7, $8, $9, $10)`,
        [
          req.user.sub, vehicleId, Math.round(Number(f.odometerKm)), Number(f.amount), unit, Number(f.totalCostEur),
          f.station ? String(f.station).trim() : null, filledAt,
          f.isFull !== undefined ? Boolean(f.isFull) : true,
          f.skipPrevious !== undefined ? Boolean(f.skipPrevious) : false
        ]
      );
      result.imported.fillups++;
    }

    for (const e of entries) {
      const occurredAt = new Date(e.occurredAt);
      const category = String(e.category).trim();
      const dup = await client.query(
        `SELECT 1 FROM expenses
         WHERE vehicle_id = $1 AND user_id = $2 AND category = $3 AND amount_eur = $4::numeric AND occurred_at::date = $5::date`,
        [vehicleId, req.user.sub, category, Number(e.amountEur), occurredAt]
      );
      if (dup.rowCount > 0) { result.skipped.entries++; continue; }
      const odometerKm = e.odometerKm === undefined || e.odometerKm === null || e.odometerKm === '' ? null : Math.round(Number(e.odometerKm));
      await client.query(
        `INSERT INTO expenses (user_id, vehicle_id, category, amount_eur, odometer_km, occurred_at, vendor, description)
         VALUES ($1, $2, $3, $4::numeric, $5, $6, $7, $8)`,
        [
          req.user.sub, vehicleId, category, Number(e.amountEur), odometerKm, occurredAt,
          e.vendor ? String(e.vendor).trim() : null, e.description ? String(e.description).trim() : null
        ]
      );
      result.imported.entries++;
    }

    await client.query('COMMIT');
    res.status(201).json(result);
  } catch (e) {
    await client.query('ROLLBACK').catch(() => {});
    if (e.code === '22P02') return res.status(404).json({ error: 'vehicle_not_found' });
    serverError(res, e);
  } finally {
    client.release();
  }
});

// Fälligkeiten (TÜV, Wartung, Versicherung, Steuer) eines Fahrzeugs
app.get('/api/v1/vehicles/:vehicleId/reminders', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  try {
    const v = await pool.query('SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2', [vehicleId, req.user.sub]);
    if (v.rowCount === 0) return res.status(404).json({ error: 'vehicle_not_found' });
    const s = await pool.query('SELECT reminder_days FROM user_mail_settings WHERE user_id = $1', [req.user.sub]);
    res.json(await getVehicleReminders(pool, req.user.sub, vehicleId, s.rows[0]?.reminder_days ?? 30));
  } catch (e) {
    if (e.code === '22P02') return res.status(404).json({ error: 'vehicle_not_found' });
    serverError(res, e);
  }
});

// --- E-Mail-Benachrichtigungen (SMTP-Zugang je Benutzer) ---
function mailSettingsResponse(row, defaults) {
  return {
    // Ohne eigenen Server verschickt Fuelstat über den Standard-Mailserver an die Konto-Adresse
    default_available: Boolean(defaults),
    default_from: defaults ? (defaults.from_address || defaults.smtp_user || defaults.account_email) : null,
    uses_default: Boolean(defaults) && !row?.smtp_host,
    smtp_host: row?.smtp_host ?? null,
    smtp_port: row?.smtp_port ?? null,
    smtp_security: row?.smtp_security ?? 'starttls',
    smtp_user: row?.smtp_user ?? null,
    has_password: Boolean(row?.smtp_password_enc),
    from_address: row?.from_address ?? null,
    to_address: row?.to_address ?? null,
    reminders_enabled: row?.reminders_enabled ?? false,
    reminder_days: row?.reminder_days ?? 30,
    monthly_report: row?.monthly_report ?? false,
    last_sent_at: row?.last_sent_at ?? null,
    last_error: row?.last_error ?? null
  };
}

const MAIL_ERROR_CODES = new Set(['mail_not_configured', 'smtp_host_not_allowed']);
function mailErrorResponse(res, e) {
  if (MAIL_ERROR_CODES.has(e.code)) return res.status(400).json({ error: e.code });
  if (e.code === 'ENOTFOUND' || e.code === 'EAI_AGAIN') return res.status(400).json({ error: 'smtp_host_not_found' });
  // SMTP-Fehler (Login, TLS, Verbindung) an den Benutzer weitergeben, damit er die Einstellungen korrigieren kann
  return res.status(502).json({ error: 'smtp_failed', detail: e.message });
}

app.get('/api/v1/auth/mail-settings', auth, async (req, res) => {
  try {
    const r = await pool.query('SELECT * FROM user_mail_settings WHERE user_id = $1', [req.user.sub]);
    res.json(mailSettingsResponse(r.rows[0], await loadDefaultSmtp(pool)));
  } catch (e) {
    serverError(res, e);
  }
});

app.put('/api/v1/auth/mail-settings', auth, async (req, res) => {
  const b = req.body || {};
  const host = optionalText(b.smtpHost) ?? null;
  const port = optionalNumber(b.smtpPort) ?? null;
  const security = b.smtpSecurity === undefined ? 'starttls' : String(b.smtpSecurity);
  const emailOk = (v) => v === null || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  const fromAddress = optionalText(b.fromAddress) ?? null;
  const toAddress = optionalText(b.toAddress) ?? null;
  const reminderDays = optionalNumber(b.reminderDays) ?? 30;

  if (host !== null && !/^[a-z0-9.-]+$/i.test(host)) return res.status(400).json({ error: 'invalid_smtpHost' });
  if (host !== null && !ALLOWED_PORTS.includes(port)) return res.status(400).json({ error: 'invalid_smtpPort', allowed: ALLOWED_PORTS });
  if (!['tls', 'starttls', 'none'].includes(security)) return res.status(400).json({ error: 'invalid_smtpSecurity' });
  if (!emailOk(fromAddress)) return res.status(400).json({ error: 'invalid_fromAddress' });
  if (!emailOk(toAddress)) return res.status(400).json({ error: 'invalid_toAddress' });
  if (!Number.isInteger(reminderDays) || reminderDays < 1 || reminderDays > 180) return res.status(400).json({ error: 'invalid_reminderDays' });

  try {
    const defaults = await loadDefaultSmtp(pool);
    if ((b.remindersEnabled || b.monthlyReport) && host === null && !defaults) return res.status(400).json({ error: 'mail_not_configured' });
    if (host !== null) await resolveSmtpHost(host);

    // Passwort: nur ändern, wenn ein neues gesendet wird; clearPassword löscht es
    let passwordSql = 'smtp_password_enc';
    const values = [req.user.sub, host, host ? port : null, security, optionalText(b.smtpUser) ?? null, fromAddress, toAddress,
      Boolean(b.remindersEnabled), reminderDays, Boolean(b.monthlyReport)];
    if (b.smtpPassword) {
      values.push(encryptSecret(b.smtpPassword));
      passwordSql = `$${values.length}`;
    } else if (b.clearPassword) {
      passwordSql = 'NULL';
    }

    const r = await pool.query(
      `INSERT INTO user_mail_settings (user_id, smtp_host, smtp_port, smtp_security, smtp_user, from_address, to_address,
                                       reminders_enabled, reminder_days, monthly_report, smtp_password_enc)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, ${passwordSql === 'smtp_password_enc' ? 'NULL' : passwordSql})
       ON CONFLICT (user_id) DO UPDATE SET
         smtp_host = EXCLUDED.smtp_host, smtp_port = EXCLUDED.smtp_port, smtp_security = EXCLUDED.smtp_security,
         smtp_user = EXCLUDED.smtp_user, from_address = EXCLUDED.from_address, to_address = EXCLUDED.to_address,
         reminders_enabled = EXCLUDED.reminders_enabled, reminder_days = EXCLUDED.reminder_days,
         monthly_report = EXCLUDED.monthly_report,
         smtp_password_enc = ${passwordSql === 'smtp_password_enc' ? 'user_mail_settings.smtp_password_enc' : passwordSql},
         updated_at = now()
       RETURNING *`,
      values
    );
    res.json(mailSettingsResponse(r.rows[0], defaults));
  } catch (e) {
    if (e.code === 'smtp_host_not_allowed' || e.code === 'ENOTFOUND' || e.code === 'EAI_AGAIN') return mailErrorResponse(res, e);
    serverError(res, e);
  }
});

// Manuelles Senden über den Standard-Mailserver begrenzen (schützt den Server des Betreibers vor Missbrauch)
const MANUAL_DEFAULT_MAILS = 5;
function manualMailLimited(settings, userId) {
  if (!settings?.uses_default) return false;
  const key = `default-mail:${userId}`;
  if (isLockedOut(key, MANUAL_DEFAULT_MAILS)) return true;
  recordFailure(key);
  return false;
}

// Test-Mail mit den gespeicherten Einstellungen
app.post('/api/v1/auth/mail-settings/test', auth, async (req, res) => {
  try {
    const settings = await notifier.loadUserSettings(pool, req.user.sub);
    if (manualMailLimited(settings, req.user.sub)) return tooManyAttempts(res);
    await sendUserMail(settings, {
      subject: 'Fuelstat: Test-Mail',
      text: 'Hallo,\n\ndie E-Mail-Einstellungen in Fuelstat funktionieren.\n\nDeine Fuelstat-App',
      html: '<p>Hallo,</p><p>die E-Mail-Einstellungen in Fuelstat funktionieren.</p><p>Deine Fuelstat-App</p>'
    });
    res.json({ sent: true });
  } catch (e) {
    mailErrorResponse(res, e);
  }
});

// Erinnerungen bzw. Monatsbericht (Vormonat) sofort senden – unabhängig davon, ob sie schon verschickt wurden
app.post('/api/v1/auth/mail-settings/send-now', auth, async (req, res) => {
  const type = String(req.body?.type || '');
  if (!['reminders', 'report'].includes(type)) return res.status(400).json({ error: 'invalid_type' });
  try {
    const settings = await notifier.loadUserSettings(pool, req.user.sub);
    if (manualMailLimited(settings, req.user.sub)) return tooManyAttempts(res);
    if (type === 'reminders') {
      const due = await notifier.collectDueReminders(pool, settings);
      if (due.length === 0) return res.json({ sent: false, reason: 'nothing_due' });
      await sendUserMail(settings, notifier.reminderMail(due));
      return res.json({ sent: true, count: due.length });
    }
    const { year, month } = notifier.previousMonth();
    await sendUserMail(settings, notifier.reportMail(await notifier.buildMonthlyReport(pool, settings, year, month)));
    res.json({ sent: true });
  } catch (e) {
    mailErrorResponse(res, e);
  }
});

// --- Feedback der Benutzer (Beta) ---
const FEEDBACK_CATEGORIES = { fehler: 'Fehler', idee: 'Idee', lob: 'Lob', sonstiges: 'Sonstiges' };
const FEEDBACK_MAX_PER_WINDOW = 10;

/** Empfänger: FEEDBACK_EMAIL, sonst der Empfänger bzw. die Konto-Adresse des Standard-Mailservers */
function feedbackRecipient(defaults) {
  return process.env.FEEDBACK_EMAIL || defaults?.to_address || defaults?.account_email || null;
}

app.post('/api/v1/feedback', auth, async (req, res) => {
  const category = String(req.body?.category || '');
  const message = String(req.body?.message || '').trim();
  const page = optionalText(req.body?.page) ?? null;
  if (!FEEDBACK_CATEGORIES[category]) return res.status(400).json({ error: 'invalid_category' });
  if (message.length < 3 || message.length > 5000) return res.status(400).json({ error: 'invalid_message' });
  if (page !== null && page.length > 300) return res.status(400).json({ error: 'invalid_page' });

  const key = `feedback:${req.user.sub}`;
  if (isLockedOut(key, FEEDBACK_MAX_PER_WINDOW)) return tooManyAttempts(res);
  recordFailure(key);

  try {
    const u = await pool.query('SELECT email, username FROM users WHERE id = $1', [req.user.sub]);
    const user = u.rows[0] || {};
    const userAgent = String(req.get('user-agent') || '').slice(0, 300) || null;
    const r = await pool.query(
      `INSERT INTO feedback (user_id, user_email, category, message, page, user_agent)
       VALUES ($1, $2, $3, $4, $5, $6) RETURNING id, created_at`,
      [req.user.sub, user.email || null, category, message, page, userAgent]
    );

    // Gespeichert ist es auf jeden Fall; die Mail an den Betreiber ist ein Zusatz
    let mailed = false;
    const defaults = await loadDefaultSmtp(pool);
    const to = feedbackRecipient(defaults);
    if (defaults && to) {
      const esc = (v) => String(v ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
      const von = user.username ? `${user.username} <${user.email}>` : user.email;
      const zeilen = [`Von: ${von}`, `Art: ${FEEDBACK_CATEGORIES[category]}`, page ? `Seite: ${page}` : null, userAgent ? `Gerät: ${userAgent}` : null].filter(Boolean);
      try {
        await sendUserMail(withDefaultSmtp({}, defaults), {
          to,
          replyTo: user.email,
          subject: `Fuelstat-Feedback (${FEEDBACK_CATEGORIES[category]}) von ${user.username || user.email}`,
          text: `${zeilen.join('\n')}\n\n${message}\n`,
          html: `<p>${zeilen.map(esc).join('<br>')}</p><p style="white-space:pre-wrap">${esc(message)}</p>`
        });
        mailed = true;
      } catch (e) {
        console.error('[feedback] Mail fehlgeschlagen:', e.message);
      }
    }
    res.status(201).json({ id: r.rows[0].id, saved: true, mailed });
  } catch (e) {
    serverError(res, e);
  }
});

// Feedback-Liste für Admins (neueste zuerst)
app.get('/api/v1/feedback', auth, requireAdmin, async (req, res) => {
  try {
    const r = await pool.query(
      `SELECT f.id, f.category, f.message, f.page, f.user_agent, f.done, f.created_at,
              COALESCE(u.email, f.user_email) AS user_email, u.username, u.beta_tester
       FROM feedback f LEFT JOIN users u ON u.id = f.user_id
       ORDER BY f.done ASC, f.created_at DESC
       LIMIT 200`
    );
    res.json(r.rows);
  } catch (e) {
    serverError(res, e);
  }
});

app.patch('/api/v1/feedback/:id', auth, requireAdmin, async (req, res) => {
  if (typeof req.body?.done !== 'boolean') return res.status(400).json({ error: 'invalid_done' });
  try {
    const r = await pool.query('UPDATE feedback SET done = $2 WHERE id = $1 RETURNING id, done', [String(req.params.id), req.body.done]);
    if (r.rowCount === 0) return res.status(404).json({ error: 'feedback_not_found' });
    res.json(r.rows[0]);
  } catch (e) {
    if (e.code === '22P02') return res.status(404).json({ error: 'feedback_not_found' });
    serverError(res, e);
  }
});

app.get('/api/v1/vehicles/:vehicleId/stats', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();

  try {
    const data = await getVehicleStats(pool, req.user.sub, vehicleId);
    if (data?.error === 'vehicle_not_found') return res.status(404).json({ error: 'vehicle_not_found' });
    res.json(data);
  } catch (e) {
    serverError(res, e);
  }
});


// ============================================
// Media Upload Endpoints (User Media)
// ============================================

// Upload user media
app.post('/api/v1/media/upload', auth, upload.single('file'), verifyUploadedImage, async (req, res) => {
  if (!req.file) {
    return res.status(400).json({ error: 'missing_file' });
  }

  const mediaType = String(req.body?.mediaType || 'image').trim();
  const url = `${MEDIA_BASE_URL}/${req.file.filename}`;
  const mimeType = req.file.mimetype;
  const bytes = req.file.size;

  try {
    const r = await pool.query(
      `INSERT INTO user_media (user_id, media_type, url, mime_type, bytes)
       VALUES ($1, $2, $3, $4, $5)
       RETURNING id, user_id, media_type, url, mime_type, bytes, created_at`,
      [req.user.sub, mediaType, url, mimeType, bytes]
    );

    res.status(201).json(r.rows[0]);
  } catch (e) {
    // Datei löschen falls DB-Insert fehlschlägt
    fs.unlink(req.file.path, () => {});
    serverError(res, e);
  }
});

// List user media
app.get('/api/v1/media', auth, async (req, res) => {
  try {
    const r = await pool.query(
      `SELECT id, user_id, media_type, url, mime_type, bytes, created_at
       FROM user_media
       WHERE user_id = $1
       ORDER BY created_at DESC`,
      [req.user.sub]
    );
    res.json(r.rows);
  } catch (e) {
    serverError(res, e);
  }
});

// Get single media
app.get('/api/v1/media/:mediaId', auth, async (req, res) => {
  const mediaId = String(req.params.mediaId || '').trim();
  if (!mediaId) return res.status(400).json({ error: 'missing_mediaId' });

  try {
    const r = await pool.query(
      `SELECT id, user_id, media_type, url, mime_type, bytes, created_at
       FROM user_media
       WHERE id = $1 AND user_id = $2`,
      [mediaId, req.user.sub]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'media_not_found' });
    }

    res.json(r.rows[0]);
  } catch (e) {
    serverError(res, e);
  }
});

// Delete media
app.delete('/api/v1/media/:mediaId', auth, async (req, res) => {
  const mediaId = String(req.params.mediaId || '').trim();
  if (!mediaId) return res.status(400).json({ error: 'missing_mediaId' });

  try {
    const r = await pool.query(
      `SELECT url FROM user_media WHERE id = $1 AND user_id = $2`,
      [mediaId, req.user.sub]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'media_not_found' });
    }

    const url = r.rows[0].url;
    const filename = path.basename(url);

    // Aus DB löschen
    await pool.query(
      'DELETE FROM user_media WHERE id = $1 AND user_id = $2',
      [mediaId, req.user.sub]
    );

    // Datei löschen
    const filePath = path.join(UPLOAD_DIR, filename);
    fs.unlink(filePath, (err) => {
      // Ignoriere Fehler wenn Datei nicht existiert
    });

    return res.status(204).send();
  } catch (e) {
    serverError(res, e);
  }
});

// ============================================
// Media Upload Endpoints (Vehicle Media)
// ============================================

// Upload vehicle media
app.post('/api/v1/vehicles/:vehicleId/media/upload', auth, upload.single('file'), verifyUploadedImage, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });

  if (!req.file) {
    return res.status(400).json({ error: 'missing_file' });
  }

  try {
    // Prüfen, ob das Fahrzeug dem User gehört
    const v = await pool.query(
      'SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2',
      [vehicleId, req.user.sub]
    );
    if (v.rowCount === 0) {
      fs.unlink(req.file.path, () => {});
      return res.status(404).json({ error: 'vehicle_not_found' });
    }

    const mediaType = String(req.body?.mediaType || 'image').trim();
    const url = `${MEDIA_BASE_URL}/${req.file.filename}`;
    const mimeType = req.file.mimetype;
    const bytes = req.file.size;

    const r = await pool.query(
      `INSERT INTO vehicle_media (vehicle_id, user_id, media_type, url, mime_type, bytes)
       VALUES ($1, $2, $3, $4, $5, $6)
       RETURNING id, vehicle_id, user_id, media_type, url, mime_type, bytes, created_at`,
      [vehicleId, req.user.sub, mediaType, url, mimeType, bytes]
    );

    res.status(201).json(r.rows[0]);
  } catch (e) {
    // Datei löschen falls DB-Insert fehlschlägt
    fs.unlink(req.file.path, () => {});
    serverError(res, e);
  }
});

// List vehicle media
app.get('/api/v1/vehicles/:vehicleId/media', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });

  try {
    // Prüfen, ob das Fahrzeug dem User gehört
    const v = await pool.query(
      'SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2',
      [vehicleId, req.user.sub]
    );
    if (v.rowCount === 0) {
      return res.status(404).json({ error: 'vehicle_not_found' });
    }

    const r = await pool.query(
      `SELECT id, vehicle_id, user_id, media_type, url, mime_type, bytes, created_at
       FROM vehicle_media
       WHERE vehicle_id = $1 AND user_id = $2
       ORDER BY created_at DESC`,
      [vehicleId, req.user.sub]
    );
    res.json(r.rows);
  } catch (e) {
    serverError(res, e);
  }
});

// Delete vehicle media
app.delete('/api/v1/vehicles/:vehicleId/media/:mediaId', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  const mediaId = String(req.params.mediaId || '').trim();
  if (!vehicleId) return res.status(400).json({ error: 'missing_vehicleId' });
  if (!mediaId) return res.status(400).json({ error: 'missing_mediaId' });

  try {
    const r = await pool.query(
      `SELECT url FROM vehicle_media
       WHERE id = $1 AND vehicle_id = $2 AND user_id = $3`,
      [mediaId, vehicleId, req.user.sub]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'media_not_found' });
    }

    const url = r.rows[0].url;
    const filename = path.basename(url);

    // Aus DB löschen
    await pool.query(
      'DELETE FROM vehicle_media WHERE id = $1 AND vehicle_id = $2 AND user_id = $3',
      [mediaId, vehicleId, req.user.sub]
    );

    // Datei löschen
    const filePath = path.join(UPLOAD_DIR, filename);
    fs.unlink(filePath, (err) => {
      // Ignoriere Fehler wenn Datei nicht existiert
    });

    return res.status(204).send();
  } catch (e) {
    serverError(res, e);
  }
});

// Error Handler für multer
app.use((error, req, res, next) => {
  if (error instanceof multer.MulterError) {
    if (error.code === 'LIMIT_FILE_SIZE') {
      return res.status(400).json({ error: 'file_too_large', message: 'Datei ist zu groß (max. 20 MB)' });
    }
    return res.status(400).json({ error: 'upload_error', detail: error.message });
  }
  if (error) {
    return res.status(400).json({ error: 'upload_error', detail: error.message });
  }
  next();
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Backend V2 läuft auf Port ${PORT}`);
  notifier.startNotifier(pool);
});
