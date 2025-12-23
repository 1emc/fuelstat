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
  liters numeric(10,3) NOT NULL CHECK (liters >= 0),
  price_total_eur numeric(10,2) NOT NULL CHECK (price_total_eur >= 0),
  station text NULL,
  filled_at timestamptz NOT NULL DEFAULT now(),
  created_at timestamptz NOT NULL DEFAULT now(),
  is_full boolean NOT NULL DEFAULT true,
  skip_previous boolean NOT NULL DEFAULT false
);

CREATE INDEX IF NOT EXISTS idx_vehicles_user_created ON vehicles(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_fillups_user_vehicle_filled ON fillups(user_id, vehicle_id, filled_at DESC);
CREATE INDEX IF NOT EXISTS idx_fillups_vehicle_odo ON fillups(vehicle_id, odometer_km);
