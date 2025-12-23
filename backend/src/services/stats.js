// backend/src/services/stats.js
async function getVehicleStats(pool, userId, vehicleId) {
  const v = await pool.query(
    'SELECT 1 FROM vehicles WHERE id = $1 AND user_id = $2',
    [vehicleId, userId]
  );
  if (v.rowCount === 0) return { error: 'vehicle_not_found' };

  const odo = await pool.query(
    'SELECT MAX(odometer_km) AS max_odo FROM fillups WHERE user_id=$1 AND vehicle_id=$2',
    [userId, vehicleId]
  );
  const currentOdo = odo.rows[0]?.max_odo ?? null;

  const ppl = await pool.query(
    `SELECT
       CASE WHEN SUM(liters) > 0
         THEN ROUND((SUM(price_total_eur) / SUM(liters))::numeric, 3)
         ELSE NULL
       END AS avg_price_per_liter
     FROM fillups
     WHERE user_id=$1 AND vehicle_id=$2`,
    [userId, vehicleId]
  );

  const segments = await pool.query(
    `
    WITH ordered AS (
      SELECT id, filled_at, odometer_km, liters, is_full, skip_previous
      FROM fillups
      WHERE user_id=$1 AND vehicle_id=$2
      ORDER BY filled_at
    ),
    fulls AS (
      SELECT *,
        LAG(odometer_km) OVER (ORDER BY filled_at) AS prev_full_odo,
        LAG(filled_at) OVER (ORDER BY filled_at) AS prev_full_at
      FROM ordered
      WHERE is_full = true AND skip_previous = false
    ),
    seg AS (
      SELECT
        f.id AS full_id,
        f.filled_at AS full_at,
        (f.odometer_km - f.prev_full_odo) AS km,
        (
          SELECT COALESCE(SUM(o.liters),0)
          FROM ordered o
          WHERE o.filled_at > f.prev_full_at AND o.filled_at <= f.filled_at
        ) AS liters_segment
      FROM fulls f
      WHERE f.prev_full_at IS NOT NULL
    )
    SELECT
      full_id, full_at, km, liters_segment,
      CASE WHEN km > 0 THEN ROUND(((liters_segment / km) * 100)::numeric, 2) ELSE NULL END AS l_per_100km
    FROM seg
    ORDER BY full_at DESC
    LIMIT 12
    `,
    [userId, vehicleId]
  );

  const segRows = segments.rows;
  let trend = null;

  if (segRows.length >= 2) {
    const current = Number(segRows[0].l_per_100km);
    const prev = segRows.slice(1, 4).map(x => Number(x.l_per_100km)).filter(Number.isFinite);
    if (Number.isFinite(current) && prev.length) {
      const avgPrev = prev.reduce((a, b) => a + b, 0) / prev.length;
      trend = {
        current: Number(current.toFixed(2)),
        avgPrev: Number(avgPrev.toFixed(2)),
        diff: Number((current - avgPrev).toFixed(2))
      };
    }
  }

  return {
    currentOdo,
    avgPricePerLiter: ppl.rows[0]?.avg_price_per_liter ?? null,
    segments: segRows,
    trend
  };
}

module.exports = { getVehicleStats };

