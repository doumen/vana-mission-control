/**
 * VanaTestEngine.js
 * 
 * Motor de Testes Genérico
 * Reutilizável para qualquer página do Vana
 * Output: JSON estruturado para IA/Bot processar
 * 
 * Uso:
 *   const engine = new VanaTestEngine('https://...');
 *   engine.addTest('Descrição', async (page) => { ... });
 *   const report = await engine.runAll();
 *   console.log(JSON.stringify(report));
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

class VanaTestEngine {
    constructor(baseURL, options = {}) {
        this.baseURL = baseURL;
        this.headless = options.headless !== false;
        this.screenshotDir = options.screenshotDir || path.join(__dirname, 'screenshots');
        this.tests = [];
        
        // Cria diretório de screenshots se não existir
        if (!fs.existsSync(this.screenshotDir)) {
            fs.mkdirSync(this.screenshotDir, { recursive: true });
        }

        this.results = {
            targetUrl: baseURL,
            timestamp: new Date().toISOString(),
            summary: { 
                total: 0, 
                passed: 0, 
                failed: 0,
                skipped: 0
            },
            details: [],
            metadata: {
                engine: 'VanaTestEngine v1.0',
                browser: 'chromium',
                environment: process.env.NODE_ENV || 'unknown'
            }
        };
    }

    /**
     * Adiciona um teste para ser executado
     * @param {string} description - Descrição do teste (exibida nos relatórios)
     * @param {function} testFn - Função async que executa o teste (recebe page como argumento)
     * @param {object} options - Opções (timeout, skip, etc)
     */
    addTest(description, testFn, options = {}) {
        this.tests.push({ 
            description, 
            testFn, 
            timeout: options.timeout || 10000,
            skip: options.skip || false
        });
    }

    /**
     * Executa todos os testes de forma isolada
     * Cada teste roda em sua própria aba para máxima isolação
     * Captura screenshots automáticos em caso de falha
     */
    async runAll() {
        const browser = await chromium.launch({ headless: this.headless });
        const context = await browser.newContext();

        for (const t of this.tests) {
            const page = await context.newPage();
            
            const testResult = {
                description: t.description,
                status: t.skip ? 'SKIP' : 'PASS',
                error: null,
                errorStack: null,
                screenshot: null,
                duration: 0,
                timestamp: new Date().toISOString()
            };

            const startTime = Date.now();

            try {
                // Pula o teste se marcado como skip
                if (t.skip) {
                    this.results.summary.skipped++;
                    this.results.details.push(testResult);
                    await page.close();
                    continue;
                }

                // Navega para a URL base com timeout
                await page.goto(this.baseURL, { 
                    waitUntil: 'networkidle',
                    timeout: 30000
                });
                
                // Executa a função de teste com timeout
                await Promise.race([
                    t.testFn(page),
                    new Promise((_, reject) => 
                        setTimeout(() => reject(new Error(`Timeout após ${t.timeout}ms`)), t.timeout)
                    )
                ]);
                
                testResult.status = 'PASS';
                this.results.summary.passed++;
            } catch (err) {
                testResult.status = 'FAIL';
                testResult.error = err.message;
                testResult.errorStack = err.stack;
                this.results.summary.failed++;

                // Captura screenshot automático na falha
                try {
                    const shotName = `error-${Date.now()}-${Math.random().toString(36).substr(2, 9)}.png`;
                    const shotPath = path.join(this.screenshotDir, shotName);
                    await page.screenshot({ path: shotPath, fullPage: true });
                    testResult.screenshot = shotPath;
                } catch (shotErr) {
                    testResult.screenshot = `Erro ao capturar screenshot: ${shotErr.message}`;
                }
            } finally {
                testResult.duration = Date.now() - startTime;
                this.results.summary.total++;
                this.results.details.push(testResult);
                await page.close();
            }
        }

        await context.close();
        await browser.close();
        
        return this.results;
    }

    /**
     * Getter para acesso direto aos resultados
     */
    getResults() {
        return this.results;
    }

    /**
     * Retorna resumo em formato de string legível
     */
    getSummary() {
        const { summary, details } = this.results;
        const lines = [
            `\n${'='.repeat(60)}`,
            `Relatório de Testes - ${new Date().toLocaleString()}`,
            `${'='.repeat(60)}`,
            `Total: ${summary.total} | Passou: ${summary.passed} | Falhou: ${summary.failed} | Pulou: ${summary.skipped}`,
            `Target: ${this.baseURL}`,
            `${'='.repeat(60)}\n`
        ];

        for (const detail of details) {
            const icon = detail.status === 'PASS' ? '✅' : detail.status === 'FAIL' ? '❌' : '⏭️';
            lines.push(`${icon} [${detail.status}] ${detail.description}`);
            if (detail.error) {
                lines.push(`     Error: ${detail.error}`);
            }
            if (detail.screenshot && detail.status === 'FAIL') {
                lines.push(`     Screenshot: ${detail.screenshot}`);
            }
            lines.push(`     Duration: ${detail.duration}ms\n`);
        }

        return lines.join('\n');
    }
}

module.exports = VanaTestEngine;
