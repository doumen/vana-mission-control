#!/usr/bin/env node
const args = process.argv.slice(2);
const targetUrl = args[0] || 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02-2/?v_day=2026-02-22';
let chromium;
try { ({ chromium } = await import('playwright')); } catch (e) { console.error('Playwright not available'); process.exit(2); }

async function probe(selector) {
  return await page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return { exists: false };
    const rect = el.getBoundingClientRect();
    const style = window.getComputedStyle(el);
    const visible = rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none' && parseFloat(style.opacity || '1') > 0;
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    let elemAtPoint = null;
    try { elemAtPoint = document.elementFromPoint(centerX, centerY); } catch (e) { elemAtPoint = null; }
    const covered = elemAtPoint && !el.contains(elemAtPoint) && elemAtPoint !== el;
    // ancestors hidden
    function ancestorChecks(node) {
      const res = [];
      let cur = node;
      while (cur) {
        const s = window.getComputedStyle(cur);
        res.push({ tag: cur.tagName, id: cur.id || null, classes: cur.className || null, display: s.display, visibility: s.visibility, opacity: s.opacity });
        cur = cur.parentElement;
      }
      return res;
    }
    return {
      exists: true,
      rect: { left: rect.left, top: rect.top, width: rect.width, height: rect.height, right: rect.right, bottom: rect.bottom },
      visible,
      computed: { display: style.display, visibility: style.visibility, opacity: style.opacity, pointerEvents: style.pointerEvents },
      centerPoint: { x: centerX, y: centerY },
      elementAtCenter: elemAtPoint ? { tag: elemAtPoint.tagName, id: elemAtPoint.id || null, classes: elemAtPoint.className || null } : null,
      covered,
      ancestors: ancestorChecks(el),
      inViewport: (rect.top < window.innerHeight && rect.bottom > 0 && rect.left < window.innerWidth && rect.right > 0),
      clientRects: el.getClientRects().length,
    };
  }, selector);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();
console.log('Opening', targetUrl);
await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(800);

const selectors = ['[data-vana-play-vod]', '[data-vana-event-key]'];
for (const sel of selectors) {
  const exists = await page.$(sel);
  if (!exists) {
    console.log(sel, '-> not found');
    continue;
  }
  const report = await probe(sel);
  console.log(JSON.stringify({ selector: sel, report }, null, 2));
}

await browser.close();
