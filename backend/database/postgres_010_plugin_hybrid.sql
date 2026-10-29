-- backend/database/postgres_010_plugin_hybrid.sql
-- Plug-in-Hybride: eigene Antriebsart 'phev' (tankt und lädt) und Akkugröße zusätzlich zum Tank
BEGIN;

ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS vehicles_fuel_type_check;
ALTER TABLE vehicles ADD CONSTRAINT vehicles_fuel_type_check
  CHECK (fuel_type = ANY (ARRAY['diesel','e5','e10','lpg','cng','electric','hybrid','phev','hydrogen','other']));

ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS battery_capacity numeric(10,3) NULL; -- kWh, nur Plug-in-Hybride

COMMIT;
