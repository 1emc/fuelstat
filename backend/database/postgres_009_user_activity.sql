-- backend/database/postgres_009_user_activity.sql
-- Aktivität für die Benutzerverwaltung: letzter Login und zuletzt aktiv (höchstens stündlich aktualisiert)
BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at timestamptz NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_at timestamptz NULL;

COMMIT;
