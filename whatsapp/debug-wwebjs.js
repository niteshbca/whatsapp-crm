const path = require('path');
const fs = require('fs');
const { Client, LocalAuth } = require('whatsapp-web.js');

const SESSION_DIR = path.join(__dirname, '.wwebjs_auth');
fs.rmSync(SESSION_DIR, { recursive: true, force: true });

const client = new Client({
  authStrategy: new LocalAuth({ clientId: 'whatsapp-crm', dataPath: SESSION_DIR }),
  puppeteer: {
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  },
});

const t = setTimeout(() => { console.log('TIMEOUT (no ready)'); process.exit(1); }, 90000);

client.on('qr', (qr) => {
  console.log('QR_OK len', String(qr).length);
  clearTimeout(t);
  // close
  client.destroy().then(() => process.exit(0));
});
client.on('ready', () => { console.log('READY'); clearTimeout(t); process.exit(0); });
client.on('auth_failure', (m) => { console.log('AUTH_FAIL', m); });

client.initialize().catch((err) => {
  console.log('INIT_ERROR:', err && err.message);
  // puppeteer attaches logs to err as err.message usually includes them, but print stack
  if (err && err.stack) console.log(err.stack.split('\n').slice(0, 4).join('\n'));
  clearTimeout(t);
  process.exit(1);
});