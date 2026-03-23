const { chromium } = require('playwright');

/**
 * CONFIGURAÇÃO GERAL
 * Você pode expandir este objeto para incluir novos seletores e ações
 */
const CONFIG = {
    url: process.argv[2] || 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/',
    selectors: {
        notifyBtn: '.vana-header__notify-btn',
        tourBtn: '[data-drawer="vana-tour-drawer"]',
        drawerList: '#vana-drawer-tour-list'
    },
    ajax: {
        endpoint: '/wp-admin/admin-ajax.php',
        action: 'vana_get_tour_visits'
    }
};

async function runTests() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    console.log(`\n🚀 Iniciando Suite de Testes: ${CONFIG.url}\n` + "=".repeat(50));

    try {
        // 1. Carregamento Base
        await page.goto(CONFIG.url, { waitUntil: 'networkidle' });

        // --- TESTE A: Verificação de Endpoint AJAX ---
        const ajaxResult = await page.evaluate(async (cfg) => {
            try {
                // Extrai dados necessários do window.vanaDrawer
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

                const response = await fetch(cfg.endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: cfg.action,
                        tour_id: tourId,
                        visit_id: visitId,
                        _wpnonce: nonce,
                        lang: drawer.lang || 'pt'
                    }).toString()
                });
                const result = await response.json();
                return result || { success: false, error: 'Empty response' };
            } catch (e) { return { success: false, error: e.message }; }
        }, CONFIG.ajax);

        report('AJAX Endpoint Integration', ajaxResult.success, ajaxResult.success ? `Data items: ${Array.isArray(ajaxResult.data) ? ajaxResult.data.length : 'N/A'}` : ajaxResult.error);

        // --- TESTE B: Integridade de Elementos Críticos ---
        const btnExists = await page.isVisible(CONFIG.selectors.notifyBtn);
        report('UI Element: Notify Button exists', btnExists);

        // --- TESTE C: Teste de Interação e Mudança de Estado (Drawer) ---
        await page.click(CONFIG.selectors.tourBtn);
        await page.waitForTimeout(1500); // Aguarda transição/carregamento

        const isDrawerPopulated = await page.evaluate((sel) => {
            const el = document.querySelector(sel);
            return el && el.innerHTML.trim().length > 0;
        }, CONFIG.selectors.drawerList);

        report('Interaction: Drawer population', isDrawerPopulated);

        // --- TESTE D: Validação Visual (CSS) ---
        const computedColor = await page.evaluate((sel) => {
            const el = document.querySelector(sel);
            return el ? getComputedStyle(el).color : null;
        }, CONFIG.selectors.notifyBtn);

        const isGold = computedColor === 'rgb(255, 217, 6)';
        report('Visual: Branding color (--vana-gold)', isGold, `Detected: ${computedColor}`);

    } catch (err) {
        console.error(`\n❌ ERRO CRÍTICO NA EXECUÇÃO: ${err.message}`);
    } finally {
        await browser.close();
        console.log("\n" + "=".repeat(50) + "\n✨ Testes finalizados.\n");
    }
}

/**
 * Utilitário de Report
 */
function report(name, success, meta = '') {
    const icon = success ? '✅' : '❌';
    const status = success ? 'PASS' : 'FAIL';
    console.log(`${icon} [${status}] ${name.padEnd(35)} ${meta}`);
}

runTests();