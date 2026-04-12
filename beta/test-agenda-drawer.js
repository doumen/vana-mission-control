const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  const url = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';
  console.log('Visiting', url);
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

  // Wait for header open button
  const selOpen = '#vana-agenda-open-btn, [data-vana-agenda-open]';
  const openBtn = await page.waitForSelector(selOpen, { timeout: 10000 });
  if (!openBtn) {
    console.error('❌ Open button not found');
    await browser.close();
    process.exit(2);
  }

  // Click the header agenda open button
  await openBtn.click();
  console.log('Clicked agenda open button');

  // Wait for drawer to become visible (not hidden) or body class
  try {
    await page.waitForFunction(() => {
      const d = document.getElementById('vana-agenda-drawer');
      if (!d) return false;
      if (d.hasAttribute('hidden')) return false;
      if (window.getComputedStyle(d).display === 'none') return false;
      if (document.body.classList.contains('vana-drawer-open')) return true;
      return true;
    }, { timeout: 8000 });

    console.log('✅ Agenda drawer opened');
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.error('❌ Drawer did not open within timeout');
    // Capture screenshot for debugging
    const path = 'beta/test-agenda-drawer-fail.png';
    await page.screenshot({ path, fullPage: true });
    console.error('Screenshot saved to', path);
    await browser.close();
    process.exit(3);
  }
})();
