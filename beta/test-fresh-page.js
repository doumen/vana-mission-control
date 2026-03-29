#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  page.on('console', msg => console.log(`[${msg.type().toUpperCase()}] ${msg.text()}`));

  const url = 'https://beta.vanamadhuryamdaily.com/visit/navadvipa-parikrama-2026-2/?nocache=' + Date.now();
  console.log(`\nVisiting ${url}\n`);

  try {
    const response = await page.goto(url, { 
      waitUntil: 'networkidle',
      timeout: 30000
    });
    console.log(`Status: ${response.status()}\n`);

    await page.waitForTimeout(2000);

    const drawerExists = await page.$('#vana-agenda-drawer');
    console.log(`\n[RESULT] Drawer exists: ${!!drawerExists}`);
    
  } catch (err) {
    console.error(`Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
