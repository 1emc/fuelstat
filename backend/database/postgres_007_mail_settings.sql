-- backend/database/postgres_007_mail_settings.sql
-- E-Mail-Benachrichtigungen: SMTP-Zugang je Benutzer und Versandprotokoll
BEGIN;

CREATE TABLE IF NOT EXISTS user_mail_settings (
  user_id uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,

  smtp_host text NULL,
  smtp_port integer NULL CHECK (smtp_port IN (25, 465, 587, 2525)),
  smtp_security text NOT NULL DEFAULT 'starttls' CHECK (smtp_security IN ('tls', 'starttls', 'none')),
  smtp_user text NULL,
  smtp_password_enc text NULL,          -- AES-256-GCM verschlüsselt, siehe backend/src/services/mail.js
  from_address text NULL,
  to_address text NULL,                 -- leer = E-Mail-Adresse des Kontos

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

COMMIT;
