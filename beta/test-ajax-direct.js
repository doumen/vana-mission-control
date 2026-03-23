/**
 * Test AJAX Endpoint Directly
 * Fetches page to get nonce, then tests AJAX
 */

const https = require('https');

// Disable certificate validation
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

function extractNonce(html) {
  const match = html.match(/"nonce"\s*:\s*"([^"]+)"/);
  return match ? match[1] : null;
}

function extractVanaDrawer(html) {
  const match = html.match(/window\.vanaDrawer\s*=\s*(\{[^}]*"nonce"[^}]*\})/);
  if (match) {
    try {
      return JSON.parse(match[1]);
    } catch {
      return null;
    }
  }
  return null;
}

function postAjax(url, params) {
  return new Promise((resolve, reject) => {
    const bodyData = new URLSearchParams(params).toString();

    const options = {
      method: 'POST',
      agent,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(bodyData),
      },
    };

    const request = https.request(url, options, (response) => {
      let data = '';
      response.on('data', (chunk) => (data += chunk));
      response.on('end', () => {
        resolve({
          status: response.statusCode,
          headers: response.headers,
          data: data,
        });
      });
    });

    request.on('error', reject);
    request.write(bodyData);
    request.end();
  });
}

(async () => {
  try {
    const pageUrl = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';
    const ajaxUrl = 'https://beta.vanamadhuryamdaily.com/wp-admin/admin-ajax.php';

    console.log('📍 Fetching page to extract nonce...');
    const html = await fetchPage(pageUrl);

    const nonce = extractNonce(html);
    const vanaDrawer = extractVanaDrawer(html);

    if (!nonce) {
      console.log('❌ Could not extract nonce from page');
      console.log('Searching for nonce pattern in HTML...');
      const matches = html.match(/"nonce"[^,}]*:[^,}]*"[^"]*"/g);
      if (matches) {
        console.log('Found nonce patterns:', matches.slice(0, 3));
      }
      process.exit(1);
    }

    console.log('✅ Found nonce:', nonce);
    console.log('✅ VanaDrawer:', JSON.stringify(vanaDrawer, null, 2));

    console.log('\n📋 Testing AJAX endpoint...');
    const ajaxResponse = await postAjax(ajaxUrl, {
      action: 'vana_get_tour_visits',
      tour_id: 360,
      visit_id: 359,
      lang: 'pt',
      _wpnonce: nonce,
    });

    console.log('✅ AJAX Response Status:', ajaxResponse.status);
    console.log('Response Headers:', ajaxResponse.headers);
    console.log('\n📋 Response Body:');
    console.log(ajaxResponse.data);

    // Try to parse JSON
    try {
      const json = JSON.parse(ajaxResponse.data);
      console.log('\n✅ Parsed JSON:');
      console.log(JSON.stringify(json, null, 2));

      if (json.success === false) {
        console.log('\n❌ AJAX returned success=false!');
        console.log('Error:', json.data);
      } else if (Array.isArray(json.data)) {
        console.log(`\n✅ AJAX returned ${json.data.length} items`);
      }
    } catch (e) {
      console.log('\n❌ Could not parse response as JSON:', e.message);
    }
  } catch (err) {
    console.error('❌ Error:', err.message);
  }
})();
