// backend/src/services/mail.js
// SMTP-Versand mit den Zugangsdaten des jeweiligen Benutzers.
const crypto = require('crypto');
const dns = require('dns').promises;
const net = require('net');
const nodemailer = require('nodemailer');

const ALLOWED_PORTS = [25, 465, 587, 2525];

// Schlüssel für das SMTP-Passwort: MAIL_ENCRYPTION_KEY, sonst aus JWT_SECRET abgeleitet
function encryptionKey() {
  const source = process.env.MAIL_ENCRYPTION_KEY || `fuelstat-mail:${process.env.JWT_SECRET}`;
  return crypto.createHash('sha256').update(source).digest();
}

function encryptSecret(plain) {
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', encryptionKey(), iv);
  const data = Buffer.concat([cipher.update(String(plain), 'utf8'), cipher.final()]);
  return 'v1:' + Buffer.concat([iv, cipher.getAuthTag(), data]).toString('base64');
}

function decryptSecret(enc) {
  if (!enc) return null;
  const raw = Buffer.from(String(enc).replace(/^v1:/, ''), 'base64');
  const decipher = crypto.createDecipheriv('aes-256-gcm', encryptionKey(), raw.subarray(0, 12));
  decipher.setAuthTag(raw.subarray(12, 28));
  return Buffer.concat([decipher.update(raw.subarray(28)), decipher.final()]).toString('utf8');
}

function isPrivateAddress(ip) {
  if (net.isIPv4(ip)) {
    const [a, b] = ip.split('.').map(Number);
    return a === 10 || a === 127 || a === 0 || (a === 169 && b === 254) ||
      (a === 172 && b >= 16 && b <= 31) || (a === 192 && b === 168) || (a === 100 && b >= 64 && b <= 127) || a >= 224;
  }
  const lower = ip.toLowerCase();
  if (lower.startsWith('::ffff:')) return isPrivateAddress(lower.slice(7));
  return lower === '::' || lower === '::1' || lower.startsWith('fc') || lower.startsWith('fd') || lower.startsWith('fe80');
}

/**
 * SMTP-Host auflösen und prüfen. Interne Adressen sind gesperrt (Schutz vor SSRF),
 * außer ALLOW_PRIVATE_SMTP=true (z. B. eigener Mailserver im selben Netz).
 * @returns aufgelöste IP-Adresse
 */
async function resolveSmtpHost(host) {
  const { address } = await dns.lookup(host);
  if (isPrivateAddress(address) && process.env.ALLOW_PRIVATE_SMTP !== 'true') {
    const err = new Error('smtp_host_not_allowed');
    err.code = 'smtp_host_not_allowed';
    throw err;
  }
  return address;
}

async function createTransport(settings) {
  if (!settings?.smtp_host || !settings?.smtp_port) {
    const err = new Error('mail_not_configured');
    err.code = 'mail_not_configured';
    throw err;
  }
  // Mit der geprüften IP verbinden, damit eine zweite DNS-Auflösung nicht auf eine andere Adresse zeigen kann
  const ip = await resolveSmtpHost(settings.smtp_host);
  const password = decryptSecret(settings.smtp_password_enc);
  return nodemailer.createTransport({
    host: ip,
    port: settings.smtp_port,
    secure: settings.smtp_security === 'tls',
    requireTLS: settings.smtp_security === 'starttls',
    ignoreTLS: settings.smtp_security === 'none',
    auth: settings.smtp_user ? { user: settings.smtp_user, pass: password || '' } : undefined,
    tls: { servername: settings.smtp_host },
    name: 'fuelstat',
    connectionTimeout: 15000,
    greetingTimeout: 15000,
    socketTimeout: 30000
  });
}

/**
 * Mail mit den Einstellungen des Benutzers senden.
 * @param settings Zeile aus user_mail_settings (+ account_email)
 */
async function sendUserMail(settings, { subject, text, html }) {
  const transport = await createTransport(settings);
  try {
    const from = settings.from_address || settings.smtp_user || settings.account_email;
    const to = settings.to_address || settings.account_email;
    return await transport.sendMail({ from: `Fuelstat <${from}>`, to, subject, text, html });
  } finally {
    transport.close();
  }
}

module.exports = { encryptSecret, decryptSecret, sendUserMail, resolveSmtpHost, isPrivateAddress, ALLOWED_PORTS };
