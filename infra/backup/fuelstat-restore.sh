#!/usr/bin/env bash
# Wiederherstellung einer Fuelstat-Sicherung.
#
#   Test (empfohlen, regelmäßig): in eine eigene Testdatenbank einspielen, Zeilen zählen, Testdatenbank löschen.
#     sudo infra/backup/fuelstat-restore.sh --test /var/backups/fuelstat/daily/fuelstat-db-2026-11-03_031700.dump[.age]
#
#   Ernstfall: ersetzt die Daten der laufenden Datenbank (vorher API stoppen!)
#     sudo infra/backup/fuelstat-restore.sh --echt <db-dump> [<uploads.tar.gz>]
#
# Verschlüsselte Dateien (.age) brauchen den privaten Schlüssel: AGE_IDENTITY=/pfad/zum/schluessel.txt
set -euo pipefail

MODUS="${1:-}"; DUMP="${2:-}"; UPLOADS="${3:-}"
CONFIG="${BACKUP_ENV:-$(dirname "$0")/backup.env}"
[ -f "$CONFIG" ] && . "$CONFIG"
: "${COMPOSE_DIR:?COMPOSE_DIR (Ordner mit docker-compose.yml) fehlt}"
: "${UPLOADS_DIR:=$COMPOSE_DIR/../uploads}"

if [ "$MODUS" != "--test" ] && [ "$MODUS" != "--echt" ] || [ ! -f "$DUMP" ]; then
  sed -n '2,12p' "$0"; exit 2
fi

for f in "$DUMP" $UPLOADS; do
  case "$f" in *.age) [ -f "${AGE_IDENTITY:-}" ] || { echo "Für $f wird der private age-Schlüssel gebraucht: AGE_IDENTITY=/pfad/zum/schluessel.txt"; exit 2; } ;; esac
done

dc() { docker compose --project-directory "$COMPOSE_DIR" -f "$COMPOSE_DIR/docker-compose.yml" "$@"; }
klartext() {  # Datei ggf. entschlüsseln und auf stdout ausgeben
  case "$1" in
    *.age) : "${AGE_IDENTITY:?AGE_IDENTITY (privater Schlüssel) fehlt für $1}"; age -d -i "$AGE_IDENTITY" "$1" ;;
    *) cat "$1" ;;
  esac
}

if [ "$MODUS" = "--test" ]; then
  echo "Test-Wiederherstellung in Datenbank fuelstat_restore_test …"
  dc exec -T db sh -c 'dropdb -U "$POSTGRES_USER" --if-exists fuelstat_restore_test && createdb -U "$POSTGRES_USER" fuelstat_restore_test'
  klartext "$DUMP" | dc exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d fuelstat_restore_test --no-owner --exit-on-error'
  dc exec -T db sh -c 'psql -U "$POSTGRES_USER" -d fuelstat_restore_test -At -F " " -c "
    SELECT '"'"'Benutzer'"'"', count(*) FROM users UNION ALL
    SELECT '"'"'Fahrzeuge'"'"', count(*) FROM vehicles UNION ALL
    SELECT '"'"'Tank-/Ladevorgänge'"'"', count(*) FROM fillups UNION ALL
    SELECT '"'"'Ausgaben'"'"', count(*) FROM expenses"'
  dc exec -T db sh -c 'dropdb -U "$POSTGRES_USER" fuelstat_restore_test'
  if [ -n "$UPLOADS" ]; then
    echo "Uploads-Archiv: $(klartext "$UPLOADS" | tar -tzf - | grep -vc '/$') Datei(en) lesbar"
  fi
  echo "Test erfolgreich."
  exit 0
fi

read -r -p "Die laufende Datenbank wird mit der Sicherung überschrieben. Wirklich fortfahren? (ja/nein) " ok
[ "$ok" = "ja" ] || { echo "Abgebrochen."; exit 1; }
klartext "$DUMP" | dc exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --exit-on-error'
if [ -n "$UPLOADS" ]; then
  klartext "$UPLOADS" | tar -xzf - -C "$(dirname "$UPLOADS_DIR")"
  chown -R 1000:1000 "$UPLOADS_DIR"
fi
echo "Wiederhergestellt. API neu starten: (cd $COMPOSE_DIR && docker compose up -d)"
