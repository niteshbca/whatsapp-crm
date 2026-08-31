require('dotenv').config();
const { Client, LocalAuth } = require('whatsapp-web.js');

const client = new Client({
  authStrategy: new LocalAuth({ clientId: 'whatsapp-crm', dataPath: require('path').join(__dirname, '.wwebjs_auth') }),
  puppeteer: { headless: true, args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'] },
});

const NUMBER = process.argv[2] || '8810751872';

client.on('ready', async () => {
  try {
    console.log('READY. Testing number:', NUMBER);
    const id = `${NUMBER}@c.us`;
    // 1) getContactLidAndPhone
    try {
      const res = await client.getContactLidAndPhone(id);
      console.log('getContactLidAndPhone =>', JSON.stringify(res));
    } catch (e) {
      console.log('getContactLidAndPhone ERROR:', e.message);
    }
    // 2) getChatById raw
    try {
      const chat = await client.getChatById(id);
      console.log('getChatById OK, id:', chat.id?._serialized);
    } catch (e) {
      console.log('getChatById ERROR:', e.message);
    }
    // 3) sendMessage @c.us
    try {
      const sent = await client.sendMessage(id, 'hii');
      console.log('sendMessage@c.us =>', sent ? 'MSG:' + sent.id?._serialized : 'UNDEFINED (no msg returned)');
    } catch (e) {
      console.log('sendMessage@c.us ERROR:', e.message);
    }
    // 4) inspect lid flag
    try {
      const info = await client.pupPage.evaluate(() => {
        const gating = window.require('WAWebLid1X1MigrationGating');
        const utils = gating.Lid1X1MigrationUtils;
        return { isLidMigrated: utils.isLidMigrated ? utils.isLidMigrated() : 'n/a', hasShouldHave: typeof utils.shouldHaveAccountLid === 'function' };
      });
      console.log('lid gating =>', JSON.stringify(info));
    } catch (e) {
      console.log('lid gating ERROR:', e.message);
    }
  } catch (e) {
    console.log('TOP ERROR:', e.message);
  } finally {
    await client.destroy();
    process.exit(0);
  }
});

client.on('auth_failure', (m) => { console.log('AUTH FAILURE:', m); process.exit(1); });
client.on('disconnected', (r) => { console.log('DISCONNECTED:', r); process.exit(1); });
client.initialize();
