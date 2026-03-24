// Teste automatizado: Verifica se o botão de tours abre a gaveta e carrega pelo menos um item
// Requer Playwright instalado: npm install playwright

const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Altere para a URL de staging/local correta
  const url = 'https://beta.vanamadhuryamdaily.com/visit/teste-probe-via-ingest-2026-02-23-195722/';
  await page.goto(url, { waitUntil: 'domcontentloaded' });

  // Clica no botão de tours
  const tourBtn = await page.waitForSelector('[data-drawer="vana-tour-drawer"]', { timeout: 5000 });
  await tourBtn.click();

  // Aguarda a gaveta abrir
  await page.waitForSelector('#vana-tour-drawer.is-open', { timeout: 5000 });

  // Aguarda a lista de tours aparecer
  await page.waitForSelector('#vana-drawer-tour-list', { timeout: 5000 });

  // Aguarda pelo menos um item <li> na lista
  const items = await page.$$('#vana-drawer-tour-list li');
  if (items.length > 0) {
    console.log('✅ Gaveta de tours abriu e carregou itens.');
  } else {
    console.error('❌ Gaveta abriu, mas não carregou nenhum tour.');
    process.exit(1);
  }

  await browser.close();
})();
