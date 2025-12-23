-- backend/database/postgres_003_revoked_tokens.sql
BEGIN;

CREATE TABLE IF NOT EXISTS revoked_tokens (
  jti text PRIMARY KEY,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  revoked_at timestamptz NOT NULL DEFAULT now(),
  reason text NULL
);

CREATE INDEX IF NOT EXISTS idx_revoked_tokens_user
  ON revoked_tokens(user_id, revoked_at DESC);

COMMIT;


