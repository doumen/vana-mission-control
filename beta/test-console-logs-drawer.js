#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Set up console message handler
  page.on('console', msg => {
    const type = msg.type();
    if (type !== 'image') {
      console.log(`[PAGE ${type.toUpperCase()}]: ${msg.text()}`);
    }
  });

  const url = 'https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/';
  console.log(`\nVisiting ${url}\n`);

  try {
    const response = await page.goto(url, { waitUntil: 'networkidle' });
    console.log(`HTTP Status: ${response.status()}\n`);

    // Wait a bit for scripts to execute
    await page.waitForTimeout(2000);

    // Check DOM
    const drawerExists = await page.$('#vana-agenda-drawer');
    const overlayExists = await page.$('#vana-agenda-overlay');
    
    console.log(`\n=== DOM CHECK ===`);
    console.log(`#vana-agenda-drawer exists: ${!!drawerExists}`);
    console.log(`#vana-agenda-overlay exists: ${!!overlayExists}`);
    
    // Check if button exists and is clickable
    const button = await page.$('#vana-agenda-open-btn');
    console.log(`#vana-agenda-open-btn exists: ${!!button}`);
    
    if (drawerExists) {
      const visibility = await page.evaluate(() => {
        const drawer = document.getElementById('vana-agenda-drawer');
        return {
          hidden: drawer.hidden,
          display: window.getComputedStyle(drawer).display,
          visibility: window.getComputedStyle(drawer).visibility,
          ariaHidden: drawer.getAttribute('aria-hidden')
        };
      });
      console.log(`Drawer visibility: ${JSON.stringify(visibility)}`);
    }
    
  } catch (err) {
    console.error(`Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
