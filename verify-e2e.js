const { spawn } = require('child_process');

const root = __dirname;
const children = [];

// php artisan serve (Laravel) on :8000
const php = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000'], {
  cwd: `${root}\\backend`,
  stdio: ['ignore', 'pipe', 'pipe'],
});
php.stderr.on('data', (d) => process.stderr.write('[laravel] ' + d));
children.push(php);

// Node WhatsApp service on :3001
const node = spawn(process.execPath, ['index.js'], {
  cwd: `${root}\\whatsapp`,
  stdio: ['ignore', 'pipe', 'pipe'],
});
node.stderr.on('data', (d) => process.stderr.write('[whatsapp] ' + d));
children.push(node);

function killAll(code) {
  for (const c of children) {
    try {
      // Windows: kill the whole tree (Node + orphaned Chromium) so the profile isn't locked
      require('child_process').execSync(`taskkill /pid ${c.pid} /T /F`, { stdio: 'ignore' });
    } catch {}
  }
  process.exit(code);
}

const BASE = 'http://127.0.0.1:8000/api';
const PROVIDER_TOKEN = 'crm-provider-secret-change-me';
let failures = 0;

function check(name, cond, extra) {
  if (!cond) {
    failures++;
    console.log('  FAIL:', name, extra || '');
  } else {
    console.log('  ok:', name);
  }
}

async function waitUp(url, tries = 40) {
  for (let i = 0; i < tries; i++) {
    try {
      const r = await fetch(url);
      if (r.ok) return true;
    } catch {}
    await new Promise((r) => setTimeout(r, 1500));
  }
  return false;
}

async function main() {
  console.log('Resetting database…');
  const fresh = spawn('php', ['artisan', 'migrate:fresh', '--force'], {
    cwd: `${root}\\backend`,
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  await new Promise((resolve) => fresh.on('exit', resolve));

  console.log('Waiting for Laravel…');
  if (!(await waitUp(`${BASE}/dashboard`))) {
    console.log('Laravel did not come up'); return killAll(1);
  }
  console.log('Waiting for WhatsApp service…');
  if (!(await waitUp('http://127.0.0.1:3001/health'))) {
    console.log('WhatsApp service did not come up'); return killAll(1);
  }

  // 1. dashboard
  let r = await fetch(`${BASE}/dashboard`);
  let j = await r.json();
  check('dashboard shape', r.ok && typeof j.totals === 'object' && Array.isArray(j.recent_campaigns));

  // 2. whatsapp status + QR generation (real Chromium boot through index.js)
  let qrSeen = false;
  for (let i = 0; i < 40; i++) {
    r = await fetch(`${BASE}/whatsapp/status`);
    j = await r.json();
    if (j.qr) { qrSeen = true; break; }
    await new Promise((r2) => setTimeout(r2, 2000));
  }
  check('whatsapp status + QR generated', r.ok && qrSeen, JSON.stringify(j).slice(0, 150));

  // 3. whatsapp connect
  r = await fetch(`${BASE}/whatsapp/connect`, { method: 'POST' });
  j = await r.json();
  check('whatsapp connect', r.ok && j.ok === true);

  // 4. create contact
  r = await fetch(`${BASE}/contacts`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Test User', number: '15551234567' }),
  });
  check('create contact', r.status === 201);

  // 5. contacts list
  r = await fetch(`${BASE}/contacts`);
  j = await r.json();
  check('contacts list', r.ok && j.data && j.data.length === 1);

  // 6. create campaign (multipart, CSV + manual recipients)
  const form = new FormData();
  form.append('name', 'E2E Campaign');
  form.append('message', 'Hi {{name}}, welcome! Your number is {{number}}.');
  form.append('recipients', JSON.stringify(['15559876543', '15551112222']));
  form.append('csrf', 'ignore');
  form.append('delay_min', '1');
  form.append('delay_max', '2');
  const csv = new Blob(['number,name\n15557778888,Bob\n15554443333,Alice'], { type: 'text/csv' });
  form.append('csv', csv, 'contacts.csv');
  form.append('media', new Blob(['fake'], { type: 'text/plain' }), 'note.txt');

  r = await fetch(`${BASE}/campaigns`, { method: 'POST', body: form });
  j = await r.json();
  check('create campaign (multipart+CSV)', r.status === 201 && j.total === 4, `${r.status} ${JSON.stringify(j).slice(0, 200)}`);

  const campaignId = j.id;

  // 7. campaign show
  r = await fetch(`${BASE}/campaigns/${campaignId}`);
  j = await r.json();
  check('campaign show with messages', r.ok && j.campaign.id === campaignId && j.messages.total === 4);
  check('campaign counts', r.ok && j.counts.pending === 4, JSON.stringify(j.counts));
  const firstItemId = j.messages.data[0]?.id;

  // 8. campaign start when WhatsApp not connected -> 409
  r = await fetch(`${BASE}/campaigns/${campaignId}/start`, { method: 'POST' });
  check('start returns 409 when not connected', r.status === 409, `${r.status} ${await r.text()}`);

  // 9. provider progress (auth) — wrong token
  r = await fetch(`${BASE}/provider/campaign-progress`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Provider-Token': 'wrong' },
    body: JSON.stringify({ campaign: campaignId, item_id: firstItemId, status: 'sent' }),
  });
  check('provider rejects bad token', r.status === 401);

  // 10. provider progress (auth) — correct token
  r = await fetch(`${BASE}/provider/campaign-progress`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Provider-Token': PROVIDER_TOKEN },
    body: JSON.stringify({ campaign: campaignId, item_id: firstItemId, status: 'sent', message_id: 'TESTID' }),
  });
  check('provider accepts good token', r.ok);

  // 11. provider done event
  r = await fetch(`${BASE}/provider/campaign-progress`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Provider-Token': PROVIDER_TOKEN },
    body: JSON.stringify({ campaign: campaignId, done: true, final_status: 'completed' }),
  });
  check('provider done event', r.ok);

  // 12. campaign state updated
  r = await fetch(`${BASE}/campaigns/${campaignId}`);
  j = await r.json();
  check('campaign completed after events', j.campaign.status === 'completed' && j.campaign.success === 1, JSON.stringify(j.campaign).slice(0, 160));

  // 13. dashboard totals reflect history
  r = await fetch(`${BASE}/dashboard`);
  j = await r.json();
  check('dashboard totals', j.totals.contacts === 5 && j.totals.sent === 1, JSON.stringify(j.totals));

  console.log(failures ? `\n${failures} FAILURES` : '\nALL E2E CHECKS PASSED');
  killAll(failures ? 1 : 0);
}

main().catch((e) => {
  console.error(e);
  killAll(1);
});