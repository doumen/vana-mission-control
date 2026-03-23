/**
 * check-visit.js
 * 
 * Script de Teste Modular para Páginas de Visita
 * Usa VanaTestEngine para executar testes isolados
 * Output: JSON estruturado para IA/Bot
 * 
 * Uso:
 *   node check-visit.js
 *   node check-visit.js "https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/"
 *   node check-visit.js "https://..." --output=json
 *   node check-visit.js "https://..." --output=text
 */

const VanaTestEngine = require('./VanaTestEngine');
const path = require('path');

// Configuração
const DEFAULT_URL = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';
const OUTPUT_FORMAT = process.argv.includes('--output=text') ? 'text' : 'json';

const targetUrl = process.argv[2] || DEFAULT_URL;

// Instancia o motor
const engine = new VanaTestEngine(targetUrl, { 
    headless: true,
    screenshotDir: path.join(__dirname, 'test-screenshots')
});

// ────────────────────────────────────────────────────────────────
// TESTE 1: AJAX Endpoint Integration
// ────────────────────────────────────────────────────────────────
engine.addTest(
    'AJAX Endpoint Integration',
    async (page) => {
        const result = await page.evaluate(async () => {
            try {
                // Extrai dados do window.vanaDrawer
                const drawer = window.vanaDrawer || {};
                const tourId = drawer.tourId || 0;
                const visitId = drawer.visitId || 0;
                const nonce = drawer.nonce || '';

                if (!tourId || !nonce) {
                    return {
                        success: false,
                        error: `Dados incompletos: tourId=${tourId}, nonce=${nonce ? 'OK' : 'MISSING'}`
                    };
                }

                const response = await fetch(drawer.ajaxUrl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'vana_get_tour_visits',
                        tour_id: tourId,
                        visit_id: visitId,
                        _wpnonce: nonce,
                        lang: drawer.lang || 'pt'
                    }).toString()
                });

                const data = await response.json();
                return data || { success: false, error: 'Empty response' };
            } catch (e) {
                return { success: false, error: e.message };
            }
        });

        // Valida resultado
        if (!result.success) {
            throw new Error(
                `AJAX retornou erro: ${result.error || 'unknown'}`
            );
        }

        // Opcionalmente, valida se há dados
        const itemCount = Array.isArray(result.data) ? result.data.length : 0;
        if (itemCount === 0) {
            console.warn(`⚠️  Aviso: Endpoint respondeu success=true mas sem dados (${itemCount} items)`);
        }
    },
    { timeout: 5000 }
);

// ────────────────────────────────────────────────────────────────
// TESTE 2: UI Element - Notify Button
// ────────────────────────────────────────────────────────────────
engine.addTest(
    'UI Element: Notify Button exists and is visible',
    async (page) => {
        // Usa ID específico para evitar ambiguidade (há 2 elementos com a classe)
        const btnLocator = page.locator('#vana-notify-btn');
        
        // Aguarda elemento estar visível
        await btnLocator.waitFor({ state: 'visible', timeout: 5000 });
        
        // Valida que está realmente visível
        if (!await btnLocator.isVisible()) {
            throw new Error('Notify button (#vana-notify-btn) encontrado mas não está visível');
        }
    },
    { timeout: 8000 }
);

// ────────────────────────────────────────────────────────────────
// TESTE 3: Interaction - Drawer Population
// ────────────────────────────────────────────────────────────────
engine.addTest(
    'Interaction: Drawer opens and populates with content',
    async (page) => {
        // Clica no botão de tours (data-drawer="vana-tour-drawer")
        const tourBtn = page.locator('[data-drawer="vana-tour-drawer"]');
        await tourBtn.click({ timeout: 5000 });
        
        // Aguarda a gaveta abrir (classe 'is-open' adiciona visualização)
        const drawer = page.locator('#vana-tour-drawer.is-open');
        await drawer.waitFor({ state: 'visible', timeout: 6000 });
        
        // Aguarda a lista estar visível
        const drawerList = page.locator('#vana-drawer-tour-list');
        await drawerList.waitFor({ state: 'visible', timeout: 6000 });
        
        // Aguarda conteúdo real carregar (não apenas "Carregando..." ou vazio)
        // Valida que há itens <li> com conteúdo
        await page.waitForFunction(() => {
            const list = document.querySelector('#vana-drawer-tour-list');
            if (!list) return false;
            const items = list.querySelectorAll('li');
            // Verifica se há algum item com texto substancial (não é "Carregando..." ou vazio)
            if (items.length === 0) return false;
            // Verifica se primeiro item tem conteúdo significativo (pelo menos 5 chars)
            return items[0].textContent.trim().length > 5;
        }, { timeout: 8000 });
    },
    { timeout: 15000 }
);

// ────────────────────────────────────────────────────────────────
// TESTE 4: Visual - Branding Color
// ────────────────────────────────────────────────────────────────
engine.addTest(
    'Visual: Notification button color is correct gold (#FFD906)',
    async (page) => {
        // Usa ID específico para evitar ambiguidade
        const btnLocator = page.locator('#vana-notify-btn');
        
        // Aguarda elemento existir
        await btnLocator.waitFor({ state: 'attached', timeout: 5000 });
        
        // Obtém cor computada
        const computedColor = await btnLocator.evaluate((el) => {
            return window.getComputedStyle(el).color;
        });

        // Valida cor (RGB ou nome)
        const expectedColor = 'rgb(255, 217, 6)'; // #FFD906
        if (computedColor !== expectedColor) {
            throw new Error(
                `Cor incorreta detectada. Esperado: ${expectedColor}, Recebido: ${computedColor}`
            );
        }
    },
    { timeout: 5000 }
);

// ────────────────────────────────────────────────────────────────
// EXECUÇÃO
// ────────────────────────────────────────────────────────────────
(async () => {
    try {
        const finalReport = await engine.runAll();

        // Output
        if (OUTPUT_FORMAT === 'json') {
            // JSON puro para IA/Bot processar
            console.log(JSON.stringify(finalReport, null, 2));
        } else {
            // Texto legível
            console.log(engine.getSummary());
            console.log(JSON.stringify(finalReport, null, 2));
        }

        // Exit code baseado em resultados
        process.exit(finalReport.summary.failed > 0 ? 1 : 0);
    } catch (err) {
        console.error('❌ Erro crítico ao executar testes:', err.message);
        process.exit(2);
    }
})();
