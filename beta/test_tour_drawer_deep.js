const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({
    viewport: { width: 1440, height: 1200 }
  });

  const logs = [];
  const errors = [];
  const requests = [];
  const responses = [];

  page.on('console', msg => {
    logs.push({
      type: msg.type(),
      text: msg.text()
    });
  });

  page.on('pageerror', err => {
    errors.push(String(err));
  });

  page.on('request', req => {
    const url = req.url();
    if (
      url.includes('/wp-admin/admin-ajax.php') ||
      url.includes('/wp-json/')
    ) {
      requests.push({
        method: req.method(),
        url
      });
    }
  });

  page.on('response', async res => {
    const url = res.url();
    if (
      url.includes('/wp-admin/admin-ajax.php') ||
      url.includes('/wp-json/')
    ) {
      let body = '';
      try {
        body = await res.text();
        body = body.slice(0, 500);
      } catch (_) {}
      responses.push({
        status: res.status(),
        url,
        body
      });
    }
  });

  const url = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';
  console.log(`Abrindo: ${url}`);
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });

  await page.screenshot({ path: '01-page-loaded.png', fullPage: true });

  const drawerTrigger = page.locator('[data-drawer="vana-tour-drawer"]');
  const triggerCount = await drawerTrigger.count();
  console.log('drawer trigger count:', triggerCount);

  if (!triggerCount) {
    console.log('ERRO: trigger do drawer não encontrado');
    await browser.close();
    process.exit(1);
  }

  await drawerTrigger.first().click();
  await page.waitForTimeout(1200);
  await page.screenshot({ path: '02-drawer-opened.png', fullPage: true });

  const drawerState1 = await page.evaluate(() => {
    function info(sel) {
      const el = document.querySelector(sel);
      if (!el) return { found: false };

      const cs = window.getComputedStyle(el);
      return {
        found: true,
        hiddenAttr: el.hasAttribute('hidden'),
        ariaHidden: el.getAttribute('aria-hidden'),
        display: cs.display,
        visibility: cs.visibility,
        opacity: cs.opacity,
        text: (el.textContent || '').trim().slice(0, 120),
        childCount: el.children ? el.children.length : 0
      };
    }

    const allCandidates = Array.from(document.querySelectorAll('*')).filter(el => {
      const txt = ((el.className || '') + ' ' + (el.id || '') + ' ' + (el.getAttribute('data-role') || '')).toLowerCase();
      return txt.includes('spin') || txt.includes('load') || txt.includes('tour') || txt.includes('drawer');
    }).slice(0, 80).map(el => {
      const cs = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        id: el.id || '',
        className: typeof el.className === 'string' ? el.className : '',
        dataRole: el.getAttribute('data-role') || '',
        hiddenAttr: el.hasAttribute('hidden'),
        ariaHidden: el.getAttribute('aria-hidden'),
        display: cs.display,
        visibility: cs.visibility,
        opacity: cs.opacity,
        text: (el.textContent || '').trim().slice(0, 80)
      };
    });

    return {
      drawer: info('#vana-tour-drawer'),
      overlay: info('.vana-drawer-overlay'),
      loading: info('[data-role="tour-loading"]'),
      list: info('[data-role="tour-list"]'),
      body: info('[data-role="tour-body"]'),
      candidates: allCandidates
    };
  });

  console.log('\n=== Estado após abrir drawer ===');
  console.log(JSON.stringify(drawerState1, null, 2));

  await page.waitForTimeout(2500);

  const listCount = await page.locator('[data-role="tour-list"] > *').count();
  console.log('\nQuantidade de itens na lista:', listCount);

  await page.screenshot({ path: '03-drawer-after-load.png', fullPage: true });

  const visibleSpinnersAfterList = await page.evaluate(() => {
    function isVisible(el) {
      const cs = window.getComputedStyle(el);
      return (
        !el.hasAttribute('hidden') &&
        cs.display !== 'none' &&
        cs.visibility !== 'hidden' &&
        cs.opacity !== '0'
      );
    }

    return Array.from(document.querySelectorAll('*'))
      .filter(el => {
        const text = (
          (el.id || '') + ' ' +
          (typeof el.className === 'string' ? el.className : '') + ' ' +
          (el.getAttribute('data-role') || '')
        ).toLowerCase();

        return (
          text.includes('spin') ||
          text.includes('loading') ||
          text.includes('loader')
        );
      })
      .filter(isVisible)
      .slice(0, 20)
      .map(el => {
        const cs = window.getComputedStyle(el);
        return {
          tag: el.tagName,
          id: el.id || '',
          className: typeof el.className === 'string' ? el.className : '',
          dataRole: el.getAttribute('data-role') || '',
          display: cs.display,
          visibility: cs.visibility,
          opacity: cs.opacity,
          text: (el.textContent || '').trim().slice(0, 80)
        };
      });
  });

  console.log('\n=== Spinners visíveis após lista carregar ===');
  console.log(JSON.stringify(visibleSpinnersAfterList, null, 2));

  const firstTourButton = page.locator('[data-role="tour-list"] button, [data-role="tour-list"] a').first();
  const firstTourExists = await firstTourButton.count();

  if (firstTourExists) {
    console.log('\nClicando no primeiro tour...');
    await firstTourButton.click();
    await page.waitForTimeout(1800);
    await page.screenshot({ path: '04-after-first-tour-click.png', fullPage: true });

    const afterClickState = await page.evaluate(() => {
      function isVisible(el) {
        const cs = window.getComputedStyle(el);
        return (
          !el.hasAttribute('hidden') &&
          cs.display !== 'none' &&
          cs.visibility !== 'hidden' &&
          cs.opacity !== '0'
        );
      }

      const spinners = Array.from(document.querySelectorAll('*'))
        .filter(el => {
          const text = (
            (el.id || '') + ' ' +
            (typeof el.className === 'string' ? el.className : '') + ' ' +
            (el.getAttribute('data-role') || '')
          ).toLowerCase();

          return (
            text.includes('spin') ||
            text.includes('loading') ||
            text.includes('loader')
          );
        })
        .filter(isVisible)
        .slice(0, 20)
        .map(el => {
          const cs = window.getComputedStyle(el);
          return {
            tag: el.tagName,
            id: el.id || '',
            className: typeof el.className === 'string' ? el.className : '',
            dataRole: el.getAttribute('data-role') || '',
            display: cs.display,
            visibility: cs.visibility,
            opacity: cs.opacity,
            text: (el.textContent || '').trim().slice(0, 80)
          };
        });

      const detailCandidates = Array.from(document.querySelectorAll('*'))
        .filter(el => {
          const text = (
            (el.id || '') + ' ' +
            (typeof el.className === 'string' ? el.className : '') + ' ' +
            (el.getAttribute('data-role') || '')
          ).toLowerCase();

          return text.includes('tour') || text.includes('detail') || text.includes('drawer');
        })
        .slice(0, 50)
        .map(el => {
          const cs = window.getComputedStyle(el);
          return {
            tag: el.tagName,
            id: el.id || '',
            className: typeof el.className === 'string' ? el.className : '',
            dataRole: el.getAttribute('data-role') || '',
            hiddenAttr: el.hasAttribute('hidden'),
            display: cs.display,
            visibility: cs.visibility,
            opacity: cs.opacity,
            text: (el.textContent || '').trim().slice(0, 100)
          };
        });

      return { spinners, detailCandidates };
    });

    console.log('\n=== Estado após clicar no primeiro tour ===');
    console.log(JSON.stringify(afterClickState, null, 2));
  } else {
    console.log('\nNenhum botão/link de tour encontrado para clique.');
  }

  console.log('\n=== Requests capturados ===');
  console.log(JSON.stringify(requests, null, 2));

  console.log('\n=== Responses capturadas ===');
  console.log(JSON.stringify(responses, null, 2));

  console.log('\n=== Console logs ===');
  console.log(JSON.stringify(logs, null, 2));

  console.log('\n=== Page errors ===');
  console.log(JSON.stringify(errors, null, 2));

  await browser.close();
})();
