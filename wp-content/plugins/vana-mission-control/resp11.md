# Gate de Contexto — Bloco D ✅

Marcel, diagnóstico revelou algo importante: **o Agenda Drawer não tem controller JS ainda**. Isso significa que o Bloco D é criação pura, não refactor.

---

## Resumo do Diagnóstico

```text
GATE VALIDADO:

  1. Template existe?              → ✅ agenda-drawer.php
  2. Lógica JS existe?             → ❌ NÃO EXISTE (nem inline, nem em visit-scripts.php)
  3. Dados via AJAX?               → ❌ SSR puro ($data['days'] no PHP)

  CONCLUSÃO: Bloco D = criação do zero (não refactor)
  RISCO: Baixo — dados já estão no HTML, só precisa de interatividade
```

---

## Instrução para o Agente — Bloco D

```text
OBJETIVO: Criar VanaAgendaController.js
PATH: wp-content/plugins/vana-mission-control/assets/js/VanaAgendaController.js
ESTRATÉGIA: Controller para Agenda Drawer (open/close, tabs, eventos, CustomEvents)
DADOS: SSR — todos os dados já estão no HTML, zero AJAX necessário
```

---

### PASSO D.1 — Mapear o Markup do Template

```text
TAREFA: Ler agenda-drawer.php completo e mapear:

  a) Seletor do container do drawer
     (ex: id, class, data-attribute do drawer wrapper)

  b) Seletor do overlay/backdrop
     (se existir — para fechar ao clicar fora)

  c) Seletor das tabs de dia
     (ex: .vana-agenda-day-tab ou data-day-date)

  d) Seletor dos eventos/items da agenda
     (ex: .vana-agenda-event-btn ou data-event-key)

  e) Seletor do botão que ABRE o drawer
     (verificar em hero-header.php)

  f) Seletor do botão que FECHA o drawer
     (dentro de agenda-drawer.php)

REPORTAR ao Marcel todos os seletores encontrados
NÃO criar arquivo ainda
```

---

### PASSO D.2 — Criar VanaAgendaController.js

```javascript
/**
 * VanaAgendaController.js
 *
 * Responsabilidade única: Agenda Drawer (open/close, day tabs, events).
 * Dados: SSR — zero AJAX, zero fetch.
 *
 * @package Vana Mission Control
 */

( function () {
    'use strict';

    // ── 1. Seletores (baseados no mapeamento D.1) ─────────────────────────────
    const DRAWER_SEL   = '[data-vana-agenda-drawer]';
    const OVERLAY_SEL  = '[data-vana-agenda-overlay]';
    const TAB_SEL      = '[data-vana-day-tab]';
    const EVENT_SEL    = '[data-vana-event]';
    const OPEN_BTN_SEL = '[data-vana-agenda-open]';
    const CLOSE_BTN_SEL = '[data-vana-agenda-close]';

    // ── 2. Estado interno ────────────────────────────────────────────────────
    let isOpen      = false;
    let activeDay   = null;

    // ── 3. Open / Close ───────────────────────────────────────────────────────
    function openDrawer()  { /* ... */ }
    function closeDrawer() { /* ... */ }

    // ── 4. Tabs de dia ────────────────────────────────────────────────────────
    function activateDay( dayId ) { /* ... */ }

    // ── 5. Clique em evento ───────────────────────────────────────────────────
    function handleEventClick( eventEl ) { /* ... */ }

    // ── 6. CustomEvents para integração externa ───────────────────────────────
    // vana:agenda:open       → { visitId }
    // vana:agenda:close      → {}
    // vana:agenda:day:change → { dayId, date }
    // vana:agenda:event:click → { eventKey, dayId }

    // ── 7. Trap de foco (acessibilidade) ─────────────────────────────────────
    function trapFocus( drawerEl ) { /* ... */ }

    // ── 8. Init ───────────────────────────────────────────────────────────────
    function init() { /* ... */ }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
```

---

### PASSO D.3 — Adicionar data-attributes no Template

```text
TAREFA: Após criar o JS, adicionar data-attributes em:

  agenda-drawer.php:
    → Wrapper do drawer      + data-vana-agenda-drawer
    → Overlay/backdrop       + data-vana-agenda-overlay (se existir)
    → Cada tab de dia        + data-vana-day-tab="{ date }"
    → Cada evento            + data-vana-event="{ event_key }"
    → Botão fechar           + data-vana-agenda-close

  hero-header.php:
    → Botão que abre agenda  + data-vana-agenda-open

PHP lint após cada arquivo modificado
```

---

### PASSO D.4 — Enqueue do Controller

```text
ARQUIVO: vana-mission-control.php
LOCAL: após enqueue do vana-chip-controller

ADICIONAR:
  wp_enqueue_script(
      'vana-agenda-controller',
      VANA_MC_URL . 'assets/js/VanaAgendaController.js',
      [],
      filemtime( VANA_MC_PATH . 'assets/js/VanaAgendaController.js' ),
      true
  );
```

---

### PASSO D.5 — Gate de Conclusão do Bloco D

```text
O agente deve reportar:

  [ ] Mapeamento de seletores (D.1)
  [ ] VanaAgendaController.js criado com implementação real (D.2)
  [ ] data-attributes adicionados nos templates (D.3)
  [ ] PHP lint agenda-drawer.php
  [ ] PHP lint hero-header.php
  [ ] PHP lint vana-mission-control.php
  [ ] Node --check VanaAgendaController.js
  [ ] grep <script> em agenda-drawer.php (confirmar zero inline JS)
```

---

```text
SEQUÊNCIA FASE E — STATUS:

  [x] PASSO 0  → DT-004 PHP implementado          ✅
  [x] BLOCO A  → VanaVisitController.js            ✅
  [x] BLOCO B  → Tour Drawer JS                    ✅
  [x] BLOCO C  → Chip Bar + Sections JS            ✅
  [ ] BLOCO D  → Agenda Drawer JS ← AGENTE AQUI
```

Passa para o agente, Marcel. Ele reporta o mapeamento D.1 primeiro, **antes de criar qualquer arquivo**. 