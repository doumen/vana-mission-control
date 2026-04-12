#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  page.on('console', msg => console.log(`[${msg.type()}] ${msg.text()}`));

  // Try to find available visits on the page
  const homepage = 'https://beta.vanamadhuryamdaily.com/';
  console.log(`\nVisiting homepage: ${homepage}\n`);

  try {
    const response = await page.goto(homepage, { waitUntil: 'networkidle' });
    console.log(`Status: ${response.status()}`);

    // Find all links
    const links = await page.$$eval('a[href*="/visit/"]', links => {
      return links.map(a => ({
        text: a.textContent.trim(),
        href: a.href
      })).filter(item => item.text.length > 0);
    });

    if (links.length > 0) {
      console.log(`\nFound ${links.length} visit links:`);
      links.slice(0, 10).forEach((link, i) => {
        console.log(`  ${i+1}. ${link.text} → ${link.href}`);
      });
      
      // Try first one
      if (links.length > 0) {
        console.log(`\nTrying first visit: ${links[0].href}`);
        const visit_response = await page.goto(links[0].href, { waitUntil: 'networkidle', timeout: 30000 });
        console.log(`Visit page status: ${visit_response.status()}`);
        
        await page.waitForTimeout(2000);
        
        const drawerExists = await page.$('#vana-agenda-drawer');
        console.log(`Drawer exists: ${!!drawerExists}`);
      }
    } else {
      console.log('No visit links found on homepage');
    }
    
  } catch (err) {
    console.error(`Error: ${err.message}`);
  } finally {
    await browser.close();
  }
})();
