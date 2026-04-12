#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext();
  
  // Clear cache for the domain
  const page = await context.newPage();

  // Set up console message handler
  page.on('console', msg => {
    console.log(`[PAGE ${msg.type().toUpperCase()}]: ${msg.text()}`);
  });

  const url = 'https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/?nocache=' + Date.now();
  console.log(`\nVisiting ${url} (with cache bust)\n`);

  try {
    // Clear application cache
    await page.evaluate(() => {
      if ('caches' in window) {
        caches.keys().then(names => {
          names.forEach(name => caches.delete(name));
        });
      }
    });
    
    const response = await page.goto(url, { 
      waitUntil: 'networkidle',
      timeout: 30000
    });
    console.log(`HTTP Status: ${response.status()}\n`);

    // Wait for scripts
    await page.waitForTimeout(3000);

    // Check DOM
    const drawerExists = await page.$('#vana-agenda-drawer');
    console.log(`\n#vana-agenda-drawer exists: ${!!drawerExists}`);
    
  } catch (err) {
    console.error(`Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
