#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Set up console message handler
  page.on('console', msg => console.log(`PAGE LOG: ${msg.type()} ${msg.text()}`));

  const url = 'https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/';
  console.log(`Visiting ${url}`);

  try {
    const response = await page.goto(url, { waitUntil: 'networkidle' });
    console.log(`Status: ${response.status()}`);

    // Get raw HTML to check if agenda-drawer is in source
    const content = await page.content();
    
    const hasDrawerComment = content.includes('vana-agenda-drawer');
    const hasOverlayId = content.includes('id="vana-agenda-overlay"');
    const hasDrawerId = content.includes('id="vana-agenda-drawer"');
    
    console.log(`\n=== RAW HTML ANALYSIS ===`);
    console.log(`Contains "vana-agenda-drawer": ${hasDrawerComment}`);
    console.log(`Contains id="vana-agenda-overlay": ${hasOverlayId}`);
    console.log(`Contains id="vana-agenda-drawer": ${hasDrawerId}`);
    
    // Also check via DOM
    if (await page.$('#vana-agenda-drawer')) {
      console.log(`DOM has #vana-agenda-drawer: YES`);
    } else {
      console.log(`DOM has #vana-agenda-drawer: NO`);
    }
    
    if (await page.$('#vana-agenda-overlay')) {
      console.log(`DOM has #vana-agenda-overlay: YES`);
    } else {
      console.log(`DOM has #vana-agenda-overlay: NO`);
    }
    
    // Extract part of HTML around "agenda"
    const drawerMatch = content.match(/id="vana-agenda-drawer"[\s\S]{0,500}/);
    if (drawerMatch) {
      console.log(`\nFound drawer markup:\n${drawerMatch[0]}`);
    } else {
      console.log(`\nNo drawer markup found in HTML source.`);
    }
    
  } catch (err) {
    console.error(`Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
