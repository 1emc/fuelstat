const express = require('express');
const helmet = require('helmet');
const cors = require('cors');
const { Pool } = require('pg');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');
const { randomUUID } = require('crypto');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const { getVehicleStats } = require('./services/stats');

const app = express();

app.use(helmet());
app.use(cors());
app.use(express.json());

// Statischer File-Server für Uploads
app.use(MEDIA_BASE_URL, express.static(UPLOAD_DIR));

const PORT = process.env.PORT || 3000;

if (!process.env.DATABASE_URL) {
  throw new Error('DATABASE_URL fehlt');
}
if (!process.env.JWT_SECRET || process.env.JWT_SECRET.length < 16) {
  throw new Error('JWT_SECRET fehlt oder ist zu kurz');
}

const pool = new Pool({ connectionString: process.env.DATABASE_URL });

// Media Upload Konfiguration
const UPLOAD_DIR = process.env.UPLOAD_DIR || path.join(__dirname, '../../uploads');
const MEDIA_BASE_URL = process.env.MEDIA_BASE_URL || '/uploads';

// Upload-Verzeichnis erstellen falls nicht vorhanden
if (!fs.existsSync(UPLOAD_DIR)) {
  fs.mkdirSync(UPLOAD_DIR, { recursive: true });
}

const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, UPLOAD_DIR);
  },
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname);
    const filename = `${randomUUID()}${ext}`;
    cb(null, filename);
  }
});

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

async function auth(req, res, next) {
  const h = req.header('Authorization') || '';
  const m = h.match(/^Bearer\s+(.+)$/i);
  if (!m) return res.status(401).json({ error: 'missing_token' });

  try {
    const payload = jwt.verify(m[1], process.env.JWT_SECRET);

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
    return next();
  } catch {
    return res.status(401).json({ error: 'invalid_token' });
  }
}

// Healthcheck
app.get('/api/v1/health', (req, res) => {
  res.status(200).json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    service: 'fuelstat-api-v2'
  });
});

// Whoami - zeigt aktuell angemeldeten User
app.get('/api/v1/whoami', auth, async (req, res) => {
  try {
    // Zusätzlich User-Daten aus DB holen für vollständige Info
    const r = await pool.query(
      `SELECT id, email, username, mfa_enabled, profile_image_url, created_at, updated_at
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
      created_at: r.rows[0].created_at,
      updated_at: r.rows[0].updated_at,
      token: {
        jti: req.user.jti || null,
        iat: req.user.iat ? new Date(req.user.iat * 1000).toISOString() : null,
        exp: req.user.exp ? new Date(req.user.exp * 1000).toISOString() : null
      }
    });
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// DB Ping
app.get('/api/v1/db-ping', async (req, res) => {
  try {
    const r = await pool.query('SELECT 1 AS ok');
    res.json({ ok: true, result: r.rows[0] });
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
});

// Register
app.post('/api/v1/auth/register', async (req, res) => {
  const email = String(req.body?.email || '').trim().toLowerCase();
  const password = String(req.body?.password || '');

  if (!email || !email.includes('@')) return res.status(400).json({ error: 'invalid_email' });
  if (password.length < 8) return res.status(400).json({ error: 'password_too_short' });

  try {
    const passwordHash = await bcrypt.hash(password, 12);
    const r = await pool.query(
      `INSERT INTO users (email, password_hash)
       VALUES ($1, $2)
       RETURNING id, email, username, mfa_enabled, profile_image_url, created_at, updated_at`,
      [email, passwordHash]
    );
    const user = r.rows[0];
    res.status(201).json({ token: signToken(user), user });
  } catch (e) {
    if (e.code === '23505') return res.status(409).json({ error: 'email_already_exists' });
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// Login
app.post('/api/v1/auth/login', async (req, res) => {
  const email = String(req.body?.email || '').trim().toLowerCase();
  const password = String(req.body?.password || '');

  if (!email || !password) return res.status(400).json({ error: 'missing_credentials' });

  try {
    const r = await pool.query(
      `SELECT id, email, password_hash, username, mfa_enabled, profile_image_url, created_at, updated_at
       FROM users WHERE email = $1`,
      [email]
    );
    if (r.rowCount === 0) return res.status(401).json({ error: 'invalid_credentials' });

    const user = r.rows[0];
    const ok = await bcrypt.compare(password, user.password_hash);
    if (!ok) return res.status(401).json({ error: 'invalid_credentials' });

    delete user.password_hash;
    res.json({ token: signToken(user), user });
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// List all users (Admin-Endpoint, aktuell ohne weitere Einschränkung)
app.get('/api/v1/auth/users', auth, async (req, res) => {
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
       ORDER BY created_at ASC`
    );
    res.json(r.rows);
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// Get single user (Admin-Endpoint)
app.get('/api/v1/auth/users/:userId', auth, async (req, res) => {
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
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// Patch single user (Admin-Endpoint)
app.patch('/api/v1/auth/users/:userId', auth, async (req, res) => {
  const userId = String(req.params.userId || '').trim();
  if (!userId) return res.status(400).json({ error: 'missing_userId' });

  const email =
    req.body?.email !== undefined
      ? String(req.body.email).trim().toLowerCase()
      : undefined;
  const username =
    req.body?.username !== undefined
      ? String(req.body.username).trim() || null
      : undefined;
  const profileImageUrl =
    req.body?.profileImageUrl !== undefined
      ? String(req.body.profileImageUrl).trim() || null
      : undefined;

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
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// Delete single user (Admin-Endpoint)
app.delete('/api/v1/auth/users/:userId', auth, async (req, res) => {
  const userId = String(req.params.userId || '').trim();
  if (!userId) return res.status(400).json({ error: 'missing_userId' });

  try {
    const r = await pool.query(
      'DELETE FROM users WHERE id = $1 RETURNING id',
      [userId]
    );

    if (r.rowCount === 0) {
      return res.status(404).json({ error: 'user_not_found' });
    }

    return res.status(204).send();
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// Revoke all tokens of a user (Admin-Endpoint)
app.post('/api/v1/auth/users/:userId/revoke-all', auth, async (req, res) => {
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
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// List revoked tokens (Admin-Endpoint)
app.get('/api/v1/auth/tokens', auth, async (req, res) => {
  const userId =
    req.query.userId !== undefined
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
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// Revoke a token (Admin-Endpoint)
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
  const year = req.body?.year !== undefined ? Number(req.body.year) : null;
  const imageUrl = req.body?.imageUrl ? String(req.body.imageUrl).trim() : null;
  const odometerKm = req.body?.odometerKm !== undefined ? Number(req.body.odometerKm) : null;
  const mileageKm = req.body?.mileageKm !== undefined ? Number(req.body.mileageKm) : null;
  const tankCapacity = req.body?.tankCapacity !== undefined ? Number(req.body.tankCapacity) : null;
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
  const brand =
    req.body?.brand !== undefined
      ? String(req.body.brand).trim() || null
      : undefined;
  const model =
    req.body?.model !== undefined
      ? String(req.body.model).trim() || null
      : undefined;
  const year =
    req.body?.year !== undefined ? Number(req.body.year) : undefined;
  const imageUrl =
    req.body?.imageUrl !== undefined
      ? String(req.body.imageUrl).trim() || null
      : undefined;
  const odometerKm =
    req.body?.odometerKm !== undefined ? Number(req.body.odometerKm) : undefined;
  const mileageKm =
    req.body?.mileageKm !== undefined ? Number(req.body.mileageKm) : undefined;
  const tankCapacity =
    req.body?.tankCapacity !== undefined ? Number(req.body.tankCapacity) : undefined;
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
      else if (fuelType === 'cng' || fuelType === 'lpg') unit = 'kg';
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
         filled_at
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
         COALESCE($8, now())
       )
       RETURNING id, vehicle_id, odometer_km, amount, unit, total_cost_eur, price_per_unit, station, filled_at, created_at, is_full, skip_previous`,
      [req.user.sub, vehicleId, odometerKm, amount, unit, totalCostEur, station, filledAt]
    );

    res.status(201).json(r.rows[0]);
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
  } finally {
    client.release();
  }
});

// List fillups by vehicle
app.get('/api/v1/vehicles/:vehicleId/fillups', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  const limit = Math.min(200, Math.max(1, Number(req.query.limit || 50)));

  try {
    const v = await pool.query('SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2', [vehicleId, req.user.sub]);
    if (v.rowCount === 0) return res.status(404).json({ error: 'vehicle_not_found' });

    const r = await pool.query(
      `SELECT
         id,
         odometer_km,
         amount       AS liters,
         total_cost_eur AS price_total_eur,
         station,
         filled_at,
         created_at
       FROM fillups
       WHERE user_id = $1 AND vehicle_id = $2
       ORDER BY filled_at DESC
       LIMIT $3`,
      [req.user.sub, vehicleId, limit]
    );
    res.json(r.rows);
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// List expense entries by vehicle
app.get('/api/v1/vehicles/:vehicleId/entries', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();
  const limit = Math.min(200, Math.max(1, Number(req.query.limit || 50)));

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
       LIMIT $3`,
      [req.user.sub, vehicleId, limit]
    );

    res.json(r.rows);
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// Statistics of Vehicle
app.get('/api/v1/vehicles/:vehicleId/stats', auth, async (req, res) => {
  const vehicleId = String(req.params.vehicleId || '').trim();

  try {
    const data = await getVehicleStats(pool, req.user.sub, vehicleId);
    if (data?.error === 'vehicle_not_found') return res.status(404).json({ error: 'vehicle_not_found' });
    res.json(data);
  } catch (e) {
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});


// ============================================
// Media Upload Endpoints (User Media)
// ============================================

// Upload user media
app.post('/api/v1/media/upload', auth, upload.single('file'), async (req, res) => {
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
  }
});

// ============================================
// Media Upload Endpoints (Vehicle Media)
// ============================================

// Upload vehicle media
app.post('/api/v1/vehicles/:vehicleId/media/upload', auth, upload.single('file'), async (req, res) => {
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
    res.status(500).json({ error: 'server_error', detail: e.message });
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
});
