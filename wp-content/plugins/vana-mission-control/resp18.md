Para criar essa "forma" (um *Test Runner* ou *Test Engine* customizado), a melhor abordagem para o seu cenário — onde um bot do Telegram ou um Agente de IA vai acionar os testes e ler os resultados — é usar o Playwright como uma **biblioteca programática**, e não apenas como uma ferramenta de linha de comando.

Isso permite criar uma classe que age como um "molde": ela prepara o navegador, recebe uma lista de funções (os testes), executa uma a uma isoladamente, captura erros e *screenshots* automaticamente, e no final devolve um **JSON estruturado** perfeito para o seu backend ou IA processar.

Aqui está a arquitetura completa dessa "Forma".

### 1. O Motor de Testes (A Forma): `VanaTestEngine.js`

Este arquivo é o coração do sistema. Ele não sabe *o que* testar, ele só sabe *como* testar, isolar falhas e compilar os resultados.

```javascript
const { chromium } = require('playwright');
const path = require('path');

class VanaTestEngine {
    constructor(baseURL, options = {}) {
        this.baseURL = baseURL;
        this.headless = options.headless !== false;
        this.tests = [];
        this.results = {
            targetUrl: baseURL,
            timestamp: new Date().toISOString(),
            summary: { total: 0, passed: 0, failed: 0 },
            details: []
        };
    }

    /**
     * Recebe um teste para ser executado
     * @param {string} description - O que o teste faz
     * @param {function} testFn - A função contendo as ações do Playwright
     */
    addTest(description, testFn) {
        this.tests.push({ description, testFn });
    }

    /**
     * Executa todos os testes injetados de forma isolada
     */
    async runAll() {
        const browser = await chromium.launch({ headless: this.headless });
        const context = await browser.newContext();

        for (const t of this.tests) {
            // Cria uma nova aba limpa para cada teste
            const page = await context.newPage();
            
            const testResult = {
                description: t.description,
                status: 'PASS',
                error: null,
                screenshot: null
            };

            try {
                // Navega para a URL base antes de rodar a lógica do teste
                await page.goto(this.baseURL, { waitUntil: 'networkidle' });
                
                // Executa a função de teste injetada
                await t.testFn(page);
                
                this.results.summary.passed++;
            } catch (err) {
                testResult.status = 'FAIL';
                testResult.error = err.message;
                this.results.summary.failed++;

                // Captura screenshot automático na falha
                const shotName = `error-${Date.now()}.png`;
                const shotPath = path.resolve(__dirname, 'screenshots', shotName);
                await page.screenshot({ path: shotPath, fullPage: true });
                testResult.screenshot = shotPath;
            } finally {
                this.results.summary.total++;
                this.results.details.push(testResult);
                await page.close(); // Limpa a aba
            }
        }

        await browser.close();
        return this.results;
    }
}

module.exports = VanaTestEngine;
```

---

### 2. A Injeção de Testes (O Uso da Forma): `check-visit.js`

Agora você cria os scripts específicos (ex: validação de VOD, validação de CSS). Eles importam a "Forma", injetam as regras de negócio e mandam rodar.

```javascript
const VanaTestEngine = require('./VanaTestEngine');

// Pega a URL dos argumentos (ex: enviada pelo Bot do Telegram)
const targetUrl = process.argv[2] || 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/';

// 1. Instancia o Motor
const engine = new VanaTestEngine(targetUrl, { headless: true });

// 2. Injeta o Teste 1: Validação do AJAX
engine.addTest('Validação do Endpoint AJAX de Tours', async (page) => {
    const response = await page.evaluate(async () => {
        const res = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=vana_get_tour_visits'
        });
        return res.json();
    });

    if (!response.success) {
        throw new Error('O endpoint AJAX retornou success: false');
    }
});

// 3. Injeta o Teste 2: Interação com o Drawer
engine.addTest('Abertura do Drawer e renderização de itens', async (page) => {
    // Clica no botão e aguarda o DOM atualizar
    await page.click('[data-drawer="vana-tour-drawer"]');
    
    // Espera dinâmica: aguarda até que um item 'li' apareça dentro da lista
    await page.waitForSelector('#vana-drawer-tour-list li', { state: 'visible', timeout: 3000 });
});

// 4. Injeta o Teste 3: Validação de CSS do Sino
engine.addTest('Cor do sino de notificação deve ser amarela', async (page) => {
    const btnLocator = page.locator('.vana-header__notify-btn');
    
    // Obtém a cor computada
    const color = await btnLocator.evaluate((el) => window.getComputedStyle(el).color);
    
    if (color !== 'rgb(255, 217, 6)') {
        throw new Error(`Cor incorreta detectada. Esperado: rgb(255, 217, 6), Recebido: ${color}`);
    }
});

// 5. Executa e colhe os resultados (Saída em JSON puro)
(async () => {
    const finalReport = await engine.runAll();
    
    // Imprime EXATAMENTE um JSON para que o Python/Bot possa fazer o parse
    console.log(JSON.stringify(finalReport, null, 2));
})();
```

---

### 3. A Integração Perfeita com o seu Backend (O Bot)

Como o script Node.js agora devolve um JSON limpo, a integração no seu backend (exemplo em Python) se torna extremamente simples e robusta. O seu bot Python pode chamar a automação assim:

```python
import subprocess
import json

def rodar_testes_no_beta(url):
    print(f"Acionando motor de testes para: {url}...")
    
    # Chama o Node.js e captura a saída
    resultado = subprocess.run(
        ['node', 'check-visit.js', url], 
        capture_output=True, 
        text=True
    )
    
    try:
        # Transforma a saída do Node num dicionário Python
        relatorio = json.loads(resultado.stdout)
        
        # Analisa os resultados para responder no Telegram
        if relatorio['summary']['failed'] == 0:
            return "✅ Todos os testes passaram com sucesso no ambiente Beta!"
        else:
            msg = f"⚠️ Atenção! {relatorio['summary']['failed']} testes falharam.\n\n"
            for detalhe in relatorio['details']:
                if detalhe['status'] == 'FAIL':
                    msg += f"❌ {detalhe['description']}\nErro: {detalhe['error']}\n"
                    # Aqui você poderia até enviar o arquivo detalhe['screenshot'] como foto no Telegram
            return msg

    except json.JSONDecodeError:
        return "Erro crítico ao ler o relatório do motor de testes."

# Exemplo de uso:
# mensagem_telegram = rodar_testes_no_beta('https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/')
```

### O Poder dessa Abordagem
1. **Separação de Responsabilidades:** O `VanaTestEngine` lida com as complexidades do navegador (abrir, fechar, tirar print, try/catch). Os seus scripts de teste só contêm a regra de negócio.
2. **Pronto para Agentes de IA:** Se um teste falhar, o JSON gerado contém o erro exato e o caminho da imagem. Você pode programar seu Agente IA para ler esse JSON e enviar o erro para uma API de visão (como a do Gemini) avaliar a imagem e tentar entender o que quebrou na interface do WordPress.