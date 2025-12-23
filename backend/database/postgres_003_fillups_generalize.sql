-- backend/database/postgres_003_fillups_generalize.sql
BEGIN;

-- liters -> amount
ALTER TABLE fillups
  RENAME COLUMN liters TO amount;

-- price_total_eur -> total_cost_eur (neutraler)
ALTER TABLE fillups
  RENAME COLUMN price_total_eur TO total_cost_eur;

-- Einheit dazu
ALTER TABLE fillups
  ADD COLUMN IF NOT EXISTS unit text NOT NULL DEFAULT 'l'
  CHECK (unit IN ('l','kwh','kg'));

-- Optional, aber sehr praktisch: Preis je Einheit (statisch gespeichert)
ALTER TABLE fillups
  ADD COLUMN IF NOT EXISTS price_per_unit numeric(10,4);

-- Backfill für bestehende Daten
UPDATE fillups
SET unit = COALESCE(unit, 'l')
WHERE unit IS NULL;

UPDATE fillups
SET price_per_unit = CASE WHEN amount > 0 THEN ROUND(total_cost_eur / amount, 4) ELSE NULL END
WHERE price_per_unit IS NULL;

-- Indizes ggf. anpassen (nur falls du sie schon hast, sonst ignorieren)
-- DROP INDEX IF EXISTS idx_fillups_vehicle_odo;
CREATE INDEX IF NOT EXISTS idx_fillups_vehicle_odo ON fillups(vehicle_id, odometer_km);

COMMIT;

