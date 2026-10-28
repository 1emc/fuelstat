-- backend/database/postgres_006_fix_fillup_units.sql
-- Das Frontend hat bis Oktober 2026 jede Tankfüllung mit unit='l' angelegt,
-- auch bei Elektro- und Gasfahrzeugen. Einheit aus dem Kraftstoff des Fahrzeugs korrigieren.
BEGIN;

UPDATE fillups f
SET unit = CASE v.fuel_type
             WHEN 'electric' THEN 'kwh'
             WHEN 'cng' THEN 'kg'
             WHEN 'hydrogen' THEN 'kg'
             ELSE 'l'
           END
FROM vehicles v
WHERE f.vehicle_id = v.id
  AND f.unit IS DISTINCT FROM CASE v.fuel_type
                                WHEN 'electric' THEN 'kwh'
                                WHEN 'cng' THEN 'kg'
                                WHEN 'hydrogen' THEN 'kg'
                                ELSE 'l'
                              END;

COMMIT;
