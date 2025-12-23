-- backend/database/postgres_002_expenses_and_fillups.sql
BEGIN;

-- 1) Expenses (allgemeine Ausgaben pro Fahrzeug)
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

COMMIT;

