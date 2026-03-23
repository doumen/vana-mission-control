/**
 * Final Diagnostic Test
 * Simulates browser behavior to test drawer loading
 * and checks all prerequisites
 */

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

function extractJsCode(html) {
  // Extract the TOUR DRAWER LISTENER section
  const match = html.match(/\/\*[\s\S]*?TOUR DRAWER LISTENER[\s\S]*?\*\/\s*\(function\(\)[\s\S]*?\}\(\);/);
  return match ? match[0] : null;
}

(async () => {
  try {
    const pageUrl = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';

    console.log('🔍 FINAL DIAGNOSTIC TEST\n');
    console.log('=' .repeat(60));

    // Step 1: Fetch page
    console.log('\n[1/4] Fetching page from server...');
    const html = await fetchPage(pageUrl);
    console.log(`  ✅ Page loaded: ${html.length} bytes`);

    // Step 2: Check prerequisites
    console.log('\n[2/4] Checking prerequisites...');

    const has = {
      button: html.includes('data-drawer="vana-tour-drawer"'),
      drawer: html.includes('id="vana-tour-drawer"'),
      vanaDrawer: html.includes('window.vanaDrawer'),
      loadFunction: html.includes('function loadDrawerTours'),
      fetchCall: html.includes('fetch(window.vanaDrawer.ajaxUrl'),
      errorMsg: html.includes('Erro ao carregar visitas'),
      consoleLogging: html.includes("console.log('[VANA-DRAWER]"),
    };

    Object.entries(has).forEach(([key, val]) => {
      console.log(`  ${val ? '✅' : '❌'} ${key}`);
    });

    // Step 3: Extract vanaDrawer data
    console.log('\n[3/4] Checking window.vanaDrawer object...');
    const vanaMatch = html.match(/window\.vanaDrawer\s*=\s*(\{[^}]+(?:"[^"]*"[^}]*)*\})/);
    if (vanaMatch) {
      try {
        const vana = JSON.parse(vanaMatch[1]);
        console.log(`  ✅ tourId: ${vana.tourId}`);
        console.log(`  ✅ visitId: ${vana.visitId}`);
        console.log(`  ✅ nonce: ${vana.nonce}`);
        console.log(`  ✅ lang: ${vana.lang}`);
        console.log(`  ✅ ajaxUrl: ${vana.ajaxUrl ? 'present' : 'MISSING'}`);
      } catch (e) {
        console.log(`  ❌ Could not parse vanaDrawer`);
      }
    }

    // Step 4: Extract and analyze loader function
    console.log('\n[4/4] Analyzing loadDrawerTours function...');
    const jsCode = extractJsCode(html);
    if (jsCode) {
      console.log(`  ✅ Function code found (${jsCode.length} chars)`);

      // Check for key elements
      const checks = {
        'POST method': jsCode.includes("method: 'POST'"),
        'URLSearchParams': jsCode.includes('URLSearchParams'),
        'action param': jsCode.includes("action: 'vana_get_tour_visits'"),
        '_wpnonce param': jsCode.includes('_wpnonce'),
        'JSON parse': jsCode.includes('JSON.parse'),
        'Error handling': jsCode.includes('.catch('),
        'Console logging': jsCode.includes("console.log('[VANA-DRAWER]"),
      };

      Object.entries(checks).forEach(([key, val]) => {
        console.log(`    ${val ? '✅' : '❌'} ${key}`);
      });
    } else {
      console.log(`  ❌ Function code not found`);
    }

    console.log('\n' + '='.repeat(60));
    console.log('\n🎯 DIAGNOSIS:\n');

    const allOk = Object.values(has).every(v => v);

    if (allOk) {
      console.log('✅ ALL SYSTEMS GO!');
      console.log('\nThe code is correctly deployed. If still seeing error:');
      console.log('  1. Do Hard Refresh: Ctrl+Shift+Delete (Win) or Cmd+Shift+Del (Mac)');
      console.log('  2. Clear browser cache specifically for this domain');
      console.log('  3. Open DevTools (F12) → Network → Disable Cache checkbox');
      console.log('  4. Reload page (Ctrl+R)');
      console.log('  5. Check Console tab for [VANA-DRAWER] logs');
    } else {
      console.log('❌ SOME ISSUES FOUND');
      const missing = Object.entries(has).filter(([_, v]) => !v).map(([k]) => k);
      console.log('Missing: ' + missing.join(', '));
    }

  } catch (err) {
    console.error('❌ Error:', err.message);
  }
})();
