#!/usr/bin/env node

const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  const url = 'https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/?nocache=' + Date.now();

  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    
    // Get full HTML
    const html = await page.content();
    
    // Save a portion of HTML to file for analysis
    const start = html.indexOf('<main');
    const end = html.indexOf('</main>') + 7;
    const mainContent = html.substring(start, end);
    
    fs.writeFileSync('beta/main-content.html', mainContent);
    
    // Count occurrences
    const hasDrawer = html.includes('id="vana-agenda-drawer"');
    const hasOverlay = html.includes('id="vana-agenda-overlay"');
    const drawerMarkup = html.match(/vana-agenda-drawer[\s\S]{0,300}/);
    
    console.log(`\n=== HTML ANALYSIS ===`);
    console.log(`Has id="vana-agenda-drawer": ${hasDrawer}`);
    console.log(`Has id="vana-agenda-overlay": ${hasOverlay}`);
    console.log(`HTML size: ${html.length} bytes`);
    console.log(`Main element size: ${mainContent.length} bytes`);
    
    // Check aria-controls
    const controlsMatch = html.match(/aria-controls="vana-agenda-drawer"/g);
    console.log(`\nButtons with aria-controls="vana-agenda-drawer": ${controlsMatch ? controlsMatch.length : 0}`);
    
    // Check for error in page
    const pageErrors = await page.evaluate(() => {
      const logs = [];
      // Check if console errors were captured
      if (window.__errors) {
        logs.push(...window.__errors);
      }
      return logs;
    });
    
    console.log(`\nPage errors: ${pageErrors.length}`);
    
    console.log('\n✅ Main HTML saved to beta/main-content.html');
    
    if (!hasDrawer && !hasOverlay) {
      console.log('\n❌ ISSUE: No drawer or overlay markup in HTML source');
      console.log('The partial is likely returning early due to empty $days');
    }
    
  } catch (err) {
    console.error(`Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
