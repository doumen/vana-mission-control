/**
 * Test Drawer with Console Logging
 * Captures all [VANA-DRAWER-DEBUG] messages from browser console
 */

const playwright = require('playwright');

(async () => {
  const browser = await playwright.chromium.launch({ headless: false });
  const page = await browser.newPage();

  // Capture all console messages
  const consoleLogs = [];
  page.on('console', (msg) => {
    consoleLogs.push({
      type: msg.type(),
      text: msg.text(),
      location: msg.location(),
    });

    // Print to stdout in real-time
    if (msg.text().includes('VANA-DRAWER')) {
      console.log(`[${msg.type().toUpperCase()}] ${msg.text()}`);
    }
  });

  // Also capture network errors
  page.on('response', (response) => {
    if (response.status() >= 400) {
      console.log(`[NETWORK] ${response.url()} - Status: ${response.status()}`);
    }
  });

  try {
    console.log('📍 Navigating to visit page...');
    await page.goto('https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/', {
      waitUntil: 'networkidle',
      timeout: 15000,
    });

    console.log('⏳ Waiting 2s for page to settle...');
    await page.waitForTimeout(2000);

    console.log('🔍 Locating drawer button...');
    const btnSelector = '[data-drawer="vana-tour-drawer"]';
    const btn = await page.$(btnSelector);

    if (!btn) {
      console.log('❌ Drawer button not found!');
      const buttons = await page.$$('[data-drawer]');
      console.log(`Found ${buttons.length} buttons with [data-drawer]`);
      
      // List all buttons
      const attrs = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('[data-drawer]')).map((el) => ({
          selector: el.tagName,
          dataDrawer: el.getAttribute('data-drawer'),
          text: el.innerText?.substring(0, 50),
        }));
      });
      console.log('Available drawers:', attrs);
    } else {
      console.log('✅ Drawer button found');
      console.log('🖱️  Clicking drawer button...');
      await btn.click();

      console.log('⏳ Waiting 3s for AJAX response...');
      await page.waitForTimeout(3000);

      // Check if drawer loaded successfully
      const tourList = await page.$('#vana-drawer-tour-list');
      if (tourList) {
        const content = await tourList.evaluate((el) => ({
          html: el.innerHTML,
          text: el.innerText,
          children: el.children.length,
        }));

        console.log('\n📋 Drawer Content:');
        console.log('  - Children: ' + content.children);
        console.log('  - HTML: ' + content.html.substring(0, 200));
        console.log('  - Text: ' + content.text.substring(0, 200));

        if (content.html.includes('Erro ao carregar')) {
          console.log('\n❌ ERROR FOUND IN DRAWER!');
        } else if (content.children > 0) {
          console.log('\n✅ DRAWER POPULATED SUCCESSFULLY!');
        }
      }
    }
  } catch (err) {
    console.error('❌ Test error:', err.message);
  } finally {
    console.log('\n📋 All Console Messages:');
    consoleLogs.forEach((log) => {
      if (log.text.includes('VANA-DRAWER') || log.type === 'error') {
        console.log(`[${log.type}] ${log.text}`);
      }
    });

    // Keep browser open for 10 seconds
    await page.waitForTimeout(5000);
    await browser.close();
  }
})();
