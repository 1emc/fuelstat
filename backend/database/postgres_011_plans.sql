-- backend/database/postgres_011_plans.sql
-- Tarif je Konto (Grundgerüst für ein späteres Premium-Modell, siehe backend/src/services/plans.js).
-- Solange PLAN_LIMITS_ENABLED nicht gesetzt ist, haben alle Konten den vollen Umfang.
BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS plan text NOT NULL DEFAULT 'free';
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_plan_check;
ALTER TABLE users ADD CONSTRAINT users_plan_check CHECK (plan IN ('free', 'plus', 'fleet'));
-- Ende eines bezahlten bzw. geschenkten Tarifs; NULL = unbefristet. Danach gilt 'free'.
ALTER TABLE users ADD COLUMN IF NOT EXISTS plan_until timestamptz NULL;

COMMIT;
