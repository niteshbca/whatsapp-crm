const { spawn } = require('child_process');

const child = spawn(process.execPath, ['index.js'], { cwd: __dirname, stdio: ['ignore', 'pipe', 'pipe'] });

let out = '';
let err = '';
child.stdout.on('data', (d) => { out += d; });
child.stderr.on('data', (d) => { err += d; });

const startedAt = Date.now();
const timeout = 120000;

async function check() {
  if (Date.now() - startedAt > timeout) {
    console.log('TIMEOUT waiting for QR');
    console.log('STDOUT:', out);
    console.log('STDERR:', err);
    child.kill();
    process.exit(1);
  }

  try {
    const res = await fetch('http://127.0.0.1:3001/api/status');
    const json = await res.json();
    console.log('STATUS:', JSON.stringify(json, (k, v) => k === 'qr' ? (v ? v.slice(0, 60) + '...(len ' + v.length + ')' : null) : v));
    if (json.qr || json.connected || json.status === 'connecting' || json.status === 'error') {
      console.log('STDOUT:', out);
      console.log('STDERR:', err);
      child.kill();
      process.exit(0);
    }
    setTimeout(check, 3000);
  } catch (e) {
    setTimeout(check, 3000);
  }
}

setTimeout(check, 15000);