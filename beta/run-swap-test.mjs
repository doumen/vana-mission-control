#!/usr/bin/env node
// Programmatic swapStage test: dispatches `vana:event:select` and checks Stage
const args = process.argv.slice(2);
const targetUrl = args[0] || "https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02-2/?v_day=2026-02-22";
const videoId = args.find(a => a.startsWith('--video=')) ? args.find(a => a.startsWith('--video=')).split('=')[1] : 'dQw4w9WgXcQ';
const headless = !args.includes('--no-headless');

let chromium;
try { ({ chromium } = await import('playwright')); }
catch (err) { console.error('Playwright not installed.'); process.exit(2); }

async function run() {
  const browser = await chromium.launch({ headless });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('Opening', targetUrl);
  await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(600);

  const before = await page.locator('#vana-stage').innerHTML().catch(() => '');
  console.log('stage before length:', before.length);

  const res = await page.evaluate(({ videoId }) => {
    try {
      const ev = new CustomEvent('vana:event:select', {
        detail: { videoId: videoId, title: 'swap-test', segStart: null },
        cancelable: true,
      });
      const prevented = !document.dispatchEvent(ev) ? ev.defaultPrevented : ev.defaultPrevented;
      return { dispatched: true, defaultPrevented: prevented };
    } catch (err) {
      return { dispatched: false, error: String(err) };
    }
  }, { videoId });

  console.log('dispatch result:', res);

  await page.waitForTimeout(800);

  const after = await page.locator('#vana-stage').innerHTML().catch(() => '');
  console.log('stage after length:', after.length);

  const iframe = await page.$('#vanaStageIframe');
  let iframeSrc = null;
  if (iframe) {
    iframeSrc = await iframe.getAttribute('src');
  }

  console.log('iframe present:', !!iframe, 'src:', iframeSrc);

  const updated = (before !== after) || !!iframe;

  await browser.close();

  if (updated) {
    console.log('RESULT: swap appears to have succeeded');
    process.exit(0);
  } else {
    console.log('RESULT: swap did NOT update stage');
    process.exit(1);
  }
}

run().catch(err => { console.error(err); process.exit(2); });
