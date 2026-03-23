# Revisão da Fase D + Autorização para Fase E

Marcel, a Fase D foi executada de forma estruturada. Vou revisar cada sub-tarefa antes de liberar a Fase E.

---

## Revisão da Fase D

### ✅ O que foi feito corretamente

```text
D1 → tour-drawer.php extraído do hero-header.php
     include substituindo o bloco original
     seletores JS mantidos idênticos

D2 → tour-counter (Visita X de Y) adicionado
     day-selector (tabs multi-dia) adicionado
     condicional por $tour_id respeitada

D3 → modo neutro (.vana-stage--neutral) adicionado
     data-is-neutral, data-transitioning adicionados
     botões share + open-hk adicionados

D4 → agenda-drawer.php criado
     estrutura: dia-tabs + evento-list

D5 → sections.php criado
     4 painéis: HK | Galeria | Sangha | Revista

D6 → anchor-chips.php ajustado
     IDs harmonizados com sections.php

PHP lint → zero erros em todos os 6 arquivos
```

### ⚠️ Ponto de atenção — visit-template.php

```text
O agente leu visit-template.php mas não reportou
se agenda-drawer.php e sections.php estão sendo
incluídos no template principal.

VERIFICAÇÃO NECESSÁRIA antes de Fase E:
  Abrir visit-template.php e confirmar:

  [ ] include de agenda-drawer.php presente
  [ ] include de sections.php presente
  [ ] ordem dos includes coerente com o layout

  Se não estiverem incluídos:
    Os arquivos existem mas não são renderizados.
    Gate "Agenda drawer renderiza" e "Sections renderiza"
    nunca passarão no browser.

AÇÃO: agente deve verificar e incluir se necessário
       ANTES de rodar o gate final da Fase D.
```

### ⚠️ Gate de Regressão — Fase D

```text
Marcel, validar em browser antes de liberar Fase E:

[ ] Página de uma visita carrega sem PHP notice
[ ] Tour drawer abre (markup vindo de tour-drawer.php)
[ ] Hero exibe "Visita X de Y" quando há tour
[ ] Stage exibe modo neutro (classe .vana-stage--neutral)
    quando nenhum evento está selecionado
[ ] Agenda drawer renderiza (mesmo vazia)
[ ] Sections renderiza os 4 painéis
[ ] Chip bar mostra os chips alinhados com as sections
[ ] Seletores JS do drawer ainda funcionam
    (#vana-tour-drawer, #vana-drawer-tour-list, etc.)
[ ] Zero erros de console JS
```

---

## Autorização para Fase E

```text
STATUS: AUTORIZADA com pré-requisito duplo

PRÉ-REQUISITO 1:
  Agente verificar includes em visit-template.php
  e corrigir se necessário (ainda dentro de Fase D)

PRÉ-REQUISITO 2:
  Marcel confirmar gate de regressão da Fase D em browser
```

---

## Escopo Exato da Fase E

A Fase E tem **4 blocos** em ordem obrigatória:

### Bloco A — Stage Swap (GAP-001)

```text
PRIORIDADE: CRÍTICA
ARQUIVO: assets/js/VanaVisitController.js (criar)

OBJETIVO: resolver GAP-001 identificado no smoke test
  click no event-selector → fetch /stage/{key} → swap #vana-stage

IMPLEMENTAR:
  class VanaVisitController {

    init() {
      this.bindEventSelector()
      this.bindHistoryNavigation()
    }

    bindEventSelector() {
      // delegação de evento — não bind direto
      // (DOM muda após swap, bind direto perde referência)
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-event-key]')
        if (!btn) return
        e.preventDefault()
        this.loadEvent(btn.dataset.eventKey)
      })
    }

    async loadEvent(eventKey) {
      // 1. ativar modo transição no stage
      // 2. fetch /wp-json/vana/v1/stage/{eventKey}
      // 3. swap HTML em #vana-stage
      // 4. atualizar URL via history.pushState
      // 5. desativar modo transição
    }

    bindHistoryNavigation() {
      // window.onpopstate → loadEvent do event_key na URL
    }
  }

  window.vanaController = new VanaVisitController()
  vanaController.init()

REGRA DE DELEGAÇÃO:
  Usar document.addEventListener com closest()
  Nunca bind direto em elementos que o HTMX pode substituir

VALIDAÇÃO:
  smoke test htmlInjected: true
  Network traces: [{url: /stage/*, status: 200}]
```

### Bloco B — Tour Drawer JS

```text
OBJETIVO: migrar JS do tour drawer de visit-scripts.php
          para VanaVisitController.js

ANTES DE MIGRAR:
  Ler a seção exata de JS do drawer em visit-scripts.php
  Identificar todos os seletores usados
  Confirmar que correspondem aos IDs em tour-drawer.php

MIGRAR:
  openTourDrawer()
  closeTourDrawer()
  renderTourList()

APÓS MIGRAR:
  Remover o bloco correspondente de visit-scripts.php
  PHP lint visit-scripts.php → zero erros

REGRA: migrar um bloco por vez, testar, depois o próximo
```

### Bloco C — Chip Bar + Sections JS

```text
OBJETIVO: conectar chip bar ao sistema de panels

IMPLEMENTAR:
  chipBar: {
    init() {
      // bind click em cada chip
      // ao clicar: ativar chip, exibir panel correspondente
    }
    activate(chipId) {
      // remover is-active de todos
      // adicionar is-active no chip clicado
      // esconder todos os panels
      // exibir panel com id correspondente
    }
  }

MAPEAMENTO DE IDs (D6 → D5):
  chip[data-section="hari-katha"] → #section-hari-katha
  chip[data-section="galeria"]    → #section-galeria
  chip[data-section="sangha"]     → #section-sangha
  chip[data-section="revista"]    → #section-revista

VALIDAR:
  clicar em cada chip → panel correto aparece
  chip anterior → panel anterior some
```

### Bloco D — Agenda Drawer JS

```text
OBJETIVO: conectar botão open-hk ao agenda-drawer

IMPLEMENTAR:
  agendaDrawer: {
    open()   // abrir gaveta, buscar eventos do dia ativo
    close()  // fechar gaveta
    render() // popular lista de eventos
  }

GATILHO:
  botão .vana-btn--open-hk (adicionado em D3)
  → agendaDrawer.open()

DADOS:
  Usar window.vanaDrawer (enriquecido na Fase B)
  ou fetch para /vana/v1/kathas?visit_id={id}
```

---

## Gate Final da Fase E

```text
[ ] PHP lint OK em VanaVisitController.js (não se aplica,
    mas verificar que o enqueue está correto no PHP)
[ ] Smoke test htmlInjected: true
[ ] Smoke test Network traces: [{url: /stage/*, status: 200}]
[ ] Clique no event-selector → stage atualiza sem reload
[ ] Botão back/forward do browser → stage volta ao anterior
[ ] Tour drawer abre e fecha via VanaVisitController
[ ] Chips alternam painéis das sections
[ ] Botão open-hk abre agenda drawer
[ ] visit-scripts.php reduzido (blocos migrados removidos)
[ ] Zero erros de console JS
```

---

## Registro de Débitos Técnicos

```text
DT-001 — /kathas event_key
  Fase 2: adicionar event_key como alias em Vana_Hari_Katha_API

DT-002 — POST /react
  Fase 2: implementar o stub comentado em class-vana-rest-api.php

DT-003 — CSS tokens e estados (item 14 da matriz)
  Fase F: consolidar após Fase E estar estável
```

---

Marcel, dois passos antes de liberar Fase E para o agente:

1. **Agente verifica** includes de `agenda-drawer.php` e `sections.php` em `visit-template.php`
2. **Você confirma** o gate de regressão da Fase D em browser

Depois disso, Bloco A da Fase E pode começar. 🙏