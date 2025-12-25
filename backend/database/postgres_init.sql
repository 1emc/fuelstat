CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email text NOT NULL UNIQUE,
  password_hash text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS vehicles (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name text NOT NULL,
  fuel_type text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS fillups (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  vehicle_id uuid NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
  odometer_km integer NOT NULL CHECK (odometer_km >= 0),
  amount numeric(10,3) NOT NULL CHECK (amount >= 0),
  unit text NOT NULL DEFAULT 'l' CHECK (unit IN ('l','kwh','kg')),
  total_cost_eur numeric(10,2) NOT NULL CHECK (total_cost_eur >= 0),
  price_per_unit numeric(10,4),
  station text NULL,
  filled_at timestamptz NOT NULL DEFAULT now(),
  created_at timestamptz NOT NULL DEFAULT now(),
  is_full boolean NOT NULL DEFAULT true,
  skip_previous boolean NOT NULL DEFAULT false
);

CREATE INDEX IF NOT EXISTS idx_vehicles_user_created ON vehicles(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_fillups_user_vehicle_filled ON fillups(user_id, vehicle_id, filled_at DESC);
CREATE INDEX IF NOT EXISTS idx_fillups_vehicle_odo ON fillups(vehicle_id, odometer_km);

-- Allgemeine Ausgaben pro Fahrzeug
CREATE TABLE IF NOT EXISTS expenses (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  vehicle_id uuid NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,

  category text NOT NULL,
  amount_eur numeric(10,2) NOT NULL CHECK (amount_eur >= 0),

  odometer_km integer NULL CHECK (odometer_km >= 0),
  occurred_at timestamptz NOT NULL DEFAULT now(),

  vendor text NULL,
  description text NULL,

  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_expenses_user_vehicle_date
  ON expenses(user_id, vehicle_id, occurred_at DESC);

CREATE INDEX IF NOT EXISTS idx_expenses_vehicle_odo
  ON expenses(vehicle_id, odometer_km);

-- Widerrufene Einzel-Tokens (JWT jti-basiert)
CREATE TABLE IF NOT EXISTS revoked_tokens (
  jti text PRIMARY KEY,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  revoked_at timestamptz NOT NULL DEFAULT now(),
  reason text NULL
);

CREATE INDEX IF NOT EXISTS idx_revoked_tokens_user
  ON revoked_tokens(user_id, revoked_at DESC);

-- User-weite Token-Revocations (alle Tokens vor revoked_after ungültig)
CREATE TABLE IF NOT EXISTS user_token_revocations (
  user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  revoked_after timestamptz NOT NULL DEFAULT now(),
  reason text NULL
);
