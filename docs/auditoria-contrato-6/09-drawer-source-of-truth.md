# docs/auditoria-contrato-6/09-drawer-source-of-truth.md

## 1. Objetivo

Auditar a implementação da **gaveta esquerda (tour drawer)** na página `single-vana_visit.php` para fechar o risco R3 (fonte de verdade difusa / duplicidade). Sem alterar código — apenas leitura e recomendação mínima.

## 2. Arquivos inspecionados

- `wp-content/plugins/vana-mission-control/templates/visit/parts/tour-drawer.php`
- `wp-content/plugins/vana-mission-control/templates/visit/assets/visit-scripts.php`
- `wp-content/plugins/vana-mission-control/assets/js/VanaVisitController.js`
- `wp-content/plugins/vana-mission-control/vana-mission-control.php` (enqueue)

## 3. Resumo Executivo (rápido)

- Fonte de verdade atual da lógica da gaveta: **inline JS dentro de `visit-scripts.php`** (módulo IIFE que implementa open/close, fetch AJAX, render list, handlers, expõe helpers globais).
- `VanaVisitController.js` existe e é enfileirado, mas sua responsabilidade real é **prev/next navigation**, não a gaveta. O comentário em `tour-drawer.php` que aponta `VanaVisitController.js` como "JS Controller" está desatualizado.
- Há duplicidade/ambiguidade de documentação (template header) vs. implementação prática (inline script). Risco R3 permanece **PARCIAL** até estabilizar (ver seção final).

## 4. Evidências (trechos relevantes)

- `tour-drawer.php` header comment:
  - "JS Controller: VanaVisitController.js (Fase E)" — declara que o controller externo deveria ser a fonte de verdade.

- `visit-scripts.php` (inline):
  - Contém um IIFE com `initDrawer()`, `openDrawer()`, `closeDrawer()`, `renderToursList()`, `renderVisitsList()` e chamadas `fetch(ajaxUrl, { action: 'vana_get_tours' })` e `fetch(..., { action: 'vana_get_tour_visits' })`.
  - Expõe `window.__vanaDrawerSelectTour` / `window.__vanaDrawerBackToTours` (API global utilizada em templates).
  - Inicializa `window.vanaDrawer` payload (tourId, nonce, ajaxUrl, currentVisit) também aqui.

- `assets/js/VanaVisitController.js`:
  - Responsabilidade: prefetch + fade in + bind prev/next buttons.
  - Comentário em cabeça do arquivo inclui `DT-004: tour_id é APENAS contexto visual`.
  - Não contém código de gaveta (nenhuma referência a `#vana-tour-drawer`, `vana_get_tours`, etc.).

- `vana-mission-control.php`:
  - Em `enqueue_frontend_scripts()` o arquivo `assets/js/VanaVisitController.js` é enfileirado no footer quando `is_singular('vana_visit')`.
  - `visit-scripts.php` é incluído diretamente no template (`require`), portanto é sempre impresso inline no HTML SSR.

## 5. Fonte de Verdade Atual (resposta direta)

1. A fonte de verdade da **gaveta esquerda** é **`templates/visit/assets/visit-scripts.php` (inline JS)**.
2. `tour-drawer.php` fornece o markup HTML, mas o comportamento (AJAX, renderização, handlers) está em `visit-scripts.php`.
3. `VanaVisitController.js` é um controller distinto (prev/next) e **não** é a fonte de verdade da gaveta.

## 6. Pontos de duplicidade ou ambiguidade

- Comentário desatualizado: `tour-drawer.php` afirma que `VanaVisitController.js` é o controller da gaveta — incorreto.
- Dois locais relacionados à mesma UX:
  - Markup: `tour-drawer.php`
  - Lógica: `visit-scripts.php` (inline)
  - Controller enfileirado: `assets/js/VanaVisitController.js` (função diferente)

- Ambiguidade de responsabilidades leva a:
  - Expectativa de que um controller externo cuide da gaveta (comentário), mas o código real está inline.
  - Risco de regressão ao mover/concatenar scripts (dependência da ordem: `window.vanaDrawer` e IIFE precisam estar presentes antes de uso).

## 7. Riscos concretos de manutenção / regressão

1. Refatoração insegura: extrair ou concatenar scripts sem alinhar dependências pode quebrar a inicialização da gaveta (por exemplo, se `window.vanaDrawer` não for definido antes do módulo que o consome).
2. Falha de responsabilidade: futuros desenvolvedores podem editar `VanaVisitController.js` esperando alterar a gaveta (não terão efeito), ou duplicar código reimplementando a gaveta no controller externo, aumentando dívida técnica.
3. Testabilidade reduzida: lógica inline dificulta caching e testes automatizados separados (arquivo externo facilita cache e coverage).
4. Deploy/asset pipeline: deixar lógica inline impede otimizações de bundling/versão via `wp_enqueue_script` (só o controller externo tem versão/timefilemote usado no enqueue).

## 8. Recomendações (escolha orientada — custo/benefício)

Opções práticas, em ordem de risco / custo:

- A) Manter inline e corrigir documentação (baixo custo, baixo risco)
  - Alterações recomendadas (patch mínimo):
    1. Atualizar comentário/header em `templates/visit/parts/tour-drawer.php` para refletir que a lógica da gaveta está em `visit-scripts.php` e listar seletores/IDs dependentes.
    2. Documentar no README do plugin (ou em `docs/`) que `visit-scripts.php` contém a implementação da gaveta e expõe `window.vanaDrawer` & APIs públicas.
    3. Remover ou ajustar qualquer comentário que sugira que `VanaVisitController.js` controla a gaveta.
  - Prós: mínimo churn, resolve confusão documental, baixa chance de regressão.
  - Contras: dívida técnica permanece (inline vs enfileirado), mas rastreável/documentada.

- B) Extrair a lógica da gaveta para um controller dedicado externo (`assets/js/VanaDrawerController.js`) e enfileirá-lo (moderado custo)
  - Patch mínimo recomendado para essa rota:
    1. Criar `assets/js/VanaDrawerController.js` com o IIFE existente (refatorado sem mudanças de comportamento) e exportar componentes globais se necessário.
    2. Atualizar `vana-mission-control.php::enqueue_frontend_scripts()` para `wp_enqueue_script('vana-drawer-controller', VANA_MC_URL . 'assets/js/VanaDrawerController.js', [], filemtime(...), true)`.
    3. Remover lógica duplicada de `visit-scripts.php` ou transformá-la em uma invocação leve (apenas `window.vanaDrawer = ...;`) e dependência do arquivo enfileirado.
    4. Atualizar `tour-drawer.php` header comentado para apontar para o novo arquivo.
  - Prós: clara separação de responsabilidades, melhor caching e testabilidade, reduz chance de duplicação futura.
  - Contras: altera implementação (maior risco de regressão se não for feita cuidadosamente), exige testes de integração e atualização de versão.

Recomendação final: **Seguir A (manter inline + corrigir documentação)** como patch mínimo imediato para fechar R3 rapidamente. Planejar B como refactor faseada em backlog (com testes).

## 9. Patch mínimo recomendado (passos concretos, não aplicados)

1. Editar `templates/visit/parts/tour-drawer.php` header: substituir a linha `JS Controller: VanaVisitController.js (Fase E)` por `JS Controller: inline em templates/visit/assets/visit-scripts.php (verificação: #vana-tour-drawer, #vana-drawer-tour-list, #vana-drawer-visit-list)`.
2. Adicionar comentário curto em `visit-scripts.php` explicando: "Este módulo implementa a lógica da gaveta (open/close, fetch tours/visits, render). Se extrair para arquivo externo, preserve a disponibilidade de `window.vanaDrawer` antes da execução."
3. Atualizar `docs/auditoria-contrato-6/05-tour-drawer-prev-next.md` (já feita) para referenciar o local exato da implementação.
4. Opcional: abrir issue/template indicando plano de extração (`B`) e referenciar testes necessários.

## 10. Classificação final do risco R3 (após ação proposta A)

- Estado atual (sem mudanças): **PARCIAL** — duplicidade documental e dependência inline permanece; risco de manutenção médio.
- Após aplicar patch mínimo A (documentação atualizada): **PARCIAL → mitigado**, mas não totalmente resolvido. Recomenda-se agendar a extração (opção B) para resolver totalmente.

## 11. Conclusão curta

1. Fonte de verdade prática: `visit-scripts.php` (inline).  
2. Comentário em `tour-drawer.php` está desatualizado.  
3. Risco R3 permanece parcial; mitigação imediata por correção documental fecha a ambiguidade para desenvolvedores e reduz regressões inadvertidas.  
4. Para resolver 100% do risco, planejar extração do controller para `assets/js/VanaDrawerController.js` com enfileiramento adequado e testes de integração.

---

Auditado por: equipe técnica / automação (leitura em código, 23/03/2026)
