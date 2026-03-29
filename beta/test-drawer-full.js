#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  page.on('console', msg => {
    console.log(`[PAGE]: ${msg.text()}`);
  });

  const url = 'https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/?nocache=' + Date.now();
  console.log(`\nVisiting ${url}\n`);

  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    console.log(`✅ Page loaded (HTTP 200)`);

    // Check drawer exists
    const drawerExists = await page.$('#vana-agenda-drawer');
    console.log(`✅ Drawer element exists: ${!!drawerExists}`);

    // Get initial drawer state
    const initialHidden = await page.evaluate(() => {
      const drawer = document.getElementById('vana-agenda-drawer');
      return drawer ? drawer.hidden : 'MISSING';
    });
    console.log(`✅ Drawer initial hidden=${initialHidden}`);

    // Get button
    const button = await page.$('#vana-agenda-open-btn');
    console.log(`✅ Open button exists: ${!!button}`);

    // Click button
    if (button) {
      await button.click();
      console.log(`✅ Clicked agenda open button`);
      
      // Wait a bit
      await page.waitForTimeout(500);
      
      // Check drawer state after click
      const afterClick = await page.evaluate(() => {
        const drawer = document.getElementById('vana-agenda-drawer');
        const overlay = document.getElementById('vana-agenda-overlay');
        return {
          drawerHidden: drawer ? drawer.hidden : 'MISSING',
          overlayHidden: overlay ? overlay.hidden : 'MISSING',
          bodyClasses: document.body.className,
          drawerAriaExpanded: drawer ? drawer.getAttribute('aria-expanded') : 'N/A',
        };
      });
      
      console.log(`\n=== AFTER CLICK ===`);
      console.log(`Drawer hidden: ${afterClick.drawerHidden}`);
      console.log(`Overlay hidden: ${afterClick.overlayHidden}`);
      console.log(`Body classes: ${afterClick.bodyClasses}`);
      console.log(`Drawer aria-expanded: ${afterClick.drawerAriaExpanded}`);
      
      // Result
      const drawerOpen = afterClick.drawerHidden === false;
      if (drawerOpen) {
        console.log(`\n✅✅✅ SUCCESS! Drawer opened!`);
      } else {
        console.log(`\n⚠️ Drawer did not open (still hidden)`);
      }
    }

    // Take screenshot
    await page.screenshot({ path: 'beta/drawer-test-final.png' });
    console.log(`\n📸 Screenshot saved to beta/drawer-test-final.png`);

  } catch (err) {
    console.error(`❌ Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
