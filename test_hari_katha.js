const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  const requests = [];
  const consoleEvents = [];
  const pageErrors = [];

  page.on('response', async (res) => {
    const url = res.url();
    if (url.includes('/wp-json/vana/v1/kathas')) {
      let bodySnippet = '';
      try {
        const text = await res.text();
        bodySnippet = text.slice(0, 300);
      } catch (e) {}
      requests.push({
        url,
        status: res.status(),
        bodySnippet
      });
    }
  });

  page.on('console', (msg) => {
    if (['error', 'warning'].includes(msg.type())) {
      consoleEvents.push({
        type: msg.type(),
        text: msg.text()
      });
    }
  });

  page.on('pageerror', (err) => {
    pageErrors.push(String(err));
  });

  await page.goto('https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/', {
    waitUntil: 'networkidle'
  });

  const domInfo = await page.evaluate(() => {
    const root =
      document.querySelector('#vana-section-hk') ||
      document.querySelector('#vana-section-hari-katha');

    if (!root) {
      return { ok: false, reason: 'root not found' };
    }

    const list =
      root.querySelector('[data-role="katha-list"]') ||
      root.querySelector('.vana-hk__list');

    const passages =
      root.querySelector('[data-role="passage-list"]') ||
      root.querySelector('.vana-hk__passages');

    return {
      ok: true,
      id: root.id,
      visitId: root.getAttribute('data-visit-id'),
      day: root.getAttribute('data-day'),
      lang: root.getAttribute('data-lang'),
      listExists: !!list,
      listChildren: list ? list.children.length : 0,
      listTextSample: list ? list.textContent.trim().slice(0, 300) : '',
      passagesExists: !!passages,
      passagesChildren: passages ? passages.children.length : 0
    };
  });

  console.log(JSON.stringify({
    domInfo,
    requests,
    consoleEvents,
    pageErrors
  }, null, 2));

  await browser.close();
})();