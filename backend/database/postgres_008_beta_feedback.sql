-- backend/database/postgres_008_beta_feedback.sql
-- Beta-Phase: Kennzeichen für Beta-Tester und Feedback der Benutzer
BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS beta_tester boolean NOT NULL DEFAULT false;

CREATE TABLE IF NOT EXISTS feedback (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NULL REFERENCES users(id) ON DELETE SET NULL,
  user_email text NULL,                 -- bleibt erhalten, auch wenn das Konto gelöscht wird
  category text NOT NULL CHECK (category IN ('fehler', 'idee', 'lob', 'sonstiges')),
  message text NOT NULL,
  page text NULL,
  user_agent text NULL,
  done boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_feedback_created ON feedback(created_at DESC);

COMMIT;
