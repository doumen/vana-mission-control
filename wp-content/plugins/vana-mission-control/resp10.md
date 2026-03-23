# Gate de Contexto — Bloco C ✅

Marcel, diagnóstico perfeito do agente. Contexto 100% mapeado.

---

## Resumo do Diagnóstico

```text
GATE VALIDADO:

  1. Chip Bar existe?              → ✅ anchor-chips.php
  2. Chips = scroll + highlight    → ✅ (sem AJAX, sem filtro)
  3. Tour chip existe?             → ❌ (não existe na chip bar)
     Tour = drawer no header       → ✅ (já tratado no Bloco B)
     DT-004 no chip bar            → N/A (sem risco)
```

---

## Instrução para o Agente — Bloco C

```text
OBJETIVO: Extrair e isolar o JS do Chip Bar + Sections
          em arquivo dedicado VanaChipController.js
PATH: wp-content/plugins/vana-mission-control/assets/js/VanaChipController.js
ORIGEM: visit-scripts.php (lógica de chips + IntersectionObserver)
ESTRATÉGIA: Extração sem regressão + CustomEvents desacoplados
```

---

### PASSO C.1 — Mapear o Código Atual

```text
TAREFA: Ler anchor-chips.php e o bloco de chips
        em visit-scripts.php (linhas ~940-1285)
        e identificar EXATAMENTE:

  a) Quais funções/blocos controlam o scroll dos chips
  b) Qual bloco inicializa o IntersectionObserver
  c) Quais variáveis são compartilhadas com outros módulos
  d) Se há dependência de window.vanaVisitData ou similar

REPORTAR ao Marcel antes de criar o arquivo JS
NÃO modificar nada ainda
```

---

### PASSO C.2 — Criar VanaChipController.js

```javascript
/**
 * VanaChipController.js
 *
 * Responsabilidade única: chip bar (scroll + highlight de seção).
 * Estratégia: IntersectionObserver + scroll suave + CustomEvents.
 *
 * IMPORTANTE:
 * - Chips NÃO filtram conteúdo
 * - Chips NÃO conhecem tour_id
 * - Chips apenas navegam entre seções da visita atual
 *
 * @package Vana Mission Control
 */

( function () {
    'use strict';

    // ── 1. Seletores ─────────────────────────────────────────────────────────
    const BAR_SEL    = '[data-vana-chip-bar]';
    const CHIP_SEL   = '[data-vana-chip]';
    const SECTION_SEL = '[data-vana-section]';

    // ── 2. Estado interno ────────────────────────────────────────────────────
    let activeChipId = null;
    let observer     = null;

    // ── 3. Scroll suave para seção ────────────────────────────────────────────
    function scrollToSection( sectionId ) { /* ... */ }

    // ── 4. Highlight do chip ativo ────────────────────────────────────────────
    function setActiveChip( chipId ) { /* ... */ }

    // ── 5. IntersectionObserver das seções ────────────────────────────────────
    function initObserver() { /* ... */ }

    // ── 6. CustomEvents para integração externa ───────────────────────────────
    // vana:chip:activated  → { chipId, sectionId }
    // vana:chip:bar:ready  → { chips: [] }

    // ── 7. Init ───────────────────────────────────────────────────────────────
    function init() { /* ... */ }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
} )();
```

---

### PASSO C.3 — Verificar data-attributes no Template

```text
TAREFA: Verificar se anchor-chips.php já usa:

  data-vana-chip-bar       (container)
  data-vana-chip           (cada chip)
  data-vana-section        (cada seção em sections.php)

SE NÃO EXISTIREM:
  Apenas reportar — não modificar ainda
```

---

### PASSO C.4 — Gate de Conclusão do Bloco C

```text
O agente deve reportar:

  [ ] Mapeamento do código atual (C.1)
  [ ] VanaChipController.js criado (C.2)
  [ ] Status dos data-attributes (C.3)
  [ ] PHP lint anchor-chips.php
  [ ] JS lint VanaChipController.js
  [ ] Aguardar aprovação de Marcel para:
      → Adicionar data-attributes no template
      → Remover bloco de chips do visit-scripts.php
      → Adicionar enqueue do novo controller
```

---

```text
SEQUÊNCIA FASE E — STATUS:

  [x] PASSO 0  → DT-004 PHP implementado          ✅
  [x] BLOCO A  → VanaVisitController.js            ✅
  [x] BLOCO B  → Tour Drawer JS                    ✅
  [ ] BLOCO C  → Chip Bar + Sections JS (AGENTE)
  [ ] BLOCO D  → Agenda Drawer JS
```

Passa para o agente, Marcel. Ele mapeia primeiro (C.1) e só cria o arquivo após o mapeamento. 🙏