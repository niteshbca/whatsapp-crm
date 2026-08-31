const puppeteer = require('puppeteer');
const path = require('path');

const userDataDir = path.join(__dirname, '.wwebjs_auth', 'session-whatsapp-crm');
const mode = process.argv[2] || 'existing';

async function tryLaunch(label) {
  try {
    const browser = await puppeteer.launch({
      userDataDir,
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
      timeout: 90000,
    });
    console.log(label, 'OK pid', browser.process().pid);
    await browser.close();
    return true;
  } catch (e) {
    console.log(label, 'ERR:', e.message);
    return false;
  }
}

(async () => {
  if (mode === 'existing') {
    await tryLaunch('existing-profile');
  } else {
    const fs = require('fs');
    fs.rmSync(userDataDir, { recursive: true, force: true });
    await tryLaunch('fresh-profile');
  }
})();