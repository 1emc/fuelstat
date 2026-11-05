#!/usr/bin/env node
// Vorhandene Bilder einmalig umstellen (#103): verkleinern, als WebP ohne Metadaten (GPS) speichern,
// Verweise in der Datenbank anpassen und das Original löschen.
//
// Aufruf im API-Container (liest DATABASE_URL und UPLOAD_DIR wie die API):
//   docker compose exec api node scripts/bilder-optimieren.js --probe   # nur anzeigen, nichts ändern
//   docker compose exec api node scripts/bilder-optimieren.js           # umstellen
// Lässt sich gefahrlos wiederholen: bereits umgestellte Bilder (.webp) werden übersprungen.
const path = require('path');
const fs = require('fs');
const { Pool } = require('pg');
const media = require('../src/services/media');

const probe = process.argv.includes('--probe');
const BACKEND_ROOT = path.resolve(__dirname, '..');
const UPLOAD_DIR = process.env.UPLOAD_DIR
  ? (path.isAbsolute(process.env.UPLOAD_DIR) ? process.env.UPLOAD_DIR : path.resolve(BACKEND_ROOT, process.env.UPLOAD_DIR))
  : path.resolve(BACKEND_ROOT, 'uploads');
const BASE = media.MEDIA_BASE_URL;
const ALT = /^[0-9a-f-]{36}\.(jpg|png|gif)$/;

// Alle Stellen, an denen eine Bildadresse steht; art bestimmt die maximale Größe
const QUELLEN = [
  { tabelle: 'vehicle_media', spalte: 'url', art: 'vehicle', groesse: true },
  { tabelle: 'vehicles', spalte: 'image_url', art: 'vehicle' },
  { tabelle: 'user_media', spalte: 'url', art: 'user', groesse: true },
  { tabelle: 'users', spalte: 'profile_image_url', art: 'user' }
];

async function main() {
  if (!process.env.DATABASE_URL) throw new Error('DATABASE_URL fehlt');
  const pool = new Pool({ connectionString: process.env.DATABASE_URL });
  const bilder = new Map(); // Dateiname -> art (Fahrzeug gewinnt, größere Kante)
  for (const q of QUELLEN) {
    const r = await pool.query(`SELECT DISTINCT ${q.spalte} AS url FROM ${q.tabelle} WHERE ${q.spalte} LIKE $1`, [BASE + '/%']);
    for (const { url } of r.rows) {
      const name = url.slice(BASE.length + 1);
      if (!ALT.test(name)) continue;
      if (!bilder.has(name) || q.art === 'vehicle') bilder.set(name, q.art);
    }
  }

  let vorher = 0, nachher = 0, fertig = 0, fehlend = 0, fehler = 0;
  for (const [name, art] of bilder) {
    const quelle = path.join(UPLOAD_DIR, name);
    if (!(await media.exists(quelle))) { fehlend++; console.log(`fehlt: ${name}`); continue; }
    const neu = name.replace(/\.(jpg|png|gif)$/, '.webp');
    const ziel = path.join(UPLOAD_DIR, neu);
    const alt = (await fs.promises.stat(quelle)).size;
    try {
      if (probe) {
        const tmp = path.join(UPLOAD_DIR, `.probe-${neu}`);
        const info = await media.optimizeImage(quelle, tmp, art);
        await fs.promises.unlink(tmp).catch(() => {});
        vorher += alt; nachher += info.bytes;
        console.log(`${name}: ${(alt / 1024).toFixed(0)} KB -> ${(info.bytes / 1024).toFixed(0)} KB (${info.width}×${info.height})`);
        continue;
      }
      const info = await media.optimizeImage(quelle, ziel, art);
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        for (const q of QUELLEN) {
          const extra = q.groesse ? `, mime_type = 'image/webp', bytes = $3` : '';
          await client.query(`UPDATE ${q.tabelle} SET ${q.spalte} = $2${extra} WHERE ${q.spalte} = $1`,
            q.groesse ? [`${BASE}/${name}`, `${BASE}/${neu}`, info.bytes] : [`${BASE}/${name}`, `${BASE}/${neu}`]);
        }
        await client.query('COMMIT');
      } catch (e) {
        await client.query('ROLLBACK').catch(() => {});
        await fs.promises.unlink(ziel).catch(() => {});
        throw e;
      } finally {
        client.release();
      }
      await fs.promises.unlink(quelle).catch(() => {});
      vorher += alt; nachher += info.bytes; fertig++;
      console.log(`${name} -> ${neu}: ${(alt / 1024).toFixed(0)} KB -> ${(info.bytes / 1024).toFixed(0)} KB`);
    } catch (e) {
      fehler++;
      console.error(`Fehler bei ${name}: ${e.message}`);
    }
  }
  await pool.end();
  console.log(`\n${probe ? 'Probelauf' : 'Fertig'}: ${bilder.size} Bilder, ${probe ? bilder.size - fehlend - fehler : fertig} umgestellt, ${fehlend} fehlen, ${fehler} Fehler.`);
  if (vorher > 0) console.log(`Größe: ${(vorher / 1048576).toFixed(1)} MB -> ${(nachher / 1048576).toFixed(1)} MB`);
}

main().catch((e) => { console.error(e.message); process.exit(1); });
