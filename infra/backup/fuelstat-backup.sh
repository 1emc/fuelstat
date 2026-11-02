#!/usr/bin/env bash
# Datensicherung für Fuelstat: Datenbank (pg_dump) und hochgeladene Bilder.
#
#   sudo infra/backup/fuelstat-backup.sh [/pfad/zur/backup.env]
#
# Einstellungen in backup.env (Vorlage: backup.env.example). Ablauf:
#   1. Datenbank als pg_dump (Custom-Format) aus dem db-Container, Uploads als tar.gz
#   2. optional verschlüsselt mit age (öffentlicher Schlüssel auf dem Server, privater Schlüssel offline)
#   3. Aufbewahrung: täglich KEEP_DAILY Tage, sonntags zusätzlich eine Wochensicherung für KEEP_WEEKLY Wochen
#   4. optional Kopie nach extern per rsync (z. B. Hetzner Storage Box)
# Bei einem Fehler endet das Skript mit Exit-Code ≠ 0 (systemd meldet den Fehlschlag).
set -euo pipefail
umask 077

CONFIG="${1:-$(dirname "$0")/backup.env}"
[ -f "$CONFIG" ] && . "$CONFIG"

: "${COMPOSE_DIR:?COMPOSE_DIR (Ordner mit docker-compose.yml) fehlt}"
: "${BACKUP_DIR:=/var/backups/fuelstat}"
: "${UPLOADS_DIR:=$COMPOSE_DIR/../uploads}"
: "${KEEP_DAILY:=14}"
: "${KEEP_WEEKLY:=12}"
: "${BACKUP_AGE_RECIPIENT:=}"
: "${BACKUP_REMOTE:=}"
: "${BACKUP_REMOTE_SSH:=ssh}"

log() { echo "[$(date '+%F %T')] $*"; }

STAMP="$(date +%Y-%m-%d_%H%M%S)"
TMP="$(mktemp -d "${BACKUP_DIR%/}/.tmp.XXXXXX" 2>/dev/null || { mkdir -p "$BACKUP_DIR"; mktemp -d "${BACKUP_DIR%/}/.tmp.XXXXXX"; })"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly"

log "Datenbank sichern"
docker compose --project-directory "$COMPOSE_DIR" -f "$COMPOSE_DIR/docker-compose.yml" exec -T db \
  sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc --no-owner' > "$TMP/fuelstat-db-$STAMP.dump"
# Plausibilität: ein leerer oder winziger Dump ist ein Fehler
[ "$(stat -c %s "$TMP/fuelstat-db-$STAMP.dump")" -gt 1024 ] || { log "FEHLER: Datenbank-Dump ist leer"; exit 1; }

if [ -d "$UPLOADS_DIR" ]; then
  log "Uploads sichern ($UPLOADS_DIR)"
  tar -czf "$TMP/fuelstat-uploads-$STAMP.tar.gz" -C "$(dirname "$UPLOADS_DIR")" "$(basename "$UPLOADS_DIR")"
else
  log "Hinweis: Upload-Ordner $UPLOADS_DIR fehlt, nur Datenbank gesichert"
fi

if [ -n "$BACKUP_AGE_RECIPIENT" ]; then
  log "Verschlüsseln (age)"
  for f in "$TMP"/fuelstat-*; do
    age -r "$BACKUP_AGE_RECIPIENT" -o "$f.age" "$f"
    rm -f "$f"
  done
fi

(cd "$TMP" && sha256sum fuelstat-* > "fuelstat-$STAMP.sha256")
mv "$TMP"/fuelstat-* "$BACKUP_DIR/daily/"

# Sonntags zusätzlich eine Wochensicherung (harte Links sparen Platz)
if [ "$(date +%u)" = "7" ]; then
  for f in "$BACKUP_DIR/daily/"fuelstat-*"$STAMP"*; do ln -f "$f" "$BACKUP_DIR/weekly/"; done
fi

# Aufbewahrung: je Sicherungslauf (Zeitstempel) die neuesten behalten
aufraeumen() {
  local dir="$1" behalten="$2"
  ls -1 "$dir" | sed -n 's/^fuelstat-\([0-9_-]*\)\.sha256$/\1/p' | sort -r | tail -n +"$((behalten + 1))" | while read -r alt; do
    rm -f "$dir"/fuelstat-*"$alt"*
  done
}
aufraeumen "$BACKUP_DIR/daily" "$KEEP_DAILY"
aufraeumen "$BACKUP_DIR/weekly" "$KEEP_WEEKLY"

if [ -n "$BACKUP_REMOTE" ]; then
  log "Kopie nach $BACKUP_REMOTE"
  rsync -a --delete -e "$BACKUP_REMOTE_SSH" "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly" "$BACKUP_REMOTE"
fi

log "Fertig: $(ls -1 "$BACKUP_DIR/daily" | grep -c "$STAMP") Datei(en), $(du -sh "$BACKUP_DIR" | cut -f1) gesamt"
