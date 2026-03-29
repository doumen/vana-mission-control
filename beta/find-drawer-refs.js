#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  const url = 'https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/';

  try {
    await page.goto(url, { waitUntil: 'networkidle' });
    const content = await page.content();
    
    // Find where "vana-agenda-drawer" appears
    const matches = [];
    let index = 0;
    while ((index = content.indexOf('vana-agenda-drawer', index)) !== -1) {
      const start = Math.max(0, index - 100);
      const end = Math.min(content.length, index + 150);
      matches.push({
        before: content.substring(start, index),
        match: content.substring(index, index + 18),
        after: content.substring(index + 18, end)
      });
      index++;
    }
    
    console.log(`Found ${matches.length} reference(s) to "vana-agenda-drawer":\n`);
    matches.forEach((m, i) => {
      console.log(`--- Reference ${i + 1} ---`);
      console.log(`...${m.before}\n[${m.match}]\n${m.after}...\n`);
    });
    
  } catch (err) {
    console.error(`Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
