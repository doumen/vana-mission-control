

# Auditoria Pré-Implementação

Marcel, antes de codar preciso ver o conteúdo dos arquivos-chave. Vou agrupar por **prioridade** — o que é crítico ver primeiro e o que pode esperar.

---

## 🔴 Preciso Ver AGORA (Bloco 1)

São os arquivos que vou **modificar diretamente** ou que definem a estrutura atual:

### Estrutura da página da visita

```text
1.  templates/visit/visit-template.php         ← layout principal (onde vive a moldura)
2.  templates/visit/_bootstrap.php             ← dados que chegam ao template
3.  templates/single-vana_visit.php            ← entry point
```

### Zonas que vão mudar

```text
4.  templates/visit/parts/hero-header.php      ← hero (vai ganhar CTA revista + compactar)
5.  templates/visit/parts/anchor-chips.php     ← chips (vai ter 3 estados)
6.  templates/visit/parts/stage.php            ← stage (vai simplificar + mini player)
7.  templates/visit/parts/sections.php         ← zona mutável (vai virar container de estados)
```

### JS que controla tudo

```text
8.  assets/js/VanaVisitController.js           ← orquestrador principal
9.  assets/js/VanaStageController.js           ← controle do player/stage
10. assets/js/VanaChipController.js            ← controle dos chips
```

---

## 🟡 Preciso Ver EM SEGUIDA (Bloco 2)

Dependem do que eu encontrar no Bloco 1:

```text
11. assets/js/VanaStageBridge.js               ← comunicação stage ↔ player
12. assets/js/VanaEventController.js           ← eventos/seleção de item ativo
13. includes/class-visit-stage-resolver.php    ← resolve dados do stage server-side
14. includes/class-visit-stage-view-model.php  ← view model do stage
15. templates/visit/parts/stage-fragment.php   ← fragment HTML do stage (REST?)
16. includes/rest/class-vana-rest-stage-fragment.php ← endpoint do fragment
```

### Hari-Katha (passage será novo, mas preciso ver o que existe)

```text
17. templates/visit/parts/hari-katha.php       ← o que existe hoje de HK na visita
18. includes/class-vana-hk-passage-cpt.php     ← CPT do passage
19. includes/class-vana-katha-cpt.php          ← CPT da katha
20. includes/rest/class-vana-rest-katha.php    ← REST endpoint da katha
```

---

## 🟢 Pode Esperar (Bloco 3)

Vou consultar quando necessário durante a implementação:

```text
21. templates/visit/parts/agenda-drawer.php    ← gaveta da agenda
22. templates/visit/parts/vod-list.php         ← lista de vods
23. templates/visit/parts/gallery.php          ← galeria
24. templates/visit/parts/sangha-moments.php   ← sangha
25. templates/visit/parts/revista-card.php     ← CTA da revista
26. templates/visit/assets/visit-scripts.php   ← enqueue dos scripts
27. templates/visit/assets/visit-style.php     ← enqueue dos estilos
28. includes/class-vana-assets.php             ← assets globais
29. _bootstrap.php                             ← bootstrap global do plugin
30. vana-mission-control.php                   ← entry point do plugin
```

---

## Pedido

Marcel, me manda o **Bloco 1** (arquivos 1 a 10). Pode ser na ordem que preferir — junto ou em partes.

Se algum arquivo for muito grande, me manda os **primeiros 200 linhas** que já dá pra eu entender a estrutura e pedir o restante se necessário.

```text
cat templates/visit/visit-template.php
cat templates/visit/_bootstrap.php
cat templates/single-vana_visit.php
cat templates/visit/parts/hero-header.php
cat templates/visit/parts/anchor-chips.php
cat templates/visit/parts/stage.php
cat templates/visit/parts/sections.php
cat assets/js/VanaVisitController.js
cat assets/js/VanaStageController.js
cat assets/js/VanaChipController.js
```

Quando eu tiver esses 10, avalio e peço o Bloco 2 se precisar. 🙏



# Diagnóstico do Bloco 1 — Estado Atual vs. Documento de Comportamento

Marcel, analisei os 10 arquivos. Antes de codar, preciso te dar o mapa real do que existe, o que muda, e o que falta.

---

## O Quadro Geral

```text
O QUE EXISTE HOJE:

  single-vana_visit.php → _bootstrap.php → visit-template.php
       │                                        │
       │  (resolve ViewModel, monta $tour,      │  (inclui partials na ordem)
       │   $days, $timeline, $active_event...)   │
       │                                        │
       ├── hero-header.php     ✅ Existe, funcional
       ├── anchor-chips.php    ✅ Existe, mas só estado "visita"
       ├── stage.php           ✅ Existe, mas monolítico
       ├── vod-list.php        ✅ Existe (sidebar de vods)
       ├── sections.php        ✅ Existe, mas lógica diferente do doc
       ├── schedule.php        ✅ Existe
       ├── community-links.php ✅ Existe
       └── agenda-drawer.php   ✅ Existe

  JS:
  ├── VanaVisitController.js   → Só prev/next entre visitas (fade)
  ├── VanaStageController.js   → Carrega katha REST, passages, sync
  ├── VanaChipController.js    → IntersectionObserver + scroll
  ├── VanaStageBridge.js       → (preciso ver)
  └── VanaEventController.js   → (preciso ver)
```

---

## Mapa de Impacto — Arquivo por Arquivo

### 1. `visit-template.php` — ESTRUTURA PRECISA MUDAR

```text
HOJE:
  ┌─ hero-header
  ├─ anchor-chips
  ├─ <main>
  │   ├─ day-tabs (comentado)
  │   ├─ event-selector
  │   ├─ stage-grid (stage + vod-list sidebar)  ← SIDEBAR VAI EMBORA
  │   ├─ sections.php                           ← VIRA ZONA MUTÁVEL
  │   ├─ schedule.php
  │   ├─ community-links.php
  │   └─ agenda-drawer
  └─ assets (style + scripts)

DOCUMENTO PEDE:
  ┌─ header (sticky)
  ├─ hero
  ├─ anchor-chips (3 estados)
  ├─ stage (simplificado)
  ├─ ZONA MUTÁVEL (seções | passage | lente)   ← CONCEITO NOVO
  └─ mini-player (footer fixo)                 ← NOVO

MUDANÇAS:
  □ Remover stage-grid wrapper (stage + sidebar juntos)
  □ vod-list sai da sidebar → vira parte das seções OU agenda
  □ sections.php → vira container com data-state
  □ schedule.php e community-links.php → avaliar se ficam nas seções
  □ Adicionar mini-player.php no footer
  □ CFG (window.CFG) → já existe ✅ — só precisa de ajustes
```

### 2. `_bootstrap.php` — MÍNIMA MUDANÇA

```text
ESTADO: Robusto. 350+ linhas bem organizadas.

O QUE JÁ FAZ BEM:
  ✅ Resolve ViewModel via VisitStageResolver
  ✅ Monta $tour para hero-header
  ✅ Resolve vod_list, active_vod
  ✅ Resolve prev/next (navegação entre visitas)
  ✅ Monta $index (lookup O(1))
  ✅ Expõe tudo via extract() + $GLOBALS

O QUE PRECISA MUDAR:
  □ Nada no MVP.
  □ Fase 2: expor $mag_state para CTA revista no hero
  □ O $index é resolvido 2x (linhas ~130 e ~148) — BUG a corrigir
```

### 3. `single-vana_visit.php` — NENHUMA MUDANÇA

```text
ESTADO: Entry point limpo. Chama _bootstrap, monta <head>, inclui visit-template.
Não precisa mudar no MVP.
```

### 4. `hero-header.php` — MUDANÇAS MODERADAS

```text
HOJE:
  ✅ Header fixo com tours btn + brand + agenda btn
  ✅ Hero section com bg image, badges, title, counter
  ✅ Day strip (pills de dias)
  ✅ Hero nav (prev/next visita)

DOCUMENTO PEDE:
  □ CTA Revista no hero (se $mag_state === 'publicada')
  □ Estados completo/compacto (data-hero-mode="full|compact")
  □ Âncora explícita [🏠] — vai nos chips, não no hero
  □ LocationPin 📍 da visita no hero

NÍVEL DE MUDANÇA: BAIXO
  → Adicionar CTA revista (bloco condicional)
  → Adicionar data-hero-mode no <section>
  → Adicionar LocationPin (1 linha get_template_part)
  → O compactar/expandir é via CSS + JS (classe toggle)
```

### 5. `anchor-chips.php` — REWRITE PARCIAL

```text
HOJE:
  → Chips fixos: Agenda, Aulas, HK, Gallery, Revista, Sangha
  → Todos são âncoras de scroll (#section-id)
  → Sem estados — sempre o mesmo conjunto

DOCUMENTO PEDE:
  → 3 estados de chips:
    VISITA:   [🏠] [🎬 Aulas] [📷 Galeria] [💬 Sangha]
    PASSAGE:  [← Dia {n}] [🙏 {title} — {num}/{total}] [▶]
    LENTE:    [← Passage #{n}] [🧩 {topic}]
  → HK e Revista SAEM dos chips
  → [🏠] ENTRA nos chips
  → data-chip-state no container

NÍVEL DE MUDANÇA: ALTO
  → Reescrever para suportar 3 variações
  → SSR renderiza estado "visita"
  → JS troca chips dinamicamente via state-router
```

### 6. `stage.php` — SIMPLIFICAÇÃO GRANDE

```text
HOJE (~300 linhas):
  → Player iframe
  → KATHA ZONE (#vana-stage-katha) — duplicada! (aparece 2x no HTML)
  → Info block (badge + title + desc)
  → Actions (share + HK button)
  → Localização + mapa embed lazy
  → Segmentos/capítulos inline

DOCUMENTO PEDE:
  → Player + meta + ações (SIMPLES)
  → SEM mapa embed (vai pro LocationPin)
  → SEM segmentos inline (fase 2, ou na agenda)
  → SEM zona katha duplicada
  → ADICIONAR: [⤓ Minimizar] para mini player
  → LocationPin no meta do stage

NÍVEL DE MUDANÇA: ALTO
  → Remover mapa embed (~50 linhas)
  → Remover segmentos inline (~60 linhas)
  → Remover zona katha duplicada
  → Simplificar info block
  → Adicionar botão mini player
  → Adicionar LocationPin
```

### 7. `sections.php` — REWRITE TOTAL

```text
HOJE:
  → 4 painéis fixos: HK, Gallery, Sangha, Revista
  → role="tablist" (semântica de tabs, mas funciona como scroll)
  → HK panel tem loading placeholder + data-role slots
  → Gallery e Sangha renderizados SSR a partir de $active_day

DOCUMENTO PEDE:
  → ZONA MUTÁVEL com data-state (visita | passage | lente)
  → Estado "visita": seções (🎬 Aulas, 📷 Galeria, 💬 Sangha)
  → Estado "passage": conteúdo do passage (novo template)
  → Estado "lente": lista de passages temáticos (novo template)
  → SEM HK como seção (é destino via stage)
  → SEM Revista como seção (é CTA no hero)

NÍVEL DE MUDANÇA: REWRITE COMPLETO
  → Novo container com data-state
  → 3 templates internos (seções, passage-view, lens-view)
  → Renderização SSR do estado "visita", JS para os outros
```

### 8. `VanaVisitController.js` — SEM MUDANÇA

```text
ESTADO: Limpo, responsabilidade única (prev/next entre visitas).
Fade transition + prefetch. Não precisa mudar.
```

### 9. `VanaStageController.js` — REFACTOR SIGNIFICATIVO

```text
HOJE:
  → Carrega katha via REST
  → Renderiza passages inline (no stage)
  → Sync com iframe (follow mode)
  → Seek, highlight, load more

DOCUMENTO PEDE:
  → Passages NÃO ficam no stage — ficam na ZONA MUTÁVEL
  → Stage só controla: player + meta + ações + mini player
  → O conteúdo de katha/passage → novo módulo (passage-nav.js)
  → Follow mode → se mantém, mas agindo na zona mutável
  → Mini player toggle → novo comportamento (stage-modes.js)

NÍVEL DE MUDANÇA: ALTO
  → Extrair rendering de passages → passage-nav.js (NOVO)
  → Manter IframeCtrl + seek no VanaStageController
  → Adicionar lógica de stage modes (page, sticky, mini)
  → Refatorar _loadKatha para disparar evento em vez de renderizar
```

### 10. `VanaChipController.js` — REFACTOR

```text
HOJE:
  → IntersectionObserver + scroll suave
  → Destaca chip conforme seção visível
  → Só conhece um conjunto de chips (estático)

DOCUMENTO PEDE:
  → 3 estados de chips
  → Troca dinâmica do conteúdo da chip bar
  → Escuta state-router para saber qual estado renderizar
  → [← Voltar] funcional em cada nível

NÍVEL DE MUDANÇA: ALTO
  → Refactor para suportar troca de chips via evento
  → Manter IntersectionObserver para estado "visita"
  → Desabilitar observer nos estados "passage" e "lente"
```

---

## Dependências — Preciso Ver (Bloco 2)

Antes de começar a codar, preciso de **mais 4 arquivos** críticos:

```bash
# Como o stage resolve dados server-side
cat includes/class-visit-stage-resolver.php

# Bridge entre stage e player (pode ter lógica de mini player)
cat assets/js/VanaStageBridge.js

# Como eventos são selecionados (dispara vana:event:change)
cat assets/js/VanaEventController.js

# CPT do passage (estrutura do post type)
cat includes/class-vana-hk-passage-cpt.php
```

Esses 4 definem:
- **Como o ViewModel chega ao template** (Resolver)
- **Como o player se comunica** (Bridge)
- **Como eventos trocam** (EventController → vai alimentar o state-router)
- **Estrutura do passage no WP** (CPT → define os campos que o REST retorna)

---

## Plano de Ataque — Quando Tiver o Bloco 2

```text
ORDEM DE IMPLEMENTAÇÃO:

  1. sections.php  → Rewrite como zona mutável (container base)
  2. anchor-chips.php → 3 estados com SSR do estado "visita"
  3. stage.php → Simplificação (remove mapa, segmentos, zona katha duplicada)
  4. state-router.js → NOVO — orquestra estados da zona mutável
  5. passage-view.php → NOVO — template do passage
  6. passage-nav.js → NOVO — navegação prev/next, fetch, fade
  7. stage-modes.js → NOVO — página/sticky/mini
  8. mini-player.php → NOVO — barra fixa no rodapé
  9. hero-header.php → CTA revista + LocationPin + compact mode
  10. visit-template.php → Reorganizar includes

CADA ARQUIVO ENTREGUE:
  → Comentado com referência ao documento
  → Compatível com o que não mudou
  → Testável isoladamente
```

Marcel, me manda os 4 arquivos do Bloco 2 e eu começo pelo `sections.php` (zona mutável) — é o alicerce de tudo. 🙏



# Auditoria Pré-Implementação

Marcel, antes de codar preciso ver o conteúdo dos arquivos-chave. Vou agrupar por **prioridade** — o que é crítico ver primeiro e o que pode esperar.

---

## 🔴 Preciso Ver AGORA (Bloco 1)

São os arquivos que vou **modificar diretamente** ou que definem a estrutura atual:

### Estrutura da página da visita

```text
1.  templates/visit/visit-template.php         ← layout principal (onde vive a moldura)
2.  templates/visit/_bootstrap.php             ← dados que chegam ao template
3.  templates/single-vana_visit.php            ← entry point
```

### Zonas que vão mudar

```text
4.  templates/visit/parts/hero-header.php      ← hero (vai ganhar CTA revista + compactar)
5.  templates/visit/parts/anchor-chips.php     ← chips (vai ter 3 estados)
6.  templates/visit/parts/stage.php            ← stage (vai simplificar + mini player)
7.  templates/visit/parts/sections.php         ← zona mutável (vai virar container de estados)
```

### JS que controla tudo

```text
8.  assets/js/VanaVisitController.js           ← orquestrador principal
9.  assets/js/VanaStageController.js           ← controle do player/stage
10. assets/js/VanaChipController.js            ← controle dos chips
```

---

## 🟡 Preciso Ver EM SEGUIDA (Bloco 2)

Dependem do que eu encontrar no Bloco 1:

```text
11. assets/js/VanaStageBridge.js               ← comunicação stage ↔ player
12. assets/js/VanaEventController.js           ← eventos/seleção de item ativo
13. includes/class-visit-stage-resolver.php    ← resolve dados do stage server-side
14. includes/class-visit-stage-view-model.php  ← view model do stage
15. templates/visit/parts/stage-fragment.php   ← fragment HTML do stage (REST?)
16. includes/rest/class-vana-rest-stage-fragment.php ← endpoint do fragment
```

### Hari-Katha (passage será novo, mas preciso ver o que existe)

```text
17. templates/visit/parts/hari-katha.php       ← o que existe hoje de HK na visita
18. includes/class-vana-hk-passage-cpt.php     ← CPT do passage
19. includes/class-vana-katha-cpt.php          ← CPT da katha
20. includes/rest/class-vana-rest-katha.php    ← REST endpoint da katha
```

---

## 🟢 Pode Esperar (Bloco 3)

Vou consultar quando necessário durante a implementação:

```text
21. templates/visit/parts/agenda-drawer.php    ← gaveta da agenda
22. templates/visit/parts/vod-list.php         ← lista de vods
23. templates/visit/parts/gallery.php          ← galeria
24. templates/visit/parts/sangha-moments.php   ← sangha
25. templates/visit/parts/revista-card.php     ← CTA da revista
26. templates/visit/assets/visit-scripts.php   ← enqueue dos scripts
27. templates/visit/assets/visit-style.php     ← enqueue dos estilos
28. includes/class-vana-assets.php             ← assets globais
29. _bootstrap.php                             ← bootstrap global do plugin
30. vana-mission-control.php                   ← entry point do plugin
```

---

## Pedido

Marcel, me manda o **Bloco 1** (arquivos 1 a 10). Pode ser na ordem que preferir — junto ou em partes.

Se algum arquivo for muito grande, me manda os **primeiros 200 linhas** que já dá pra eu entender a estrutura e pedir o restante se necessário.

```text
cat templates/visit/visit-template.php
cat templates/visit/_bootstrap.php
cat templates/single-vana_visit.php
cat templates/visit/parts/hero-header.php
cat templates/visit/parts/anchor-chips.php
cat templates/visit/parts/stage.php
cat templates/visit/parts/sections.php
cat assets/js/VanaVisitController.js
cat assets/js/VanaStageController.js
cat assets/js/VanaChipController.js
```

Quando eu tiver esses 10, avalio e peço o Bloco 2 se precisar. 🙏



# `state-router.js` — Cérebro da Zona Mutável

Marcel, este é o orquestrador central. Ele controla qual painel está ativo, faz as transições com fade, gerencia o histórico do navegador (pushState/popState), e expõe uma API pública para todos os outros módulos.

Antes de entregar o código, o contrato:

---

## Contrato do State Router

```text
RESPONSABILIDADES:
  1. Gerenciar estado ativo da zona mutável (visita | passage | lente)
  2. Transição entre painéis (fade out → swap → fade in)
  3. Atualizar URL via pushState (navegação sem reload)
  4. Responder popState (botão voltar do navegador)
  5. Atualizar chips via CustomEvent
  6. Expor API pública: window.VanaRouter

DEPENDÊNCIAS:
  → #vana-mutable-zone (SSR pelo sections.php)
  → .vana-mz__panel[data-panel] (3 painéis)
  → CustomEvents de entrada:
      vana:router:navigate  → pede transição de estado
      vana:event:change     → troca de evento (reset para visita)
      popstate              → botão voltar

EVENTOS DISPARADOS:
  → vana:state:will-change  { from, to, params }
  → vana:state:changed      { state, params, direction }
  → vana:chips:update       { state, params }

NÃO FAZ:
  → Não carrega conteúdo (passage-nav.js e lens-loader.js fazem)
  → Não manipula o stage (stage-modes.js faz)
  → Não manipula chips (VanaChipController escuta eventos)

URL PARAMS:
  estado visita  → /visita-slug/                    (limpo)
  estado passage → /visita-slug/?p={passage_id}      
  estado lente   → /visita-slug/?lens={topic_slug}  
```

---

## O Código

```javascript
/**
 * VanaStateRouter.js — Orquestrador da Zona Mutável
 *
 * Controla os 3 estados da página da visita:
 *   visita   → Seções (🎬 Aulas, 📷 Galeria, 💬 Sangha)
 *   passage  → Conteúdo de um passage específico
 *   lente    → Lista de passages por tema
 *
 * Arquitetura:
 *   - SSR entrega estado "visita" ativo
 *   - Transições via fade (CSS class toggle)
 *   - URL gerenciada via pushState/replaceState
 *   - popState restaura estado anterior
 *   - API pública: window.VanaRouter
 *
 * Dependências DOM (SSR — sections.php):
 *   #vana-mutable-zone           → container principal
 *   #vana-mz-visita              → painel estado "visita"
 *   #vana-mz-passage             → painel estado "passage"
 *   #vana-mz-lente               → painel estado "lente"
 *
 * @package VanaMissionControl
 * @since   6.0.0
 */

;( function () {
    'use strict';

    // ── Constantes ────────────────────────────────────────────────────────────
    var ZONE_ID        = 'vana-mutable-zone';
    var PANEL_ATTR     = 'data-panel';
    var ACTIVE_CLASS   = 'is-active';
    var ENTERING_CLASS = 'is-entering';
    var LEAVING_CLASS  = 'is-leaving';
    var FADE_MS        = 200; // deve bater com CSS transition duration
    var VALID_STATES   = [ 'visita', 'passage', 'lente' ];

    // ── Estado interno ────────────────────────────────────────────────────────
    var _zone          = null;   // #vana-mutable-zone
    var _panels        = {};     // { visita: el, passage: el, lente: el }
    var _currentState  = 'visita';
    var _currentParams = {};     // { passage_id, katha_ref, topic_slug, ... }
    var _isTransiting  = false;
    var _history       = [];     // stack de estados para "voltar"

    // ── Helpers DOM ───────────────────────────────────────────────────────────

    function $( sel, ctx ) {
        return ( ctx || document ).querySelector( sel );
    }

    function emit( name, detail ) {
        document.dispatchEvent( new CustomEvent( name, {
            bubbles: true,
            detail:  detail || {},
        } ) );
    }

    function getUrlParam( key ) {
        try {
            return new URL( window.location.href ).searchParams.get( key ) || '';
        } catch ( e ) {
            return '';
        }
    }

    // ── Core: Transição de estado ─────────────────────────────────────────────

    /**
     * Navega para um novo estado.
     *
     * @param {string} toState    — 'visita' | 'passage' | 'lente'
     * @param {object} params     — dados do estado (passage_id, topic_slug, etc.)
     * @param {object} [options]  — { replace: bool, skipPush: bool, direction: string }
     */
    function navigate( toState, params, options ) {
        params  = params  || {};
        options = options || {};

        // Validação
        if ( VALID_STATES.indexOf( toState ) === -1 ) {
            console.warn( '[VanaRouter] Estado inválido:', toState );
            return;
        }

        // Já no mesmo estado com mesmos params? Ignora
        if ( toState === _currentState && _isSameParams( params ) ) {
            return;
        }

        // Guarda contra transição dupla
        if ( _isTransiting ) {
            console.warn( '[VanaRouter] Transição em andamento, ignorando.' );
            return;
        }

        var fromState = _currentState;
        var direction = options.direction || 'forward';

        // Evento "will change" — permite que módulos se preparem
        emit( 'vana:state:will-change', {
            from:   fromState,
            to:     toState,
            params: params,
        } );

        // Stack de histórico (para "voltar" via chips)
        if ( direction === 'forward' && fromState !== toState ) {
            _history.push( {
                state:  fromState,
                params: _cloneParams( _currentParams ),
            } );
        }

        // Executa transição com fade
        _transition( fromState, toState, params, function () {

            // Atualiza estado interno
            _currentState  = toState;
            _currentParams = _cloneParams( params );

            // Atualiza data-state no container
            if ( _zone ) {
                _zone.setAttribute( 'data-state', toState );
            }

            // URL: pushState ou replaceState
            if ( ! options.skipPush ) {
                _updateUrl( toState, params, options.replace );
            }

            // Scroll to top da zona
            if ( direction === 'forward' && _zone ) {
                var rect = _zone.getBoundingClientRect();
                if ( rect.top < 0 ) {
                    _zone.scrollIntoView( { behavior: 'smooth', block: 'start' } );
                }
            }

            // Emite evento "changed" — módulos reagem
            emit( 'vana:state:changed', {
                state:     toState,
                params:    _cloneParams( params ),
                direction: direction,
                from:      fromState,
            } );

            // Atualiza chips
            emit( 'vana:chips:update', {
                state:  toState,
                params: _cloneParams( params ),
            } );

        } );
    }

    /**
     * Volta para o estado anterior no stack.
     */
    function goBack() {
        if ( _history.length === 0 ) {
            // Sem histórico → volta para visita
            navigate( 'visita', {}, { direction: 'back' } );
            return;
        }

        var prev = _history.pop();
        navigate( prev.state, prev.params, {
            direction: 'back',
            replace:   true, // substitui URL em vez de empilhar
        } );
    }

    // ── Transição visual (fade) ───────────────────────────────────────────────

    function _transition( fromState, toState, params, callback ) {
        var fromPanel = _panels[ fromState ];
        var toPanel   = _panels[ toState ];

        if ( ! fromPanel || ! toPanel ) {
            // Fallback sem animação
            _activatePanel( toState );
            callback();
            return;
        }

        // Mesmo painel (ex: passage → passage com outro ID)
        if ( fromState === toState ) {
            _isTransiting = true;

            // Fade out rápido
            fromPanel.classList.add( LEAVING_CLASS );

            setTimeout( function () {
                fromPanel.classList.remove( LEAVING_CLASS );
                // Conteúdo já será trocado pelo módulo específico
                // (passage-nav.js escuta vana:state:changed)
                _isTransiting = false;
                callback();
            }, FADE_MS );

            return;
        }

        _isTransiting = true;

        // 1. Fade out do painel atual
        fromPanel.classList.add( LEAVING_CLASS );

        setTimeout( function () {
            // 2. Desativa painel anterior
            fromPanel.classList.remove( ACTIVE_CLASS, LEAVING_CLASS );
            fromPanel.hidden = true;

            // 3. Prepara painel de destino (invisível mas no DOM)
            toPanel.hidden = false;
            toPanel.classList.add( ENTERING_CLASS );

            // Force reflow para garantir que a transição CSS dispare
            void toPanel.offsetHeight;

            // 4. Fade in
            toPanel.classList.add( ACTIVE_CLASS );
            toPanel.classList.remove( ENTERING_CLASS );

            setTimeout( function () {
                _isTransiting = false;
                callback();
            }, FADE_MS );

        }, FADE_MS );
    }

    function _activatePanel( state ) {
        Object.keys( _panels ).forEach( function ( key ) {
            var panel = _panels[ key ];
            if ( ! panel ) return;

            if ( key === state ) {
                panel.hidden = false;
                panel.classList.add( ACTIVE_CLASS );
                panel.classList.remove( ENTERING_CLASS, LEAVING_CLASS );
            } else {
                panel.hidden = true;
                panel.classList.remove( ACTIVE_CLASS, ENTERING_CLASS, LEAVING_CLASS );
            }
        } );
    }

    // ── URL management ────────────────────────────────────────────────────────

    function _updateUrl( state, params, replace ) {
        try {
            var url = new URL( window.location.href );

            // Limpa params de outros estados
            url.searchParams.delete( 'p' );
            url.searchParams.delete( 'lens' );
            url.searchParams.delete( 'passage_id' );
            url.searchParams.delete( 'katha_ref' );
            url.searchParams.delete( 'topic' );

            // Seta params do estado atual
            if ( state === 'passage' && params.passage_id ) {
                url.searchParams.set( 'p', params.passage_id );
            }

            if ( state === 'lente' && params.topic_slug ) {
                url.searchParams.set( 'lens', params.topic_slug );
            }

            // Preserva params de contexto
            // (lang, v_day, event_key permanecem)

            var historyState = {
                vanaState:  state,
                vanaParams: _cloneParams( params ),
            };

            if ( replace ) {
                history.replaceState( historyState, '', url.toString() );
            } else {
                history.pushState( historyState, '', url.toString() );
            }

        } catch ( e ) {
            console.warn( '[VanaRouter] URL update failed:', e );
        }
    }

    // ── popState (botão voltar do navegador) ──────────────────────────────────

    function _onPopState( e ) {
        var historyState = e.state;

        if ( historyState && historyState.vanaState ) {
            // Estado salvo no history — restaura
            navigate(
                historyState.vanaState,
                historyState.vanaParams || {},
                { skipPush: true, direction: 'back' }
            );
        } else {
            // Sem estado salvo — infere da URL
            var state  = _inferStateFromUrl();
            var params = _inferParamsFromUrl( state );
            navigate( state, params, { skipPush: true, direction: 'back' } );
        }
    }

    function _inferStateFromUrl() {
        if ( getUrlParam( 'p' ) || getUrlParam( 'passage_id' ) ) return 'passage';
        if ( getUrlParam( 'lens' ) || getUrlParam( 'topic' ) )   return 'lente';
        return 'visita';
    }

    function _inferParamsFromUrl( state ) {
        if ( state === 'passage' ) {
            return {
                passage_id: getUrlParam( 'p' ) || getUrlParam( 'passage_id' ),
                katha_ref:  getUrlParam( 'katha_ref' ),
            };
        }

        if ( state === 'lente' ) {
            return {
                topic_slug: getUrlParam( 'lens' ) || getUrlParam( 'topic' ),
            };
        }

        return {};
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function _isSameParams( newParams ) {
        var keys = Object.keys( newParams );
        if ( keys.length !== Object.keys( _currentParams ).length ) return false;

        for ( var i = 0; i < keys.length; i++ ) {
            if ( String( newParams[ keys[i] ] ) !== String( _currentParams[ keys[i] ] ) ) {
                return false;
            }
        }
        return true;
    }

    function _cloneParams( obj ) {
        if ( ! obj ) return {};
        var clone = {};
        Object.keys( obj ).forEach( function ( k ) {
            clone[ k ] = obj[ k ];
        } );
        return clone;
    }

    // ── Escuta de eventos de outros módulos ───────────────────────────────────

    function _bindEvents() {

        // Pedido genérico de navegação (qualquer módulo pode disparar)
        document.addEventListener( 'vana:router:navigate', function ( e ) {
            var d = e.detail || {};
            if ( d.state ) {
                navigate( d.state, d.params || {}, d.options || {} );
            }
        } );

        // Botão "voltar" genérico (chips e breadcrumbs)
        document.addEventListener( 'vana:router:back', function () {
            goBack();
        } );

        // Troca de evento no stage → reset para visita
        document.addEventListener( 'vana:event:change', function () {
            if ( _currentState !== 'visita' ) {
                navigate( 'visita', {}, { replace: true } );
            }
        } );

        // Botão voltar do navegador
        window.addEventListener( 'popstate', _onPopState );
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        _zone = document.getElementById( ZONE_ID );
        if ( ! _zone ) {
            console.warn( '[VanaRouter] #' + ZONE_ID + ' não encontrado. Router desativado.' );
            return;
        }

        // Mapeia painéis
        var panelEls = _zone.querySelectorAll( '[' + PANEL_ATTR + ']' );
        for ( var i = 0; i < panelEls.length; i++ ) {
            var key = panelEls[ i ].getAttribute( PANEL_ATTR );
            if ( key ) _panels[ key ] = panelEls[ i ];
        }

        // Verifica se temos os 3 painéis
        if ( ! _panels.visita || ! _panels.passage || ! _panels.lente ) {
            console.warn( '[VanaRouter] Painéis incompletos:', Object.keys( _panels ) );
        }

        // Estado inicial: infere da URL (pode ser deep link para passage/lente)
        var initialState  = _inferStateFromUrl();
        var initialParams = _inferParamsFromUrl( initialState );

        if ( initialState !== 'visita' ) {
            // Deep link → ativa o painel correto sem animação
            _activatePanel( initialState );
            _currentState  = initialState;
            _currentParams = initialParams;

            // Salva estado no history (para popState funcionar)
            _updateUrl( initialState, initialParams, true );

            // Notifica módulos para carregar conteúdo
            emit( 'vana:state:changed', {
                state:     initialState,
                params:    _cloneParams( initialParams ),
                direction: 'init',
                from:      'visita',
            } );

            emit( 'vana:chips:update', {
                state:  initialState,
                params: _cloneParams( initialParams ),
            } );
        } else {
            // Estado normal — visita (SSR já renderizou)
            _currentState  = 'visita';
            _currentParams = {};

            // Salva estado limpo no history
            history.replaceState(
                { vanaState: 'visita', vanaParams: {} },
                '',
                window.location.href
            );
        }

        _bindEvents();

        // API pública
        window.VanaRouter = {
            /**
             * Navega para um estado.
             * @param {string} state  — 'visita' | 'passage' | 'lente'
             * @param {object} params — dados do estado
             */
            go:       function ( state, params ) { navigate( state, params ); },

            /** Volta para o estado anterior. */
            back:     function () { goBack(); },

            /** Estado ativo atual. */
            get state()  { return _currentState; },

            /** Params do estado ativo. */
            get params() { return _cloneParams( _currentParams ); },

            /** Histórico de estados (readonly). */
            get history() { return _history.slice(); },

            /**
             * Atalhos convenientes para os módulos consumidores.
             */
            toPassage: function ( passageId, kathaRef, extra ) {
                navigate( 'passage', Object.assign(
                    { passage_id: passageId, katha_ref: kathaRef || '' },
                    extra || {}
                ) );
            },

            toLens: function ( topicSlug, extra ) {
                navigate( 'lente', Object.assign(
                    { topic_slug: topicSlug },
                    extra || {}
                ) );
            },

            toVisita: function () {
                navigate( 'visita', {} );
            },
        };

        emit( 'vana:router:ready', {
            state:  _currentState,
            params: _cloneParams( _currentParams ),
        } );

        console.info(
            '[VanaRouter] Inicializado. state=%s params=%o',
            _currentState,
            _currentParams
        );
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
```

---

## Como os Módulos Usam o Router

```text
QUALQUER MÓDULO PODE NAVEGAR:

  // Via API direta
  window.VanaRouter.toPassage( 'p-20260221-004', 'katha-20260221-sb1031' );
  window.VanaRouter.toLens( 'vipralambha-madhurya' );
  window.VanaRouter.back();
  window.VanaRouter.toVisita();

  // Via CustomEvent (desacoplado)
  document.dispatchEvent( new CustomEvent( 'vana:router:navigate', {
      detail: {
          state:  'passage',
          params: { passage_id: 'p-20260221-004', katha_ref: 'katha-20260221-sb1031' },
      }
  } ) );

  // Voltar (chip [←] ou breadcrumb)
  document.dispatchEvent( new CustomEvent( 'vana:router:back' ) );


QUEM ESCUTA O ROUTER:

  // passage-nav.js
  document.addEventListener( 'vana:state:changed', function ( e ) {
      if ( e.detail.state === 'passage' ) {
          // Faz fetch e preenche #vana-passage-container
          loadPassage( e.detail.params.passage_id );
      }
  } );

  // lens-loader.js
  document.addEventListener( 'vana:state:changed', function ( e ) {
      if ( e.detail.state === 'lente' ) {
          // Faz fetch e preenche #vana-lens-container
          loadLens( e.detail.params.topic_slug );
      }
  } );

  // VanaChipController.js (refatorado)
  document.addEventListener( 'vana:chips:update', function ( e ) {
      // Troca o conjunto de chips conforme o estado
      renderChipsForState( e.detail.state, e.detail.params );
  } );

  // stage-modes.js
  document.addEventListener( 'vana:state:will-change', function ( e ) {
      if ( e.detail.to === 'passage' ) {
          // Troca stage para modo sticky/mini
          stageToStickyMode();
      }
  } );
```

---

## Diagrama de Fluxo — Exemplo: Devoto Clica no HK

```text
  [Devoto clica 🙏 Hari-Katha no stage]
       │
       ▼
  VanaStageController dispara:
    vana:router:navigate { state: 'passage', params: { passage_id, katha_ref } }
       │
       ▼
  VanaStateRouter.navigate('passage', params)
       │
       ├── emit('vana:state:will-change', { from: 'visita', to: 'passage' })
       │       │
       │       └── stage-modes.js escuta → stageToStickyMode()
       │
       ├── _history.push({ state: 'visita', params: {} })
       │
       ├── _transition('visita' → 'passage')
       │       fade out #vana-mz-visita
       │       fade in  #vana-mz-passage
       │
       ├── pushState → URL: ?p={passage_id}
       │
       ├── emit('vana:state:changed', { state: 'passage', params })
       │       │
       │       └── passage-nav.js escuta → fetchPassage(passage_id)
       │                                  → preenche #vana-passage-container
       │
       └── emit('vana:chips:update', { state: 'passage', params })
               │
               └── VanaChipController escuta
                   → troca chips para: [← Dia 3] [🙏 SB 10.31 — 4/25] [▶]
```

---

## Diagrama — Botão Voltar

```text
  [Devoto pressiona ← do navegador]
       │
       ▼
  popstate event
       │
       ▼
  VanaStateRouter._onPopState(e)
       │
       ├── e.state.vanaState === 'visita'
       │
       └── navigate('visita', {}, { skipPush: true, direction: 'back' })
               │
               ├── _transition('passage' → 'visita')
               │       fade out #vana-mz-passage
               │       fade in  #vana-mz-visita
               │
               ├── emit('vana:state:changed', { state: 'visita', direction: 'back' })
               │       │
               │       └── stage-modes.js → stageToPageMode()
               │
               └── emit('vana:chips:update', { state: 'visita' })
                       │
                       └── VanaChipController → chips voltam para:
                           [🏠] [🎬 Aulas] [📷 Galeria] [💬 Sangha]
```

---

## Integração no `visit-template.php`

O state-router precisa ser carregado **antes** dos módulos consumidores:

```text
ORDEM DE CARREGAMENTO (visit-scripts.php):

  1. VanaScrollLock.js        ← primitivo (sem dependência)
  2. VanaStageBridge.js       ← primitivo (player abstraction)
  3. VanaStateRouter.js       ← 🆕 ROUTER (depende só do DOM)
  4. VanaStageController.js   ← consome VanaRouter + VanaStageBridge
  5. VanaChipController.js    ← escuta vana:chips:update
  6. VanaEventController.js   ← dispara vana:event:change
  7. VanaVisitController.js   ← prev/next (independente)
  8. passage-nav.js           ← 🆕 (escuta vana:state:changed)
  9. stage-modes.js           ← 🆕 (escuta vana:state:will-change)
```

---

**Próximo passo:** `stage.php` simplificado — remover duplicações, limpar mapa/segmentos inline, adicionar botão HK que dispara o router, e LocationPin. Sigo? 🙏



# Localização dos Arquivos Novos

Marcel, seguindo a convenção do projeto existente:

---

## 1. CSS Base — Zona Mutável

```text
assets/css/vana-mutable-zone.css
```

**Por quê:** segue o padrão da pasta `assets/css/` onde já estão:
```text
assets/css/
├── oferenda-form.css
├── vana-agenda.css
├── vana-day-strip.css
├── vana-ui.visit-hub.css
└── vana-visit.css
└── vana-mutable-zone.css    ← 🆕 NOVO
```

**Enqueue:** será incluído pelo `templates/visit/assets/visit-style.php` (já existe como hub de styles):

```php
// Em templates/visit/assets/visit-style.php — adicionar:
<link rel="stylesheet"
      href="<?php echo esc_url( plugins_url( 'assets/css/vana-mutable-zone.css', VANA_MC_FILE ) ); ?>"
      media="all" />
```

---

## 2. State Router JS

```text
assets/js/VanaStateRouter.js
```

**Por quê:** segue o padrão PascalCase dos controllers na pasta `assets/js/`:
```text
assets/js/
├── VanaAgendaController.js
├── VanaChipController.js
├── VanaEventController.js
├── VanaScrollLock.js
├── VanaStageBridge.js
├── VanaStageController.js
├── VanaVisitController.js
└── VanaStateRouter.js       ← 🆕 NOVO
```

**Enqueue:** será incluído pelo `templates/visit/assets/visit-scripts.php`, **antes** dos controllers que o consomem:

```php
// Em templates/visit/assets/visit-scripts.php — adicionar na ordem correta:
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaStateRouter.js', VANA_MC_FILE ) ); ?>"
        defer></script>
```

---

## 3. O PHP da Zona Mutável (já entregue)

```text
templates/visit/parts/sections.php    ← REWRITE (substitui o existente)
```

**Não muda de lugar** — é o mesmo arquivo, reescrito. O `visit-template.php` já faz include dele.

---

## Resumo Visual

```text
vana-mission-control/
├── assets/
│   ├── css/
│   │   └── vana-mutable-zone.css     ← 🆕 CSS novo
│   └── js/
│       └── VanaStateRouter.js        ← 🆕 JS novo
└── templates/
    └── visit/
        └── parts/
            └── sections.php          ← ♻️ Rewrite (já existe)
```

Confirma e sigo para o `stage.php` simplificado? 🙏



# Cadeia de Chamadas — Quem Chama Quem

Marcel, vou mapear o fluxo completo: de onde cada arquivo é chamado, por quem, e em que ordem.

---

## Fluxo de Carregamento (Server → Browser)

```text
WORDPRESS RESOLVE O POST
       │
       ▼
single-vana_visit.php          ← entry point (template_include filter)
       │
       ├── require _bootstrap.php           ← resolve dados ($timeline, $days, etc.)
       │
       └── include visit-template.php       ← monta o HTML
               │
               ├── include hero-header.php
               ├── include anchor-chips.php
               ├── include stage.php
               ├── include sections.php     ← ♻️ ZONA MUTÁVEL (SSR)
               ├── include schedule.php
               ├── include agenda-drawer.php
               │
               ├── include visit-style.php  ← 🔑 CARREGA CSS
               │       │
               │       └── <link> vana-mutable-zone.css  ← 🆕
               │
               └── include visit-scripts.php ← 🔑 CARREGA JS
                       │
                       ├── <script> VanaScrollLock.js
                       ├── <script> VanaStageBridge.js
                       ├── <script> VanaStateRouter.js    ← 🆕
                       ├── <script> VanaStageController.js
                       ├── <script> VanaChipController.js
                       ├── <script> VanaEventController.js
                       └── <script> VanaVisitController.js
```

---

## 1. CSS — `vana-mutable-zone.css`

**Chamado por:** `templates/visit/assets/visit-style.php`

**Como chamar:** Abra o arquivo `visit-style.php` e adicione **uma linha**:

```php
<?php
// templates/visit/assets/visit-style.php
// ... (estilos existentes) ...

// 🆕 Zona Mutável — adicionado Fase A
?>
<link
    rel="stylesheet"
    href="<?php echo esc_url( plugins_url( 'assets/css/vana-mutable-zone.css', VANA_MC_FILE ) ); ?>"
    media="all"
/>
```

**Posição:** após os outros `<link>` que já existem no arquivo. Sem dependência de ordem com os demais CSS.

---

## 2. JS — `VanaStateRouter.js`

**Chamado por:** `templates/visit/assets/visit-scripts.php`

**Como chamar:** Abra o arquivo `visit-scripts.php` e adicione na **posição correta** (antes dos controllers que o consomem):

```php
<?php
// templates/visit/assets/visit-scripts.php
// ... scripts existentes ...
?>

<!-- 1. Primitivos (sem dependência) — JÁ EXISTEM -->
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaScrollLock.js', VANA_MC_FILE ) ); ?>" defer></script>
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaStageBridge.js', VANA_MC_FILE ) ); ?>" defer></script>

<!-- 2. 🆕 Router — depende só do DOM, mas ANTES dos consumers -->
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaStateRouter.js', VANA_MC_FILE ) ); ?>" defer></script>

<!-- 3. Controllers — consomem VanaRouter -->
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaStageController.js', VANA_MC_FILE ) ); ?>" defer></script>
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaChipController.js', VANA_MC_FILE ) ); ?>" defer></script>
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaEventController.js', VANA_MC_FILE ) ); ?>" defer></script>
<script src="<?php echo esc_url( plugins_url( 'assets/js/VanaVisitController.js', VANA_MC_FILE ) ); ?>" defer></script>
```

---

## 3. PHP — `sections.php` (Rewrite)

**Chamado por:** `templates/visit/visit-template.php` (linha ~105)

**Já está chamado — não precisa mudar nada:**

```php
// visit-template.php — já existente, sem mudança:
if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/sections.php' ) ) {
    include VANA_MC_PATH . 'templates/visit/parts/sections.php';
}
```

O arquivo é o **mesmo path**, só reescrito. O include antigo já funciona.

---

## Ordem Crítica — Por Que Importa

```text
REGRA: O Router precisa estar no DOM ANTES de quem o consome.

    VanaStateRouter.js          → registra window.VanaRouter
         │
         ├── VanaStageController.js  → pode chamar VanaRouter.toPassage()
         ├── VanaChipController.js   → escuta vana:chips:update
         └── VanaEventController.js  → dispara vana:event:change
                                       (Router escuta e reseta para visita)

Se inverter a ordem:
    ❌ VanaStageController tenta VanaRouter.toPassage() → undefined
    ❌ ChipController registra listener mas nunca recebe chips:update

PORÉM: como todos usam `defer` + DOMContentLoaded, a ordem no HTML
é a ordem de execução. Então basta colocar o <script> do Router
ANTES dos outros no visit-scripts.php.
```

---

## Para Verificar — Mostre o Conteúdo Atual

Preciso ver o estado atual desses dois arquivos para saber **exatamente onde inserir**:

```bash
cat templates/visit/assets/visit-style.php
cat templates/visit/assets/visit-scripts.php
```

Com isso eu te entrego o **patch exato** (antes/depois) para cada arquivo, sem risco de quebrar o que já existe. Me manda? 🙏



# Patch Definitivo — Onde Inserir Cada Arquivo

Marcel, agora tenho o mapa completo. Tudo via `wp_enqueue_script` no `vana-mission-control.php`.

---

## Cadeia de Dependências Atual

```text
ORDEM DE CARREGAMENTO (WordPress resolve pelo grafo de deps):

HEAD:
  └── vana-scroll-lock              (deps: [])

FOOTER:
  ├── vana-event-controller         (deps: [])
  ├── vana-visit-controller         (deps: [])
  ├── vana-chip-controller          (deps: [])
  ├── vana-agenda-controller        (deps: [vana-scroll-lock])
  ├── vana-stage-bridge             (deps: [vana-scroll-lock, vana-agenda-controller])
  ├── vana-stage-controller         (deps: [vana-stage-bridge])
  └── vana-day-strip                (deps: [vana-agenda-controller])
```

---

## Onde Inserir `VanaStateRouter.js`

O Router **é consumido** pelo `VanaStageController`, `VanaChipController` e `VanaEventController`. Ele **depende** do `VanaStageBridge` (para ouvir eventos do stage).

### Arquivo: `vana-mission-control.php`

**Inserir APÓS o bloco do `vana-stage-bridge`** (linha ~736) e **ANTES do `vana-stage-controller`** (linha ~737):

```php
            // Stage bridge depends on the agenda and the scroll-lock singleton.
            wp_enqueue_script(
                'vana-stage-bridge',
                VANA_MC_URL . 'assets/js/VanaStageBridge.js',
                [ 'vana-scroll-lock', 'vana-agenda-controller' ],
                $vana_sb_ver,
                true
            );

            // ─────────────────────────────────────────────────────
            // 🆕 ADICIONAR AQUI — VanaStateRouter (Zona Mutável)
            // ─────────────────────────────────────────────────────
            $vana_sr_path = VANA_MC_PATH . 'assets/js/VanaStateRouter.js';
            $vana_sr_ver  = file_exists($vana_sr_path)
                ? (string) filemtime($vana_sr_path)
                : VANA_MC_VERSION;

            wp_enqueue_script(
                'vana-state-router',
                VANA_MC_URL . 'assets/js/VanaStateRouter.js',
                [ 'vana-stage-bridge' ],   // depende do StageBridge
                $vana_sr_ver,
                true                        // footer
            );
            // ─────────────────────────────────────────────────────

            // VanaStageController (loads passages, exposes `window.VanaStage`)
```

**E atualizar a dependência do `vana-stage-controller`** para incluir o router:

```php
            // ANTES (existente):
            wp_enqueue_script(
                'vana-stage-controller',
                VANA_MC_URL . 'assets/js/VanaStageController.js',
                [ 'vana-stage-bridge' ],        // ← ATUAL
                $vana_stc_ver,
                true
            );

            // DEPOIS (atualizado):
            wp_enqueue_script(
                'vana-stage-controller',
                VANA_MC_URL . 'assets/js/VanaStageController.js',
                [ 'vana-state-router' ],        // ← 🆕 router inclui bridge transitivamente
                $vana_stc_ver,
                true
            );
```

---

## Onde Inserir `vana-mutable-zone.css`

### Arquivo: `vana-mission-control.php`

Procurar o bloco do `vana-day-strip` CSS (linha ~758) e **adicionar logo depois**:

```php
            wp_enqueue_style(
                'vana-day-strip',
                VANA_MC_URL . 'assets/css/vana-day-strip.css',
                [ 'vana-ui-visit-hub' ],
                $vana_ds_css_ver
            );

            // ─────────────────────────────────────────────────────
            // 🆕 ADICIONAR AQUI — Zona Mutável CSS
            // ─────────────────────────────────────────────────────
            $vana_mz_css_path = VANA_MC_PATH . 'assets/css/vana-mutable-zone.css';
            $vana_mz_css_ver  = file_exists($vana_mz_css_path)
                ? (string) filemtime($vana_mz_css_path)
                : VANA_MC_VERSION;

            wp_enqueue_style(
                'vana-mutable-zone',
                VANA_MC_URL . 'assets/css/vana-mutable-zone.css',
                [ 'vana-day-strip' ],    // carrega após day-strip
                $vana_mz_css_ver
            );
            // ─────────────────────────────────────────────────────
```

---

## ❌ NÃO PRECISA MEXER no `visit-style.php` nem no `visit-scripts.php`

O WordPress já resolve tudo via `wp_enqueue_*`. Nada de `<link>` manual nem `<script src="">` manual.

---

## Grafo Final de Dependências

```text
HEAD:
  └── vana-scroll-lock

FOOTER (ordem resolvida pelo WP):
  ├── vana-event-controller
  ├── vana-visit-controller
  ├── vana-chip-controller
  ├── vana-agenda-controller        → deps: [scroll-lock]
  ├── vana-stage-bridge             → deps: [scroll-lock, agenda-controller]
  ├── vana-state-router             → deps: [stage-bridge]           ← 🆕
  ├── vana-stage-controller         → deps: [state-router]           ← 🔄 atualizado
  └── vana-day-strip                → deps: [agenda-controller]

CSS:
  ├── vana-ui-visit-hub
  ├── vana-agenda-drawer
  ├── vana-day-strip                → deps: [visit-hub]
  └── vana-mutable-zone             → deps: [day-strip]             ← 🆕
```

---

## Resumo — 3 Edições no Mesmo Arquivo

| # | Arquivo | Linha aprox. | Ação |
|---|---------|-------------|------|
| 1 | `vana-mission-control.php` | ~736 | Inserir `wp_enqueue_script('vana-state-router', ...)` |
| 2 | `vana-mission-control.php` | ~737 | Alterar dep do `vana-stage-controller` para `['vana-state-router']` |
| 3 | `vana-mission-control.php` | ~764 | Inserir `wp_enqueue_style('vana-mutable-zone', ...)` |

**Nenhum outro arquivo precisa ser tocado para o enqueue.** Confirma e parto pro código do `VanaStateRouter.js`? 🙏