// backend/src/services/reminders.js
// Fälligkeiten (TÜV, Wartung, Versicherung, Steuer) aus den erfassten Ausgaben ableiten.
// Regeln: TÜV alle 24 Monate, Wartung/Inspektion alle 12 Monate oder 15.000 km,
// Versicherung und Steuer jährlich – jeweils ab dem letzten Eintrag der Kategorie.

const RULES = [
  { title: 'TÜV', categories: ['TUV'], months: 24, km: null },
  { title: 'Wartung / Inspektion', categories: ['Wartung', 'Inspektion'], months: 12, km: 15000 },
  { title: 'Versicherung', categories: ['Versicherung'], months: 12, km: null },
  { title: 'Kfz-Steuer', categories: ['Steuer'], months: 12, km: null }
];

function toDateString(d) {
  return d.toISOString().slice(0, 10);
}

// Monate addieren wie PHP DateTime::modify('+N months') (31.01. + 1 Monat = 03.03.)
function addMonths(date, months) {
  const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
  d.setUTCMonth(d.getUTCMonth() + months);
  return d;
}

/**
 * @param pool pg-Pool
 * @param {number} warnDays Vorlaufzeit in Tagen für "bald fällig"
 * @returns Liste mit title, category, last, due_date, due_km, status (ok|bald|ueberfaellig|unbekannt), days
 */
async function getVehicleReminders(pool, userId, vehicleId, warnDays = 30, today = new Date()) {
  const categories = RULES.flatMap((r) => r.categories);
  const last = await pool.query(
    `SELECT DISTINCT ON (category) category, occurred_at, odometer_km
     FROM expenses
     WHERE user_id = $1 AND vehicle_id = $2 AND category = ANY($3)
     ORDER BY category, occurred_at DESC`,
    [userId, vehicleId, categories]
  );
  const odo = await pool.query(
    'SELECT MAX(odometer_km) AS max_odo FROM fillups WHERE user_id = $1 AND vehicle_id = $2',
    [userId, vehicleId]
  );
  const currentOdo = odo.rows[0]?.max_odo ?? null;

  const todayUtc = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
  return RULES.map((rule) => {
    // Neuesten Eintrag aller Kategorien der Regel nehmen (Wartung oder Inspektion)
    const latest = last.rows
      .filter((r) => rule.categories.includes(r.category))
      .sort((a, b) => new Date(b.occurred_at) - new Date(a.occurred_at))[0];

    const row = {
      title: rule.title,
      category: rule.categories[0],
      last: null,
      due_date: null,
      due_km: null,
      status: 'unbekannt',
      days: null
    };
    if (!latest) return row;

    const lastDate = new Date(latest.occurred_at);
    const lastDay = new Date(Date.UTC(lastDate.getUTCFullYear(), lastDate.getUTCMonth(), lastDate.getUTCDate()));
    const due = addMonths(lastDay, rule.months);
    const days = Math.round((due - todayUtc) / 86400000);
    row.last = toDateString(lastDay);
    row.due_date = toDateString(due);
    row.days = days;

    let restKm = null;
    if (rule.km !== null && latest.odometer_km !== null && currentOdo !== null) {
      row.due_km = latest.odometer_km + rule.km;
      restKm = row.due_km - currentOdo;
    }

    if (days < 0 || (restKm !== null && restKm <= 0)) row.status = 'ueberfaellig';
    else if (days <= warnDays || (restKm !== null && restKm <= 1000)) row.status = 'bald';
    else row.status = 'ok';
    return row;
  });
}

module.exports = { getVehicleReminders, RULES };
