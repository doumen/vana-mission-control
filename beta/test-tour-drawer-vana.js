// Teste automatizado do tour drawer usando VanaTestEngine
const VanaTestEngine = require('./VanaTestEngine');

const engine = new VanaTestEngine('https://beta.vanamadhuryamdaily.com/visit/sao-paulo-janeiro-2026/', { headless: true });

engine.addTest('Tour drawer abre e exibe lista', async (page) => {
  // Espera o botão estar presente
  const btn = await page.waitForSelector('[data-drawer="vana-tour-drawer"]', { timeout: 5000 });
  if (!btn) throw new Error('Botão de tours não encontrado');

  // Clica no botão
  await btn.click();

  // Aguarda o drawer ficar visível (classe is-open e sem atributo hidden)
  await page.waitForFunction(() => {
    const d = document.getElementById('vana-tour-drawer');
    return d && d.classList.contains('is-open') && !d.hidden && getComputedStyle(d).display !== 'none';
  }, { timeout: 5000 });

  // Aguarda a lista de tours aparecer
  await page.waitForSelector('#vana-drawer-tour-list', { timeout: 5000 });
  const items = await page.$$('#vana-drawer-tour-list li');
  if (!items.length) throw new Error('Nenhum item de tour carregado na gaveta.');
});

(async () => {
  const report = await engine.runAll();
  console.log(engine.getSummary());
  // Para análise detalhada: console.log(JSON.stringify(report, null, 2));
})();
