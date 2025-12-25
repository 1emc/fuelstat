-- backend/database/postgres_005_users_vehicles_details_and_images.sql
BEGIN;

-- 1) USERS: Username, MFA, Profilbild (optional)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS username text NULL,
  ADD COLUMN IF NOT EXISTS mfa_secret text NULL,
  ADD COLUMN IF NOT EXISTS mfa_enabled boolean NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS profile_image_url text NULL,
  ADD COLUMN IF NOT EXISTS updated_at timestamptz NOT NULL DEFAULT now();

-- Optional: username Unique pro System (oder weglassen, falls du es nicht brauchst)
-- Hinweis: UNIQUE auf nullable erlaubt mehrere NULL Werte.
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'users_username_key'
  ) THEN
    ALTER TABLE users ADD CONSTRAINT users_username_key UNIQUE (username);
  END IF;
END$$;

-- 2) VEHICLES: Details aus MariaDB plus Bild
ALTER TABLE vehicles
  ADD COLUMN IF NOT EXISTS brand text NULL,
  ADD COLUMN IF NOT EXISTS model text NULL,
  ADD COLUMN IF NOT EXISTS year smallint NULL,
  ADD COLUMN IF NOT EXISTS image_url text NULL,
  ADD COLUMN IF NOT EXISTS odometer_km integer NULL,
  ADD COLUMN IF NOT EXISTS mileage_km integer NULL,          -- "fahrleistung" als gespeicherter Snapshot, optional
  ADD COLUMN IF NOT EXISTS tank_capacity numeric(10,3) NULL, -- z.B. 55.000 l oder kwh
  ADD COLUMN IF NOT EXISTS sort_order integer NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS updated_at timestamptz NOT NULL DEFAULT now();

-- year plausibilisieren (optional, aber nett)
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'vehicles_year_check'
  ) THEN
    ALTER TABLE vehicles
      ADD CONSTRAINT vehicles_year_check
      CHECK (year IS NULL OR (year >= 1886 AND year <= 2100));
  END IF;
END$$;

-- fuel_type absichern gegen Wildwuchs (analog MariaDB enum)
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'vehicles_fuel_type_check'
  ) THEN
    ALTER TABLE vehicles
      ADD CONSTRAINT vehicles_fuel_type_check
      CHECK (fuel_type = ANY (ARRAY[
        'diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other'
      ]));
  END IF;
END$$;

-- Indizes für typische Queries (Sortierung, Filter, Detailseiten)
CREATE INDEX IF NOT EXISTS idx_vehicles_user_sort
  ON vehicles (user_id, sort_order ASC, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_vehicles_user_brand_model
  ON vehicles (user_id, brand, model);

-- 3) Option: Wenn du "ein Bild pro Fahrzeug" nicht genug findest, dann lieber Media-Tabelle.
-- Ich lege sie hier an, aber du kannst sie auch weglassen und nur vehicles.image_url nutzen.
CREATE TABLE IF NOT EXISTS vehicle_media (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  vehicle_id uuid NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  media_type text NOT NULL DEFAULT 'image',
  url text NOT NULL,
  mime_type text NULL,
  bytes integer NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_vehicle_media_vehicle_created
  ON vehicle_media (vehicle_id, created_at DESC);

-- 4) Optional: Gleiches Konzept für User Media, falls Profilbilder versioniert werden sollen
CREATE TABLE IF NOT EXISTS user_media (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  media_type text NOT NULL DEFAULT 'image',
  url text NOT NULL,
  mime_type text NULL,
  bytes integer NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_user_media_user_created
  ON user_media (user_id, created_at DESC);

COMMIT;

