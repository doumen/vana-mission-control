const { chromium } = require('playwright');

(async () => {
  const BASE = process.env.TEST_BASE || 'https://beta.vanamadhuryamdaily.com';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ timeout: 60000 });
  try {
    console.log('Visiting', BASE);
    page.on('console', msg => {
      try { console.log('[PAGE CONSOLE]', msg.type(), msg.text()); } catch(e){}
    });
    page.on('pageerror', err => { console.error('[PAGE ERROR]', err && err.stack ? err.stack : err); });
    await page.goto(BASE, { waitUntil: 'load' });

    const openSelector = '[data-vana-agenda-open], #vana-agenda-open-btn, [data-drawer="vana-agenda-drawer"]';

    // Se o botão não estiver na homepage, procura um link interno que o contenha.
    let found = await page.$(openSelector);
    if (!found) {
      console.log('Open button not on homepage — scanning internal links for a visit page...');
      const anchors = await page.$$eval('a[href]', (els) => els.map(e => e.href));
      const origin = new URL(BASE).origin;
      const candidates = Array.from(new Set(anchors))
        .filter(h => h && h.startsWith(origin))
        .slice(0, 30);

      for (const href of candidates) {
        try {
          await page.goto(href, { waitUntil: 'load' });
          found = await page.$(openSelector);
          if (found) {
            console.log('Found page with agenda button:', href);
            break;
          }
        } catch (e) {
          // ignora e continua
        }
      }
    }

    if (!found) throw new Error('Agenda open button not found on homepage or checked internal pages');
    console.log('Found open button, clicking...');
    await page.click(openSelector, { force: true });

    const drawerSelector = '#vana-agenda-drawer, [data-vana-agenda-drawer]';
    // Aguarda um curto tempo para qualquer JS processar
    await page.waitForTimeout(750);
    const drawer = await page.$(drawerSelector);
    if (!drawer) throw new Error('Drawer not found in DOM after click');

    const hidden = await drawer.getAttribute('hidden');
    const cls = (await drawer.getAttribute('class')) || '';
    console.log('Drawer hidden attr:', hidden, 'class:', cls);

    // Always collect diagnostics (scripts, window state, outerHTML) to help debug
    try {
      const fs = require('fs');
      const outDir = 'test-output';
      if (!fs.existsSync(outDir)) fs.mkdirSync(outDir);
      const scripts = await page.$$eval('script[src]', els => els.map(s => s.src));
      fs.writeFileSync(`${outDir}/loaded-scripts.json`, JSON.stringify(scripts, null, 2));
      console.log('Wrote loaded scripts list to', `${outDir}/loaded-scripts.json`);

      const vanaAgendaExists = await page.evaluate(() => !!(window && window.VanaAgenda));
      const vanaAgendaOpenFn = await page.evaluate(() => typeof (window && window.VanaAgenda && window.VanaAgenda.open) === 'function');
      console.log('window.VanaAgenda present:', vanaAgendaExists, 'open fn:', vanaAgendaOpenFn);

      // save drawer outerHTML for inspection
      const drawerHtml = await page.$eval(drawerSelector, el => el.outerHTML);
      fs.writeFileSync(`${outDir}/drawer.html`, drawerHtml);
      console.log('Wrote drawer outerHTML to', `${outDir}/drawer.html`);

      // save page HTML snapshot
      const html = await page.content();
      fs.writeFileSync(`${outDir}/page.html`, html);
      console.log('Wrote page snapshot to', `${outDir}/page.html`);

      // screenshot
      const shotPath = `${outDir}/agenda-failure.png`;
      await page.screenshot({ path: shotPath, fullPage: true });
      console.error('Saved diagnostic screenshot to', shotPath);
    } catch (e) {
      console.error('Failed to write diagnostics:', e && e.message ? e.message : e);
    }

    if (hidden !== null || !cls.includes('is-open')) {
      throw new Error('Drawer did not become visible after click (see test-output for artifacts)');
    }

    // Additional diagnostics: list scripts and window.VanaAgenda
    try {
      const scripts = await page.$$eval('script[src]', els => els.map(s => s.src));
      console.log('Loaded script tags:', scripts.slice(0,50));
      const vanaAgendaExists = await page.evaluate(() => !!(window && window.VanaAgenda));
      console.log('window.VanaAgenda present:', vanaAgendaExists);
      const vanaAgendaOpenFn = await page.evaluate(() => typeof (window && window.VanaAgenda && window.VanaAgenda.open) === 'function');
      console.log('window.VanaAgenda.open is function:', vanaAgendaOpenFn);
      const drawerOuter = await page.$eval(drawerSelector, el => ({ hidden: el.getAttribute('hidden'), class: el.className }));
      const bodyClasses = await page.evaluate(() => document.body.className || '');
      console.log('Drawer state after click (raw):', drawerOuter);
      console.log('Body classes:', bodyClasses);
    } catch(e) {
      console.error('Diagnostics eval failed:', e && e.message ? e.message : e);
    }

    const overlay = await page.$('#vana-agenda-overlay, [data-vana-agenda-overlay]');
    if (!overlay) throw new Error('Overlay not found');
    const oHidden = await overlay.getAttribute('hidden');
    const oCls = (await overlay.getAttribute('class')) || '';
    console.log('Overlay hidden:', oHidden, 'class:', oCls);
    if (oHidden !== null) throw new Error('Overlay still hidden');
    if (!oCls.includes('is-open')) throw new Error('Overlay missing is-open class');

    console.log('SUCCESS: Agenda drawer opened and overlay visible.');
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.error('ERROR:', err && (err.message || err));
    try { await browser.close(); } catch (e) {}
    process.exit(2);
  }
})();
