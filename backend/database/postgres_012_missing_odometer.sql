-- backend/database/postgres_012_missing_odometer.sql
-- Tank- und Ladevorgänge ohne Tachostand (#41): Tachostand darf fehlen und später nachgetragen werden.
-- odometer_estimated: Wert ist nur eine Schätzung (Platzhalter), zählt nicht für Verbrauch und Tachostand.
BEGIN;

ALTER TABLE fillups ALTER COLUMN odometer_km DROP NOT NULL;
ALTER TABLE fillups ADD COLUMN IF NOT EXISTS odometer_estimated boolean NOT NULL DEFAULT false;

-- Erinnerung "Kilometerstand nachtragen" wird je Eintrag nur einmal verschickt
ALTER TABLE mail_log DROP CONSTRAINT IF EXISTS mail_log_kind_check;
ALTER TABLE mail_log ADD CONSTRAINT mail_log_kind_check CHECK (kind IN ('reminder', 'report', 'odometer'));

COMMIT;
