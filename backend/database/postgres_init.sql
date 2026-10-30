-- backend/database/postgres_init.sql
-- Vollständiges Schema für eine NEUE Installation (Stand: Migration 010).
-- Beim ersten Start der Datenbank spielt Docker diese Datei automatisch ein (siehe infra/docker-compose.yml).
-- Bestehende Installationen nutzen stattdessen die Migrationen postgres_0XX_*.sql.
BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Benutzer
CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email text NOT NULL UNIQUE,
  password_hash text NOT NULL,             -- bcrypt
  username text NULL UNIQUE,
  mfa_secret text NULL,                    -- TOTP-Secret, nur bei aktiver 2FA genutzt
  mfa_enabled boolean NOT NULL DEFAULT false,
  profile_image_url text NULL,
  beta_tester boolean NOT NULL DEFAULT false,
  last_login_at timestamptz NULL,
  last_seen_at timestamptz NULL,           -- höchstens stündlich aktualisiert
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

-- Fahrzeuge
CREATE TABLE IF NOT EXISTS vehicles (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name text NOT NULL,
  -- hybrid = Vollhybrid (tankt nur), phev = Plug-in-Hybrid (tankt und lädt)
  fuel_type text NOT NULL CHECK (fuel_type = ANY (ARRAY['diesel','e5','e10','lpg','cng','electric','hybrid','phev','hydrogen','other'])),
  brand text NULL,
  model text NULL,
  year smallint NULL CHECK (year IS NULL OR (year >= 1886 AND year <= 2100)),
  image_url text NULL,
  odometer_km integer NULL,                -- Tachostand bei Anschaffung
  mileage_km integer NULL,
  tank_capacity numeric(10,3) NULL,        -- l bzw. kg, bei Elektroautos kWh
  battery_capacity numeric(10,3) NULL,     -- kWh, nur Plug-in-Hybride
  sort_order integer NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_vehicles_user_created ON vehicles(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_vehicles_user_sort ON vehicles(user_id, sort_order ASC, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_vehicles_user_brand_model ON vehicles(user_id, brand, model);

-- Tankfüllungen und Ladevorgänge; die Einheit gilt je Eintrag (Plug-in-Hybrid: l beim Tanken, kWh beim Laden)
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
  is_full boolean NOT NULL DEFAULT true,
  skip_previous boolean NOT NULL DEFAULT false, -- Lücke davor: Vollbetankung ist nur neuer Startpunkt
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_fillups_user_vehicle_filled ON fillups(user_id, vehicle_id, filled_at DESC);
CREATE INDEX IF NOT EXISTS idx_fillups_vehicle_odo ON fillups(vehicle_id, odometer_km);

-- Andere Ausgaben (Versicherung, Werkstatt, TÜV …) und Fahrten
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

CREATE INDEX IF NOT EXISTS idx_expenses_user_vehicle_date ON expenses(user_id, vehicle_id, occurred_at DESC);
CREATE INDEX IF NOT EXISTS idx_expenses_vehicle_odo ON expenses(vehicle_id, odometer_km);

-- Hochgeladene Bilder (Profil und Fahrzeuge)
CREATE TABLE IF NOT EXISTS user_media (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  media_type text NOT NULL DEFAULT 'image',
  url text NOT NULL,
  mime_type text NULL,
  bytes integer NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_user_media_user_created ON user_media(user_id, created_at DESC);

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

CREATE INDEX IF NOT EXISTS idx_vehicle_media_vehicle_created ON vehicle_media(vehicle_id, created_at DESC);

-- Abgemeldete Einzel-Tokens (JWT jti)
CREATE TABLE IF NOT EXISTS revoked_tokens (
  jti text PRIMARY KEY,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  revoked_at timestamptz NOT NULL DEFAULT now(),
  reason text NULL
);

CREATE INDEX IF NOT EXISTS idx_revoked_tokens_user ON revoked_tokens(user_id, revoked_at DESC);

-- "Auf allen Geräten abmelden": alle Tokens vor revoked_after sind ungültig
CREATE TABLE IF NOT EXISTS user_token_revocations (
  user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  revoked_after timestamptz NOT NULL DEFAULT now(),
  reason text NULL
);

-- E-Mail-Benachrichtigungen: eigener SMTP-Zugang (optional) und Einstellungen je Benutzer
CREATE TABLE IF NOT EXISTS user_mail_settings (
  user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  smtp_host text NULL,
  smtp_port integer NULL CHECK (smtp_port IN (25, 465, 587, 2525)),
  smtp_security text NOT NULL DEFAULT 'starttls' CHECK (smtp_security IN ('tls', 'starttls', 'none')),
  smtp_user text NULL,
  smtp_password_enc text NULL,             -- AES-256-GCM verschlüsselt, siehe backend/src/services/mail.js
  from_address text NULL,
  to_address text NULL,                    -- leer = E-Mail-Adresse des Kontos
  reminders_enabled boolean NOT NULL DEFAULT false,
  reminder_days integer NOT NULL DEFAULT 30 CHECK (reminder_days BETWEEN 1 AND 180),
  monthly_report boolean NOT NULL DEFAULT false,
  last_sent_at timestamptz NULL,
  last_error text NULL,
  updated_at timestamptz NOT NULL DEFAULT now()
);

-- Jede Erinnerung bzw. jeder Monatsbericht wird nur einmal verschickt
CREATE TABLE IF NOT EXISTS mail_log (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  kind text NOT NULL CHECK (kind IN ('reminder', 'report')),
  ref text NOT NULL,
  sent_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, kind, ref)
);

-- Feedback der Benutzer (bleibt beim Löschen des Kontos erhalten)
CREATE TABLE IF NOT EXISTS feedback (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NULL REFERENCES users(id) ON DELETE SET NULL,
  user_email text NULL,
  category text NOT NULL CHECK (category IN ('fehler', 'idee', 'lob', 'sonstiges')),
  message text NOT NULL,
  page text NULL,
  user_agent text NULL,
  done boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_feedback_created ON feedback(created_at DESC);

COMMIT;
