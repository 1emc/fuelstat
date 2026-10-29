// backend/src/services/stats.js

// Antriebsarten; 'phev' (Plug-in-Hybrid) tankt und lädt
const FUEL_TYPES = new Set(['diesel', 'e5', 'e10', 'lpg', 'cng', 'electric', 'hybrid', 'phev', 'hydrogen', 'other']);

// Mengeneinheit aus dem Kraftstoff des Fahrzeugs (Standard für neue Einträge)
function unitForFuelType(fuelType) {
  const f = String(fuelType || '').toLowerCase();
  if (f === 'electric') return 'kwh';
  if (f === 'cng' || f === 'hydrogen') return 'kg';
  return 'l';
}

// Alle Einheiten, die ein Fahrzeug haben kann; die erste ist der Standard
function unitsForFuelType(fuelType) {
  return String(fuelType || '').toLowerCase() === 'phev' ? ['l', 'kwh'] : [unitForFuelType(fuelType)];
}

function trendOf(segRows) {
  if (segRows.length < 2) return null;
  const current = Number(segRows[0].l_per_100km);
  const prev = segRows.slice(1, 4).map(x => Number(x.l_per_100km)).filter(Number.isFinite);
  if (!Number.isFinite(current) || !prev.length) return null;
  const avgPrev = prev.reduce((a, b) => a + b, 0) / prev.length;
  return {
    current: Number(current.toFixed(2)),
    avgPrev: Number(avgPrev.toFixed(2)),
    diff: Number((current - avgPrev).toFixed(2))
  };
}

async function getVehicleStats(pool, userId, vehicleId) {
  const v = await pool.query(
    'SELECT fuel_type FROM vehicles WHERE id = $1 AND user_id = $2',
    [vehicleId, userId]
  );
  if (v.rowCount === 0) return { error: 'vehicle_not_found' };
  const primaryUnit = unitForFuelType(v.rows[0].fuel_type);

  const odo = await pool.query(
    'SELECT MAX(odometer_km) AS max_odo FROM fillups WHERE user_id=$1 AND vehicle_id=$2',
    [userId, vehicleId]
  );
  const currentOdo = odo.rows[0]?.max_odo ?? null;

  // Ø Preis je Einheit (bei Plug-in-Hybriden getrennt für Liter und kWh)
  const ppl = await pool.query(
    `SELECT unit,
       CASE WHEN SUM(amount) > 0
         THEN ROUND((SUM(total_cost_eur) / SUM(amount))::numeric, 3)
         ELSE NULL
       END AS avg_price
     FROM fillups
     WHERE user_id=$1 AND vehicle_id=$2
     GROUP BY unit`,
    [userId, vehicleId]
  );

  // Full-to-Full-Abschnitte je Einheit: Liter von Tankfüllung zu Tankfüllung, kWh von Ladung zu Ladung
  const segments = await pool.query(
    `
    WITH ordered AS (
      -- Reihenfolge: Zeitpunkt, bei gleichem Zeitpunkt (z. B. importierte Ladungen eines Tages) der Tachostand
      SELECT id, filled_at, odometer_km, amount, unit, is_full, skip_previous,
        ROW_NUMBER() OVER (ORDER BY filled_at, odometer_km, created_at) AS rn
      FROM fillups
      WHERE user_id=$1 AND vehicle_id=$2
    ),
    fulls AS (
      SELECT *,
        LAG(odometer_km) OVER (PARTITION BY unit ORDER BY rn) AS prev_full_odo,
        LAG(rn) OVER (PARTITION BY unit ORDER BY rn) AS prev_full_rn
      FROM ordered
      WHERE is_full = true
    ),
    seg AS (
      SELECT
        f.id AS full_id,
        f.filled_at AS full_at,
        f.unit,
        f.rn,
        (f.odometer_km - f.prev_full_odo) AS km,
        (
          SELECT COALESCE(SUM(o.amount),0)
          FROM ordered o
          WHERE o.unit = f.unit AND o.rn > f.prev_full_rn AND o.rn <= f.rn
        ) AS liters_segment
      FROM fulls f
      -- skip_previous: Vollbetankung ist nur neuer Startpunkt (vorherige Tankfüllungen fehlen)
      WHERE f.prev_full_rn IS NOT NULL AND f.skip_previous = false
    )
    SELECT
      full_id, full_at, unit, km, liters_segment,
      CASE WHEN km > 0 THEN ROUND(((liters_segment / km) * 100)::numeric, 2) ELSE NULL END AS l_per_100km
    FROM seg
    ORDER BY rn DESC
    `,
    [userId, vehicleId]
  );

  const segRows = segments.rows;
  const units = {};
  for (const row of ppl.rows) {
    const segs = segRows.filter(s => s.unit === row.unit);
    units[row.unit] = { avgPrice: row.avg_price, trend: trendOf(segs) };
  }

  // Passt die Einheit der Einträge nicht zur Antriebsart (z. B. später geändert), zählt die einzige vorhandene
  const main = units[primaryUnit] ?? (ppl.rows.length === 1 ? units[ppl.rows[0].unit] : null);

  return {
    currentOdo,
    // Für Fahrzeuge mit einer Einheit wie bisher; Plug-in-Hybride: Werte des Kraftstoffs, alles Weitere in units
    avgPricePerLiter: main?.avgPrice ?? null,
    segments: segRows,
    trend: main?.trend ?? null,
    units
  };
}

module.exports = { getVehicleStats, FUEL_TYPES, unitForFuelType, unitsForFuelType };
