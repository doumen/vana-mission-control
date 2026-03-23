# Bloco A — Instrução Completa para o Agente

Marcel, aqui está a instrução precisa para o agente implementar o `VanaVisitController.js`.

---

## Instrução para o Agente

```text
OBJETIVO: Criar VanaVisitController.js
PATH: wp-content/plugins/vana-mission-control/assets/js/VanaVisitController.js
RESPONSABILIDADE: Navegação entre visitas (prev/next)
ESTRATÉGIA: Prefetch + transição CSS + link direto
```

---

### PASSO A.2.1 — Criar o Arquivo

```text
Criar o arquivo com a seguinte estrutura exata:
```

```javascript
/**
 * VanaVisitController.js
 *
 * Responsabilidade única: navegação entre visitas (prev/next).
 * Estratégia: prefetch silencioso + transição fade + link direto.
 *
 * DT-004: tour_id é APENAS contexto visual (passthrough na URL).
 *         Nunca é filtro de navegação.
 *
 * @package Vana Mission Control
 */

( function () {
    'use strict';

    // ── 1. Leitura do contexto SSR ────────────────────────────────────────────
    const stageEl   = document.getElementById( 'vana-stage' );
    const prevBtn   = document.querySelector( '[data-vana-prev-visit]' );
    const nextBtn   = document.querySelector( '[data-vana-next-visit]' );

    const prevUrl   = prevBtn ? prevBtn.dataset.vanaVisitUrl : null;
    const nextUrl   = nextBtn ? nextBtn.dataset.vanaVisitUrl : null;

    // DT-004: tour_id lido do DOM, nunca manipula navegação
    const tourId    = stageEl ? ( stageEl.dataset.tourId || null ) : null;

    // ── 2. Prefetch silencioso ────────────────────────────────────────────────
    function prefetchUrl( url ) {
        if ( ! url ) return;
        if ( document.querySelector( `link[rel="prefetch"][href="${url}"]` ) ) return;

        const link  = document.createElement( 'link' );
        link.rel    = 'prefetch';
        link.href   = url;
        link.as     = 'document';
        document.head.appendChild( link );
    }

    // ── 3. Montar URL com tour passthrough (DT-004) ──────────────────────────
    function buildDestUrl( baseUrl ) {
        if ( ! baseUrl ) return null;
        if ( ! tourId  ) return baseUrl;

        try {
            const url = new URL( baseUrl, window.location.origin );
            url.searchParams.set( 'tour', tourId );
            return url.toString();
        } catch ( e ) {
            return baseUrl;
        }
    }

    // ── 4. Transição de saída e navegação ────────────────────────────────────
    function navigateTo( destUrl ) {
        if ( ! destUrl ) return;

        const body = document.body;
        body.style.transition = 'opacity .15s ease';
        body.style.opacity    = '0';

        setTimeout( () => {
            window.location.href = destUrl;
        }, 160 );
    }

    // ── 5. Bind de clique nos botões prev/next ────────────────────────────────
    function bindNavButton( btn, baseUrl ) {
        if ( ! btn || ! baseUrl ) return;

        const destUrl = buildDestUrl( baseUrl );

        btn.addEventListener( 'click', function ( e ) {
            e.preventDefault();
            navigateTo( destUrl );
        } );

        // Acessibilidade: Enter e Space
        btn.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' || e.key === ' ' ) {
                e.preventDefault();
                navigateTo( destUrl );
            }
        } );
    }

    // ── 6. Transição de entrada (fade in ao carregar) ─────────────────────────
    function initFadeIn() {
        document.body.style.opacity    = '0';
        document.body.style.transition = 'opacity .2s ease';
        requestAnimationFrame( () => {
            requestAnimationFrame( () => {
                document.body.style.opacity = '1';
            } );
        } );
    }

    // ── 7. Disparo de evento para outros módulos ──────────────────────────────
    function dispatchReady() {
        document.dispatchEvent( new CustomEvent( 'vana:visit:controller:ready', {
            bubbles : false,
            detail  : {
                prevUrl : buildDestUrl( prevUrl ),
                nextUrl : buildDestUrl( nextUrl ),
                tourId  : tourId,
            },
        } ) );
    }

    // ── 8. Init ───────────────────────────────────────────────────────────────
    function init() {
        initFadeIn();
        prefetchUrl( buildDestUrl( prevUrl ) );
        prefetchUrl( buildDestUrl( nextUrl ) );
        bindNavButton( prevBtn, prevUrl );
        bindNavButton( nextBtn, nextUrl );
        dispatchReady();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
```

---

### PASSO A.2.2 — Verificar data-attributes no Template

```text
TAREFA: Verificar se os botões prev/next no template
        já usam os data-attributes corretos:

          data-vana-prev-visit
          data-vana-next-visit
          data-vana-visit-url="[permalink]"

ARQUIVO A VERIFICAR:
  templates/visit/_bootstrap.php (ou partial de navegação)

SE NÃO EXISTIREM:
  Reportar ao Marcel quais atributos existem atualmente
  NÃO alterar o template ainda — apenas reportar
```

---

### PASSO A.2.3 — Registrar no Plugin

```text
TAREFA: Verificar onde vana-event-controller.js
        é enqueued no plugin (functions.php ou
        arquivo de enqueue)

LOCALIZAR a linha de wp_enqueue_script para
vana-event-controller.js e reportar ao Marcel

NÃO adicionar enqueue ainda — apenas reportar
onde deve ser adicionado
```

---

### PASSO A.3 — Gate de Conclusão do Bloco A

```text
O agente deve reportar:

  [ ] Arquivo VanaVisitController.js criado
  [ ] Conteúdo completo do arquivo mostrado
  [ ] Resultado da verificação dos data-attributes
      no template de navegação
  [ ] Linha de enqueue identificada no plugin
  [ ] JS lint sem erros (node --check ou similar)
  [ ] Aguardar aprovação de Marcel
```

---

```text
SEQUÊNCIA FASE E — STATUS:

  [x] PASSO 0  → DT-004 PHP implementado ✅
  [ ] BLOCO A  → VanaVisitController.js (AGENTE EXECUTANDO)
  [ ] BLOCO B  → Tour Drawer JS
  [ ] BLOCO C  → Chip Bar + Sections JS
  [ ] BLOCO D  → Agenda Drawer JS
```

Passa para o agente, Marcel. Ele deve criar o arquivo, verificar os data-attributes e identificar o enqueue — **sem alterar templates ainda**. 🙏