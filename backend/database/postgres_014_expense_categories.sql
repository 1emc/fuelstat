-- backend/database/postgres_014_expense_categories.sql
-- Eigene Kategorien für Ausgaben (#95, nur mit Tarif-Recht customCategories).
-- Die festen Kategorien stehen im Code (backend/src/services/categories.js) und brauchen keine Tabelle.
-- Ausgaben mit eigener Kategorie speichern in expenses.category den Schlüssel "custom:<id>".
BEGIN;

CREATE TABLE IF NOT EXISTS expense_categories (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name text NOT NULL CHECK (length(name) BETWEEN 1 AND 40),
  icon text NOT NULL DEFAULT 'fa-tag',
  color text NOT NULL DEFAULT '#6c757d',
  group_key text NOT NULL DEFAULT 'sonstiges',
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

-- Jeder Name nur einmal pro Benutzer (ohne Groß-/Kleinschreibung)
CREATE UNIQUE INDEX IF NOT EXISTS idx_expense_categories_user_name ON expense_categories(user_id, lower(name));
-- Ausgaben je Kategorie schnell finden (Löschen/Verschieben, Fälligkeiten)
CREATE INDEX IF NOT EXISTS idx_expenses_user_category ON expenses(user_id, category);

COMMIT;
