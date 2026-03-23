const https = require('https');

const agent = new https.Agent({
  rejectUnauthorized: false,
});

function fetchFile(url) {
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
    const url = 'https://beta.vanamadhuryamdaily.com/wp-content/plugins/vana-mission-control/templates/visit/assets/visit-scripts.php';

    console.log('📍 Fetching visit-scripts.php from server...');
    const content = await fetchFile(url);

    // Check for logging statements
    const hasLogging = content.includes('[VANA-DRAWER]');
    const hasLoadDrawer = content.includes('function loadDrawerTours');
    const hasConsoleLog = content.includes("console.log('[VANA-DRAWER]");
    const hasListener = content.includes('data-drawer="vana-tour-drawer"');

    console.log('\n✅ File fetched successfully');
    console.log(`File size: ${content.length} bytes`);
    console.log(`Lines: ${content.split('\n').length}`);

    console.log('\n📋 Feature Detection:');
    console.log(`  Has [VANA-DRAWER] logging: ${hasLogging ? '✅' : '❌'}`);
    console.log(`  Has loadDrawerTours function: ${hasLoadDrawer ? '✅' : '❌'}`);
    console.log(`  Has console.log for drawer: ${hasConsoleLog ? '✅' : '❌'}`);
    console.log(`  Has drawer listener: ${hasListener ? '✅' : '❌'}`);

    // Show snippet around loadDrawerTours
    if (hasLoadDrawer) {
      const idx = content.indexOf('function loadDrawerTours');
      const snippet = content.substring(idx, idx + 300);
      console.log('\n📌 loadDrawerTours snippet:');
      console.log(snippet);
      console.log('...');
    }

    // Check for inline script tag
    const inlineScriptRegex = /<script[^>]*>\s*\/\*[^*]*VANA TOUR DRAWER/;
    const hasInlineScript = inlineScriptRegex.test(content);
    console.log(`\n  Has inline TOUR DRAWER script block: ${hasInlineScript ? '✅' : '❌'}`);

    // Look for the exact error message
    const hasErrorMsg = content.includes('Erro ao carregar visitas');
    console.log(`  Has error message "Erro ao carregar visitas": ${hasErrorMsg ? '✅' : '❌'}`);

  } catch (err) {
    console.error('❌ Error:', err.message);
  }
})();
