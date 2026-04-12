const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  const url = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';
  console.log('Visiting', url);
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

  const sel = '#vana-agenda-open-btn, [data-vana-agenda-open]';
  const el = await page.waitForSelector(sel, { timeout: 10000, state: 'attached' });
  if (!el) { console.error('Element not found'); await browser.close(); process.exit(2); }

  const info = await page.evaluate((sel) => {
    const btn = document.querySelector(sel);
    if (!btn) return { found: false };
    const styles = window.getComputedStyle(btn);
    // climb ancestors until body
    const ancestors = [];
    let cur = btn;
    while (cur && cur !== document.body) {
      ancestors.push({ tag: cur.tagName, id: cur.id || null, class: cur.className || null, styleDisplay: window.getComputedStyle(cur).display, styleVisibility: window.getComputedStyle(cur).visibility, hiddenAttr: cur.hasAttribute('hidden') });
      cur = cur.parentElement;
    }
    return {
      found: true,
      outerHTML: btn.outerHTML.slice(0,500),
      computed: {
        display: styles.display,
        visibility: styles.visibility,
        opacity: styles.opacity,
        offsetParent: !!btn.offsetParent
      },
      ancestors
    };
  }, sel);

  console.log('INFO:', JSON.stringify(info, null, 2));
  const shot = 'beta/check-agenda-visibility.png';
  await page.screenshot({ path: shot, fullPage: true });
  console.log('Screenshot saved to', shot);
  await browser.close();
  process.exit(0);
})();
