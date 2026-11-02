-- backend/database/postgres_013_vehicle_details.sql
-- Fahrzeug anlegen ausbauen (#45): Kennzeichen, FIN, Erstzulassung, Kauf/Verkauf, Intervalle, Versicherung, Steuer, Reifen.
-- Alle Felder optional; Fälligkeiten nutzen sie, wenn vorhanden (backend/src/services/reminders.js).
BEGIN;

ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS license_plate text NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS vin text NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS first_registration date NULL;   -- erster TÜV nach 36 Monaten
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS purchase_date date NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS purchase_price numeric(10,2) NULL CHECK (purchase_price IS NULL OR purchase_price >= 0);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS sale_date date NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS sale_price numeric(10,2) NULL CHECK (sale_price IS NULL OR sale_price >= 0);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS service_interval_months smallint NULL CHECK (service_interval_months IS NULL OR service_interval_months BETWEEN 1 AND 60);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS service_interval_km integer NULL CHECK (service_interval_km IS NULL OR service_interval_km BETWEEN 1000 AND 100000);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS oil_interval_months smallint NULL CHECK (oil_interval_months IS NULL OR oil_interval_months BETWEEN 1 AND 60);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS oil_interval_km integer NULL CHECK (oil_interval_km IS NULL OR oil_interval_km BETWEEN 1000 AND 100000);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS insurance_company text NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS insurance_premium numeric(10,2) NULL CHECK (insurance_premium IS NULL OR insurance_premium >= 0);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS insurance_due_month smallint NULL CHECK (insurance_due_month IS NULL OR insurance_due_month BETWEEN 1 AND 12);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS tax_amount numeric(10,2) NULL CHECK (tax_amount IS NULL OR tax_amount >= 0);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS tax_due_month smallint NULL CHECK (tax_due_month IS NULL OR tax_due_month BETWEEN 1 AND 12);
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS tire_size_summer text NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS tire_size_winter text NULL;

COMMIT;
