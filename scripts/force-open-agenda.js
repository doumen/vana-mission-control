const { chromium } = require('playwright');
(async () => {
  const BASE = process.env.TEST_BASE || 'https://beta.vanamadhuryamdaily.com';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ timeout: 60000 });
  try {
    console.log('Visiting', BASE);
    await page.goto(BASE, { waitUntil: 'load' });
    // Attempt to directly open the drawer via DOM manipulation
    const drawerSelector = '#vana-agenda-drawer, [data-vana-agenda-drawer]';
    const overlaySelector = '#vana-agenda-overlay, [data-vana-agenda-overlay]';
    const found = await page.$(drawerSelector);
    if (!found) throw new Error('Drawer not present in DOM');

    await page.evaluate((args) => {
      const d = document.querySelector(args.dSel);
      const o = document.querySelector(args.oSel);
      if (!d) return 'no-draw';
      d.removeAttribute('hidden');
      d.classList.add('is-open');
      d.removeAttribute('aria-hidden');
      if (o) { o.removeAttribute('hidden'); o.classList.add('is-open'); }
      document.body.style.overflow = 'hidden';
      document.body.classList.add('vana-drawer-open');
      return 'opened';
    }, { dSel: drawerSelector, oSel: overlaySelector });

    await page.waitForTimeout(500);
    const outDir = 'test-output';
    const fs = require('fs'); if (!fs.existsSync(outDir)) fs.mkdirSync(outDir);
    const shotPath = `${outDir}/agenda-forced.png`;
    await page.screenshot({ path: shotPath, fullPage: true });
    console.log('Saved forced-open screenshot to', shotPath);
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.error('ERROR:', err && err.message ? err.message : err);
    try { await browser.close(); } catch (e) {}
    process.exit(2);
  }
})();
