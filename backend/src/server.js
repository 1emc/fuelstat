const express = require('express');
const helmet = require('helmet');
const cors = require('cors');
const { Pool } = require('pg');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');
const { getVehicleStats } = require('./services/stats');

const app = express();

app.use(helmet());
app.use(cors());
app.use(express.json());

const PORT = process.env.PORT || 3000;

if (!process.env.DATABASE_URL) {
  throw new Error('DATABASE_URL fehlt');
}
if (!process.env.JWT_SECRET || process.env.JWT_SECRET.length < 16) {
  throw new Error('JWT_SECRET fehlt oder ist zu kurz');
}

const pool = new Pool({ connectionString: process.env.DATABASE_URL });

function signToken(user) {
  return jwt.sign(
    { sub: user.id, email: user.email },
    process.env.JWT_SECRET,
    { expiresIn: '7d' }
  );
}

function auth(req, res, next) {
  const h = req.header('Authorization') || '';
  const m = h.match(/^Bearer\s+(.+)$/i);
  if (!m) return res.status(401).json({ error: 'missing_token' });

  try {
    req.user = jwt.verify(m[1], process.env.JWT_SECRET);
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
      'INSERT INTO users (email, password_hash) VALUES ($1, $2) RETURNING id, email, created_at',
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
      'SELECT id, email, password_hash, created_at FROM users WHERE email = $1',
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

// Create vehicle
app.post('/api/v1/vehicles', auth, async (req, res) => {
  const name = String(req.body?.name || '').trim();
  const fuelType = String(req.body?.fuelType || '').trim().toLowerCase();

  const allowed = new Set(['diesel','petrol','electric','hybrid','cng','lpg','other']);
  if (!name) return res.status(400).json({ error: 'missing_name' });
  if (!allowed.has(fuelType)) return res.status(400).json({ error: 'invalid_fuelType' });

  try {
    const r = await pool.query(
      'INSERT INTO vehicles (user_id, name, fuel_type) VALUES ($1, $2, $3) RETURNING id, name, fuel_type, created_at',
      [req.user.sub, name, fuelType]
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
      'SELECT id, name, fuel_type, created_at FROM vehicles WHERE user_id = $1 ORDER BY created_at DESC',
      [req.user.sub]
    );
    res.json(r.rows);
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
      else if (fuelType === 'cng') unit = 'kg';
      else unit = 'l';
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


app.listen(PORT, '0.0.0.0', () => {
  console.log(`Backend V2 läuft auf Port ${PORT}`);
});
