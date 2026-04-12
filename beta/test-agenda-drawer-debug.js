const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  page.on('console', msg => {
    console.log('PAGE LOG:', msg.type(), msg.text());
  });
  page.on('pageerror', err => {
    console.error('PAGE ERROR:', err.message);
  });
  page.on('requestfailed', req => {
    console.warn('REQ FAIL:', req.url(), req.failure().errorText);
  });

  const url = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';
  console.log('Visiting', url);
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

  const selOpen = '#vana-agenda-open-btn, [data-vana-agenda-open]';
  const openBtn = await page.waitForSelector(selOpen, { timeout: 10000 });
  if (!openBtn) {
    console.error('Open button not found');
    await browser.close();
    process.exit(2);
  }

  // Scroll into view then click
  await openBtn.scrollIntoViewIfNeeded();
  await openBtn.click({ delay: 50 });
  console.log('Clicked agenda open button');

  // Wait a short time for UI update
  await page.waitForTimeout(1000);

  const state = await page.evaluate(() => {
    const d = document.getElementById('vana-agenda-drawer');
    const overlay = document.getElementById('vana-agenda-overlay');
    const opens = Array.from(document.querySelectorAll('[data-vana-agenda-open]')).map(b => ({ id: b.id || null, aria: b.getAttribute('aria-expanded') }));
    return {
      drawerExists: !!d,
      drawerHiddenAttr: d ? d.hasAttribute('hidden') : null,
      drawerInlineDisplay: d ? window.getComputedStyle(d).display : null,
      drawerClass: d ? d.className : null,
      drawerOuterHTML: d ? (d.outerHTML || '').slice(0, 1000) : null,
      bodyClasses: Array.from(document.body.classList),
      overlayExists: !!overlay,
      overlayHidden: overlay ? overlay.hasAttribute('hidden') : null,
      opens: opens,
      daySelectorPresent: !!document.querySelector('.vana-day-selector')
    };
  });

  console.log('STATE:', JSON.stringify(state, null, 2));

  // Save screenshot
  const path = 'beta/test-agenda-drawer-debug.png';
  await page.screenshot({ path, fullPage: true });
  console.log('Screenshot saved to', path);

  await browser.close();
  process.exit(0);
})();
