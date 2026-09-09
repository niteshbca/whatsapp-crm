require('dotenv').config();

const express = require('express');
const cors = require('cors');
const QRCode = require('qrcode');
const path = require('path');
const fs = require('fs');
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');

const PORT = Number(process.env.PORT || 3001);
const LARAVEL_URL = String(process.env.LARAVEL_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const PROVIDER_TOKEN = String(process.env.PROVIDER_TOKEN || 'crm-provider-secret-change-me');
const SESSION_DIR = path.resolve(process.env.SESSION_DIR || path.join(__dirname, '.wwebjs_auth'));
const AUTH_CLIENT_ID = String(process.env.WA_CLIENT_ID || 'whatsapp-crm');

const DEFAULT_DELAY_MIN = Number(process.env.DELAY_MIN || 2) * 1000;
const DEFAULT_DELAY_MAX = Number(process.env.DELAY_MAX || 5) * 1000;

// Proxy: residential proxy se WhatsApp connect hota hai (Render pe zaroori)
// Format: socks5://user:pass@host:port  OR  http://host:port  OR  socks5://host:port
const PROXY_URL = process.env.PROXY_URL || '';
// Puppeteer executable: Docker/Render mein system chromium use hota hai
const PUPPETEER_EXE = process.env.PUPPETEER_EXECUTABLE_PATH || '';

// Parse proxy auth from URL (socks5://user:pass@host:port → { server, user, pass })
function parseProxy(raw) {
  if (!raw) return null;
  try {
    const url = new URL(raw);
    const server = `${url.protocol}//${url.hostname}:${url.port}`;
    return {
      server,
      user: url.username || '',
      pass: url.password || '',
    };
  } catch {
    return { server: raw, user: '', pass: '' };
  }
}
const PROXY = parseProxy(PROXY_URL);

const companyClients = new Map();
const companyStates = new Map();
const companyInitPromises = new Map();
const companyCleanupPromises = new Map();

// Rate limiting protection
const connectAttempts = new Map();
const RATE_LIMIT_WINDOW = 60000; // 1 minute
const MAX_CONNECT_ATTEMPTS = 3;
const RETRY_DELAY_BASE = 5000; // 5 seconds
const COOLDOWN_MS = 5 * 60 * 1000; // 5 minute cooldown after 429
const companyCooldowns = new Map(); // key -> timestamp when cooldown expires

function getStateKey(companyId) {
  return companyId ? String(companyId) : 'default';
}

function getCurrentState(companyId = null) {
  const key = getStateKey(companyId);
  if (!companyStates.has(key)) {
    companyStates.set(key, {
      status: 'unlinked',
      qr: null,
      phone: null,
      name: null,
      error: null,
      lastEvent: null,
      logoutRequested: false,
    });
  }
  return companyStates.get(key);
}

function getCurrentClient(companyId = null) {
  const key = getStateKey(companyId);
  return companyClients.get(key) || null;
}

function isCurrentClient(companyId, client) {
  return getCurrentClient(companyId) === client;
}

function getClientSessionDir(companyId = null) {
  const chosen = companyId ? `company-${companyId}` : 'shared';
  return path.resolve(SESSION_DIR, chosen);
}

// campaignId -> { recipients, index, status, template, mediaPath }
const campaigns = new Map();
// campaignId -> pending resume/stop resolver
const controls = new Map();

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const randBetween = (min, max) => Math.floor(min + Math.random() * (max - min));

function pad(number) {
  return number.replace(/\D/g, '');
}

function hasValidNumber(number) {
  const digits = pad(number);
  return digits.length >= 7 && digits.length <= 15;
}

async function notifyLaravel(payload) {
  try {
    await fetch(`${LARAVEL_URL}/api/provider/campaign-progress`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Provider-Token': PROVIDER_TOKEN,
      },
      body: JSON.stringify(payload),
    });
  } catch (err) {
    console.error('[notify-laravel]', err.message);
  }
}

/* ------------------------------------------------------------------ */
/* WhatsApp client lifecycle                                            */
/* ------------------------------------------------------------------ */

function canAttemptConnect(companyId) {
  const key = getStateKey(companyId);
  const now = Date.now();
  
  // Check if in cooldown (after a 429)
  const cooldownUntil = companyCooldowns.get(key) || 0;
  if (now < cooldownUntil) {
    const waitTime = Math.ceil((cooldownUntil - now) / 1000);
    return { allowed: false, waitTime, cooldown: true };
  }
  
  const attempts = connectAttempts.get(key) || [];
  
  // Remove old attempts outside the window
  const recentAttempts = attempts.filter(t => now - t < RATE_LIMIT_WINDOW);
  
  if (recentAttempts.length >= MAX_CONNECT_ATTEMPTS) {
    const oldestAttempt = recentAttempts[0];
    const waitTime = Math.ceil((RATE_LIMIT_WINDOW - (now - oldestAttempt)) / 1000);
    return { allowed: false, waitTime };
  }
  
  recentAttempts.push(now);
  connectAttempts.set(key, recentAttempts);
  return { allowed: true };
}

function createClient(companyId = null, sessionName = null) {
  const key = getStateKey(companyId);
  if (companyInitPromises.has(key)) {
    return companyInitPromises.get(key);
  }

  const resolvedSessionName = sessionName || `company-${companyId || 'default'}`;
  const resolvedSessionDir = getClientSessionDir(companyId);
  const state = getCurrentState(companyId);
  state.logoutRequested = false;
  state.status = 'connecting';

  // Use a real installed Chrome + matching user-agent to avoid WhatsApp's
  // automation detection (bundled Chromium headless is flagged → 429).
  // On Docker/Render, PUPPETEER_EXECUTABLE_PATH points to system chromium.
  const REAL_CHROME = PUPPETEER_EXE || [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
    process.env.LOCALAPPDATA + '/Google/Chrome/Application/chrome.exe',
    'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  ].find((p) => { try { return require('fs').existsSync(p); } catch { return false; } });

  const puppeteerArgs = [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-dev-shm-usage',
    '--disable-gpu',
    '--disable-software-rasterizer',
    '--disable-background-networking',
    '--disable-default-apps',
    '--disable-sync',
    '--no-zygote',
    '--js-flags=--max-old-space-size=512',
    '--disable-blink-features=AutomationControlled',
    '--disable-features=IsolateOrigins,site-per-process',
    '--window-size=1366,768',
    '--lang=en-US',
    '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36',
  ];

  // Residential proxy se connect karo (Render pe datacenter IP block hota hai)
  if (PROXY) {
    puppeteerArgs.push(`--proxy-server=${PROXY.server}`);
    console.log(`[proxy] Using proxy: ${PROXY.server}${PROXY.user ? ' (with auth)' : ''}`);
  }

  const client = new Client({
    authStrategy: new LocalAuth({
      clientId: `${AUTH_CLIENT_ID}-${resolvedSessionName}`,
      dataPath: resolvedSessionDir,
    }),
    puppeteer: {
      headless: true,
      executablePath: REAL_CHROME || undefined,
      args: puppeteerArgs,
      ignoreHTTPSErrors: true,
      defaultViewport: { width: 1366, height: 768 },
    },
  });


  const events = {
    qr: (qr) => {
      if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
      state.status = 'qr';
      state.error = null;
      // Reset QR timeout to keep it visible longer
      if (state.qrTimeout) clearTimeout(state.qrTimeout);
      QRCode.toDataURL(qr, { width: 280, margin: 1 })
        .then((url) => {
          if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
          state.qr = url;
          // QR expires after 20 seconds, keep it visible for user
          state.qrTimeout = setTimeout(() => {
            if (state.status === 'qr' && state.qr) {
              state.error = 'QR code expired. Click Connect WhatsApp again.';
              state.status = 'expired';
            }
          }, 30000); // 30 seconds
        })
        .catch((err) => {
          state.error = 'Failed to render QR: ' + err.message;
        });
    },
    loading_screen: () => {
      if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
      state.status = 'connecting';
      if (state.qrTimeout) clearTimeout(state.qrTimeout);
      state.qrTimeout = null;
    },
    authenticated: () => {
      if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
      state.status = 'authenticated';
    },
    auth_failure: (msg) => {
      if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
      const reasonText = typeof msg === 'string' ? msg : 'Authentication failed';
      state.status = 'auth_failure';
      state.error = reasonText;
      console.error('[auth_failure]', companyId, reasonText);
      disposeClient(companyId, client, state).catch(() => {});
    },
    ready: async () => {
      if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
      if (state.qrTimeout) clearTimeout(state.qrTimeout);
      if (client && client.pupPage) {
        try {
          const evalPromise = client.pupPage.evaluate(() => {
            if (navigator.webdriver !== undefined) {
              Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
            }
            if (window.navigator) {
              const nav = window.navigator;
              if (nav.permissions && nav.permissions.query) {
                const origQuery = nav.permissions.query.bind(nav.permissions);
                nav.permissions.query = (parameters) => {
                  if (parameters && parameters.name === 'notifications') {
                    return Promise.resolve({ state: Notification.permission });
                  }
                  return origQuery(parameters);
                };
              }
            }
            if (window.WWebJS && window.WWebJS.injectToFunction) {
              window.WWebJS.injectToFunction(
                { module: 'WAWebLid1X1MigrationGating', function: 'Lid1X1MigrationUtils.isLidMigrated' },
                () => false,
              );
              window.WWebJS.injectToFunction(
                { module: 'WAWebLid1X1MigrationGating', function: 'shouldHaveAccountLid' },
                () => false,
              );
            }
          });
          const timeoutPromise = new Promise((_, reject) =>
            setTimeout(() => reject(new Error('pupPage.evaluate timeout')), 10000)
          );
          await Promise.race([evalPromise, timeoutPromise]);
        } catch (e) {
          console.error('[lid-patch]', e.message);
        }
      }
      if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
      const info = (client && client.info) || {};
      state.status = 'ready';
      state.qr = null;
      state.error = null;
      state.phone = info.wid?.user || info.me?.user || null;
      state.name = info.pushname || null;
      console.log('[ready] WhatsApp connected as', state.phone, 'for company', companyId);
    },
    disconnected: (reason) => {
      if (state.logoutRequested || !isCurrentClient(companyId, client)) return;
      const reasonText = typeof reason === 'string' ? reason : 'Disconnected';
      abortAllCampaigns('WhatsApp disconnected');
      state.status = 'disconnected';
      state.phone = null;
      state.error = reasonText === 'LOGOUT'
        ? null
        : `WhatsApp disconnected: ${reasonText}. Click Connect WhatsApp again to relink.`;
      console.warn('[disconnected]', companyId, reason);
      disposeClient(companyId, client, state).catch(() => {});
    },
  };

  client.on('qr', events.qr);
  client.on('loading_screen', events.loading_screen);
  client.on('authenticated', events.authenticated);
  client.on('auth_failure', events.auth_failure);
  client.on('ready', events.ready);
  client.on('disconnected', events.disconnected);

  companyClients.set(key, client);
  const cleanup = companyCleanupPromises.get(key) || Promise.resolve();
  companyInitPromises.set(key, cleanup.then(() => client.initialize()).catch(async (err) => {
    console.error('[initialize]', companyId, err.message);
    if (state.logoutRequested) return;
    
    // Handle 429 rate limit error - set long cooldown
    const errMsg = String(err.message || err);
    if (errMsg.includes('429')) {
      state.status = 'rate_limited';
      state.error = 'WhatsApp has rate limited this account. Please wait 5 minutes before trying again.';
      companyCooldowns.set(key, Date.now() + COOLDOWN_MS);
      // Reset attempt counter so user can retry after cooldown
      connectAttempts.delete(key);
      console.error('[rate-limit] Company', companyId, ': 429 received. Cooldown set for 5 minutes.');
    } else {
      state.status = 'error';
      state.error = errMsg;
    }
    
    if (isCurrentClient(companyId, client)) {
      companyClients.delete(key);
      companyInitPromises.delete(key);
    }
    try { await client.destroy(); } catch {}
  }));

  return companyInitPromises.get(key);
}

function isConnected(companyId = null) {
  const state = getCurrentState(companyId);
  return Boolean(getCurrentClient(companyId)) && state.status === 'ready';
}

async function disposeClient(companyId, client, state, opts = {}) {
  const key = getStateKey(companyId);
  const current = getCurrentClient(companyId);
  if (client && current === client) {
    companyClients.delete(key);
    companyInitPromises.delete(key);
  }
  if (state && state.qrTimeout) clearTimeout(state.qrTimeout);
  if (client) {
    try { client.removeAllListeners(); } catch {}
    try { await client.destroy(); } catch {}
  }
  if (state && opts.reset !== false) {
    state.logoutRequested = false;
    state.qr = null;
    state.phone = null;
    state.name = null;
  }
}

const TERMINAL_STATUSES = new Set(['error', 'auth_failure', 'disconnected', 'unlinked', 'expired', 'rate_limited']);

const connectLocks = new Map();

async function safelyReplaceClient(companyId, sessionName) {
  const key = getStateKey(companyId);
  
  // Check rate limiting
  const rateCheck = canAttemptConnect(companyId);
  if (!rateCheck.allowed) {
    const msg = rateCheck.cooldown
      ? `WhatsApp is cooling down after rate limit. Wait ${rateCheck.waitTime} seconds.`
      : `Too many connection attempts. Please wait ${rateCheck.waitTime} seconds before trying again.`;
    console.warn(`[rate-limit] Company ${companyId}: ${msg}`);
    return { 
      ok: false, 
      error: msg,
      waitTime: rateCheck.waitTime
    };
  }
  
  const previous = connectLocks.get(key) || Promise.resolve();
  const current = previous.then(async () => {
    const existing = getCurrentClient(companyId);
    if (existing) {
      await disposeClient(companyId, existing, getCurrentState(companyId));
    }
    // Add delay between attempts to avoid 429
    await sleep(3000);
    // Kick off a fresh client without awaiting the (slow) browser init, so
    // /api/connect returns immediately and the frontend polls for the QR.
    createClient(companyId, sessionName);
  });
  connectLocks.set(key, current.catch(() => {}));
  return current;
}

/* ------------------------------------------------------------------ */
/* Campaign worker                                                      */
/* ------------------------------------------------------------------ */

function renderTemplate(template, item) {
  const name = item.name || '';
  const number = pad(item.number);
  return String(template)
    .replace(/\{\{\s*name\s*\}\}/gi, name)
    .replace(/\{\{\s*number\s*\}\}/gi, number);
}

function setCampaignStatus(campaignId, status) {
  const run = campaigns.get(campaignId);
  if (!run) return;

  run.status = status;

  if (status === 'running' || status === 'stopped') {
    const resolve = controls.get(campaignId);
    if (resolve) {
      controls.delete(campaignId);
      resolve();
    }
  }
}

function waitForStatus(run) {
  if (run.status !== 'running') {
    return new Promise((resolve) => {
      if (!controls.has(run.campaignId)) {
        controls.set(run.campaignId, resolve);
      }
    });
  }
  return Promise.resolve();
}

async function runCampaign(run) {
  const recipients = run.recipients;

  for (run.index = 0; run.index < recipients.length; run.index++) {
    if (run.status === 'stopped') break;

    await waitForStatus(run);
    if (run.status === 'stopped') break;

    const item = recipients[run.index];
    const message = renderTemplate(run.template, item);

    notifyLaravel({ campaign: Number(run.campaignId), item_id: item.id, status: 'sending' });

    try {
      const result = await doSend(item.number, message, run.mediaPath, run.companyId, run.mediaPaths);
      if (result.ok) {
        notifyLaravel({
          campaign: Number(run.campaignId),
          item_id: item.id,
          status: 'sent',
          message_id: result.messageId,
        });
      } else {
        notifyLaravel({
          campaign: Number(run.campaignId),
          item_id: item.id,
          status: 'failed',
          error: result.error,
        });
      }
    } catch (err) {
      notifyLaravel({
        campaign: Number(run.campaignId),
        item_id: item.id,
        status: 'failed',
        error: String(err.message || err),
      });
    }

    if (run.index < recipients.length - 1) {
      await sleep(randBetween(run.delayMin, run.delayMax));
      if (run.status === 'stopped') break;
    }
  }

  const finalStatus = run.status === 'stopped' ? 'stopped' : 'completed';
  notifyLaravel({
    campaign: Number(run.campaignId),
    done: true,
    final_status: finalStatus,
  });

  campaigns.delete(run.campaignId);
  controls.delete(run.campaignId);
  console.log(`[campaign ${run.campaignId}] ${finalStatus}`);
}

function abortAllCampaigns(reason) {
  for (const run of campaigns.values()) {
    run.status = 'stopped';
    notifyLaravel({
      campaign: Number(run.campaignId),
      done: true,
      final_status: 'stopped',
      error: reason,
    });
  }
  campaigns.clear();
  controls.clear();
}

/* ------------------------------------------------------------------ */
/* Send helpers                                                         */
/* ------------------------------------------------------------------ */

async function doSend(to, message, mediaPath, companyId = null, mediaPaths = []) {
  const client = getCurrentClient(companyId);
  if (!isConnected(companyId) || !client) {
    return { ok: false, error: 'WhatsApp is not connected' };
  }

  const baseNumber = pad(to);

  const resolvedMediaPaths = Array.isArray(mediaPaths) && mediaPaths.length ? mediaPaths : (mediaPath ? [mediaPath] : []);
  const missingMedia = resolvedMediaPaths.filter((filePath) => !fs.existsSync(filePath));
  if (missingMedia.length) {
    return { ok: false, error: 'Attachment file is missing on the WhatsApp service' };
  }
  const media = resolvedMediaPaths.map((filePath) => MessageMedia.fromFilePath(filePath));

  const attemptSend = async (chatId) => {
    if (media.length) {
      let sent = null;
      for (const [index, attachment] of media.entries()) {
        sent = await client.sendMessage(chatId, attachment, index === 0 ? { caption: message } : {});
      }
      return sent;
    }
    return await client.sendMessage(chatId, message);
  };

  const dedupe = (list) => {
    const seen = new Set();
    return list.filter((item) => {
      const value = String(item || '').trim();
      if (!value || seen.has(value)) return false;
      seen.add(value);
      return true;
    });
  };

  // Try sending with different chat ID formats
  const candidates = dedupe([
    `${baseNumber}@c.us`,
    `${baseNumber}@s.whatsapp.net`,
    baseNumber,
  ]);

  let lastError = null;
  for (const candidate of candidates) {
    try {
      const sent = await attemptSend(candidate);
      if (sent) {
        return { ok: true, messageId: sent.id?.id || null };
      }
    } catch (err) {
      lastError = err;
      console.warn('[doSend]', candidate, err?.message || err);
    }
  }

  // Return the actual error from WhatsApp
  const errorText = lastError && typeof lastError.message === 'string'
    ? lastError.message
    : 'Message could not be delivered. The recipient may not be on WhatsApp or the number is invalid.';

  return { ok: false, error: errorText };
}

async function handleLogout(companyId = null) {
  const key = getStateKey(companyId);
  const client = getCurrentClient(companyId);
  const state = getCurrentState(companyId);
  state.logoutRequested = true;

  const cleanup = (async () => {
    try {
      if (client) {
        client.removeAllListeners();
        try {
          await client.logout();
        } catch {}
        try {
          await client.destroy();
        } catch {}
      }

      const targetDir = getClientSessionDir(companyId);
      for (let attempt = 0; attempt < 5; attempt++) {
        try {
          fs.rmSync(targetDir, { recursive: true, force: true });
          break;
        } catch (err) {
          if (attempt === 4) {
            console.warn('[logout] unable to remove session dir after retries', targetDir, err?.message || err);
          } else {
            await sleep(500);
          }
        }
      }
    } catch {}
  })();
  companyCleanupPromises.set(key, cleanup);

  try {
    await cleanup;
  } finally {
    if (companyCleanupPromises.get(key) === cleanup) companyCleanupPromises.delete(key);
  }

  if (getCurrentClient(companyId) === client) {
    companyClients.delete(key);
    companyInitPromises.delete(key);
    state.status = 'unlinked';
    state.qr = null;
    state.phone = null;
    state.name = null;
    state.error = null;
    // Clear cooldown and attempt history on explicit logout
    companyCooldowns.delete(key);
    connectAttempts.delete(key);
  }
}

/* ------------------------------------------------------------------ */
/* HTTP API                                                             */
/* ------------------------------------------------------------------ */

const app = express();
app.use(cors());
app.use(express.json({ limit: '5mb' }));

app.get('/api/status', (req, res) => {
  const companyId = req.query.company_id ? Number(req.query.company_id) : null;
  const state = getCurrentState(companyId);
  const client = getCurrentClient(companyId);
  const key = getStateKey(companyId);
  const cooldownUntil = companyCooldowns.get(key) || 0;
  const cooldownRemaining = cooldownUntil > Date.now() ? Math.ceil((cooldownUntil - Date.now()) / 1000) : 0;

  res.json({
    connected: Boolean(client) && state.status === 'ready',
    status: state.status,
    qr: state.qr,
    phone: state.phone,
    name: state.name,
    error: state.error,
    cooldown: cooldownRemaining,
  });
});

app.post('/api/connect', async (req, res) => {
  const companyId = req.body.company_id ? Number(req.body.company_id) : null;
  const sessionName = req.body.session_name || null;
  const state = getCurrentState(companyId);

  if (companyId === null) {
    return res.status(400).json({ ok: false, error: 'company_id is required for WhatsApp connections' });
  }

  const current = getCurrentClient(companyId);
  const currentStatus = getCurrentState(companyId).status;
  // IMPORTANT: If already showing QR, DON'T recreate the client.
  // Recreating kills the QR instantly (that was the bug).
  const needsFresh = !current || TERMINAL_STATUSES.has(currentStatus);
  
  // If rate limited, return error with wait time (503 = Service Unavailable, not 429)
  if (currentStatus === 'rate_limited') {
    const result = canAttemptConnect(companyId);
    return res.status(503).json({ 
      ok: false, 
      error: result.cooldown 
        ? `WhatsApp rate limited. Please wait ${result.waitTime} seconds before trying again.`
        : 'WhatsApp rate limited. Please wait and try again.',
      waitTime: result.waitTime
    });
  }
  
  if (needsFresh) {
    const result = await safelyReplaceClient(companyId, sessionName);
    if (result && !result.ok) {
      return res.status(503).json(result);
    }
  }

  res.json({
    ok: true,
    status: getCurrentState(companyId).status,
    qr: getCurrentState(companyId).qr,
    connected: isConnected(companyId),
  });
});

app.post('/api/logout', async (req, res) => {
  const companyId = req.body.company_id ? Number(req.body.company_id) : null;

  try {
    await handleLogout(companyId);
    const state = getCurrentState(companyId);
    res.json({ ok: true, status: state.status });
  } catch (err) {
    res.status(500).json({ ok: false, error: String(err.message || err) });
  }
});

app.post('/api/send', async (req, res) => {
  const { to, message, media_path, media_paths, company_id } = req.body || {};
  const companyId = company_id ? Number(company_id) : null;

  if (!to || !hasValidNumber(String(to))) {
    return res.status(400).json({ ok: false, error: 'A valid recipient number is required' });
  }
  if (!message || typeof message !== 'string') {
    return res.status(400).json({ ok: false, error: 'A message is required' });
  }

  try {
    const result = await doSend(String(to), message, media_path || null, companyId, Array.isArray(media_paths) ? media_paths : []);
    if (!result.ok) {
      return res.status(409).json(result);
    }
    res.json({ ok: true, message_id: result.messageId });
  } catch (err) {
    res.status(500).json({ ok: false, error: String(err.message || err) });
  }
});

app.post('/api/campaign/start', (req, res) => {
  const { campaignId, recipients, template, media_path, media_paths, delay_min, delay_max, company_id } = req.body || {};
  const companyId = company_id ? Number(company_id) : null;

  if (!campaignId) return res.status(400).json({ ok: false, error: 'campaignId is required' });
  if (!Array.isArray(recipients) || recipients.length === 0) {
    return res.status(400).json({ ok: false, error: 'recipients list is required' });
  }
  if (!template || typeof template !== 'string') {
    return res.status(400).json({ ok: false, error: 'template is required' });
  }
  if (campaigns.has(String(campaignId))) {
    return res.json({ ok: false, error: 'Campaign is already running' });
  }
  if (!isConnected(companyId)) {
    return res.status(409).json({ ok: false, error: 'WhatsApp is not connected' });
  }

  const run = {
    campaignId: String(campaignId),
    companyId,
    recipients: recipients.map((r) => ({
      id: Number(r.id),
      number: String(r.number),
      name: r.name || null,
    })),
    template: String(template),
    mediaPath: media_path || null,
    mediaPaths: Array.isArray(media_paths) ? media_paths : [],
    delayMin: (Number(delay_min) || DEFAULT_DELAY_MIN / 1000) * 1000,
    delayMax: (Number(delay_max) || DEFAULT_DELAY_MAX / 1000) * 1000,
    status: 'running',
    index: 0,
  };

  campaigns.set(run.campaignId, run);
  console.log(`[campaign ${run.campaignId}] started with ${run.recipients.length} recipients`);
  runCampaign(run);

  res.json({ ok: true });
});

app.post('/api/campaign/control', (req, res) => {
  const { campaignId, command } = req.body || {};
  const run = campaigns.get(String(campaignId));

  if (!run) {
    return res.json({ ok: false, error: 'Campaign not found or not running' });
  }

  if (command === 'pause') setCampaignStatus(run.campaignId, 'paused');
  if (command === 'resume') setCampaignStatus(run.campaignId, 'running');
  if (command === 'stop') setCampaignStatus(run.campaignId, 'stopped');

  res.json({ ok: true, status: run.status });
});

app.get('/health', (req, res) => {
  const states = Array.from(companyStates.values());
  const connected = states.some((state) => state.status === 'ready');
  res.json({
    ok: true,
    connected,
    status: connected ? 'ready' : (states[0]?.status || 'unlinked'),
    campaigns: campaigns.size,
  });
});

app.use((req, res) => {
  res.status(404).json({ ok: false, error: 'Not found' });
});

app.listen(PORT, () => {
  console.log(`WhatsApp service listening on http://127.0.0.1:${PORT}`);
});

// Close the browser cleanly on exit so the session profile isn't left locked.
async function shutdown() {
  try {
    for (const client of companyClients.values()) {
      try { await client.destroy(); } catch {}
    }
  } catch {}
  process.exit(0);
}
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
process.on('unhandledRejection', (reason) => {
  console.error('[unhandled-rejection]', reason?.message || reason);
});
process.on('uncaughtException', (error) => {
  console.error('[uncaught-exception]', error.message);
});