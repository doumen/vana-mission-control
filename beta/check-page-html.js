const https = require('https');

const agent = new https.Agent({
  rejectUnauthorized: false,
});

function fetchPage(url) {
  return new Promise((resolve, reject) => {
    const request = https.get(url, { agent }, (response) => {
      let data = '';
      response.on('data', (chunk) => (data += chunk));
      response.on('end', () => resolve(data));
    });
    request.on('error', reject);
  });
}

(async () => {
  try {
    const pageUrl = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';

    console.log('📍 Fetching visit page HTML...');
    const html = await fetchPage(pageUrl);

    console.log(`✅ Page fetched: ${html.length} bytes`);

    // Look for the drawer listener code
    const hasDrawerListener = html.includes('VANA TOUR DRAWER');
    const hasLoadDrawerTours = html.includes('function loadDrawerTours');
    const hasConsoleLog = html.includes("console.log('[VANA-DRAWER]");
    const hasErrorMsg = html.includes('Erro ao carregar visitas');

    console.log('\n📋 Drawer Code Detection:');
    console.log(`  Has "VANA TOUR DRAWER" comment: ${hasDrawerListener ? '✅' : '❌'}`);
    console.log(`  Has loadDrawerTours function: ${hasLoadDrawerTours ? '✅' : '❌'}`);
    console.log(`  Has console.log with [VANA-DRAWER]: ${hasConsoleLog ? '✅' : '❌'}`);
    console.log(`  Has error message: ${hasErrorMsg ? '✅' : '❌'}`);

    // Check for vanaDrawer object
    const vanaMatch = html.match(/window\.vanaDrawer\s*=\s*\{[^}]+\}/);
    if (vanaMatch) {
      console.log(`\n✅ Found window.vanaDrawer:`);
      console.log(vanaMatch[0].substring(0, 200) + '...');
    } else {
      console.log(`\n❌ window.vanaDrawer not found!`);
    }

    // Look for the data-drawer button
    const btnMatch = html.match(/data-drawer="vana-tour-drawer"/);
    console.log(`\n  Has [data-drawer="vana-tour-drawer"] button: ${btnMatch ? '✅' : '❌'}`);

    // Check if unminified/dev code is present
    const hasRawCode = html.includes('var drawerLoaded = false;');
    console.log(`  Has raw JavaScript code (not minified): ${hasRawCode ? '✅' : '❌'}`);

    // If logging was added, we should see specific patterns
    if (!hasConsoleLog) {
      console.log('\n⚠️  The logging code is NOT in the page. Possible causes:');
      console.log('  1. Cache - page was cached before deployment');
      console.log('  2. Visit-scripts.php is not being included');
      console.log('  3. Visit-scripts.php is broken/empty');

      // Check if there is ANY script content at all
      const scriptMatch = html.match(/<script[^>]*>[\s\S]{1,1000}<\/script>/);
      if (scriptMatch) {
        console.log('\n📌 Found script block sample:');
        console.log(scriptMatch[0].substring(0, 300));
      }
    }

  } catch (err) {
    console.error('❌ Error:', err.message);
  }
})();
