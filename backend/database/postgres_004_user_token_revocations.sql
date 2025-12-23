-- backend/database/postgres_004_user_token_revocations.sql
BEGIN;

CREATE TABLE IF NOT EXISTS user_token_revocations (
  user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  revoked_after timestamptz NOT NULL DEFAULT now(),
  reason text NULL
);

COMMIT;


