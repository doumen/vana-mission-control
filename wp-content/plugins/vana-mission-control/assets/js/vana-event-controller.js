/**
 * VanaEventController
 *
 * Orquestra a troca de evento ativo no Stage sem page reload.
 * Usa HTMX + /wp-json/vana/v1/stage-fragment para buscar HTML parcial.
 *
 * Contrato com o DOM:
 *   [data-vana-event-key]   → botão seletor de evento
 *   #vana-stage             → target do player (substituído via HTMX)
 *   #vana-stage-info        → target da info (substituído via HTMX)
 *   .vana-event-btn--active → classe do item ativo
 *
 * @since 5.2.0
 */

( function () {
    'use strict';

    // ── Constantes ────────────────────────────────────────
    const ENDPOINT_V2 = '/wp-json/vana/v1/stage';
    const ENDPOINT_FALLBACK = '/wp-json/vana/v1/stage-fragment';
    const CLS_ACTIVE = 'vana-event-btn--active';
    const CLS_LOAD   = 'vana-event-btn--loading';

    // ── Estado interno ────────────────────────────────────
    let currentEventKey = null;
    let inflightRequest = null;

    // ── Utilitários ───────────────────────────────────────

    /**
     * Lê data attributes do botão selecionado.
     * @param {HTMLElement} btn
     * @returns {{ eventKey: string, visitId: string, lang: string }}
     */
    function readBtnData( btn ) {
        return {
            eventKey : btn.dataset.vanaEventKey   || '',
            visitId  : btn.dataset.vanaVisitId    || '',
            lang     : btn.dataset.vanaLang       || 'pt',
        };
    }

    /**
    * Monta URL do endpoint semantico com query params.
    * /stage-fragment permanece como fallback e nao e removido.
     *
     * @param {string} visitId
     * @param {string} eventKey   (ex: "2026-03-21")
     * @param {string} lang
     * @returns {string}
     */
    function buildUrl( visitId, eventKey, lang ) {
        const base = ENDPOINT_V2 + '/' + encodeURIComponent( eventKey );
        const params = new URLSearchParams({
            visit_id : visitId,
            lang     : lang,
        });
        return base + '?' + params.toString();
    }

    /**
     * URL legado para compatibilidade com /stage-fragment.
     *
     * @param {string} visitId
     * @param {string} eventKey
     * @param {string} lang
     * @returns {string}
     */
    function buildLegacyUrl( visitId, eventKey, lang ) {
        const params = new URLSearchParams({
            visit_id  : visitId,
            item_id   : eventKey,
            item_type : 'event',
            lang      : lang,
        });
        return ENDPOINT_FALLBACK + '?' + params.toString();
    }

    // ── Feedback visual ───────────────────────────────────

    function setLoading( btn, isLoading ) {
        btn.classList.toggle( CLS_LOAD, isLoading );
        btn.setAttribute( 'aria-busy', isLoading ? 'true' : 'false' );
    }

    function setActive( btn ) {
        document
            .querySelectorAll( '[data-vana-event-key]' )
            .forEach( b => {
                b.classList.remove( CLS_ACTIVE );
                b.setAttribute( 'aria-current', 'false' );
            });

        btn.classList.add( CLS_ACTIVE );
        btn.setAttribute( 'aria-current', 'true' );
    }

    // ── Swap do Stage ─────────────────────────────────────

    /**
     * Injeta o HTML recebido no #vana-stage via innerHTML.
     * Executa <script> inline presentes no fragmento.
     *
     * @param {string} html
     */
    function swapStage( html ) {
        const target = document.getElementById( 'vana-stage' );
        if ( ! target ) {
            console.warn( '[VanaEventController] #vana-stage não encontrado.' );
            return;
        }

        // Animação de saída
        target.style.opacity    = '0';
        target.style.transition = 'opacity .15s ease';

        setTimeout( () => {
            target.innerHTML = html;

            // Re-executa scripts inline no fragmento
            target.querySelectorAll( 'script' ).forEach( oldScript => {
                const s   = document.createElement( 'script' );
                s.textContent = oldScript.textContent;
                oldScript.replaceWith( s );
            });

            // Animação de entrada
            target.style.opacity = '1';

            // Dispara evento customizado para outros módulos
            target.dispatchEvent( new CustomEvent( 'vana:stage:swapped', {
                bubbles : true,
                detail  : { eventKey: currentEventKey },
            }));
        }, 150 );
    }

    // ── Fetch principal ───────────────────────────────────

    /**
     * Busca fragmento HTML do stage para um event_key.
     *
     * @param {HTMLElement} btn   Botão que disparou o evento
     * @param {object}      data  Output de readBtnData()
     */
    async function fetchStage( btn, data ) {
        const { eventKey, visitId, lang } = data;

        // Guard: não rebusca o evento já ativo
        if ( eventKey === currentEventKey ) return;

        // Cancela request anterior se ainda em curso
        if ( inflightRequest ) {
            inflightRequest.abort();
        }

        const controller  = new AbortController();
        inflightRequest   = controller;

        setLoading( btn, true );

        try {
            // Tenta endpoint novo primeiro.
            let res = await fetch( buildUrl( visitId, eventKey, lang ), {
                signal  : controller.signal,
                headers : { 'X-Requested-With': 'XMLHttpRequest' },
            });

            // Fallback automatico para rota legada quando o v2 falhar.
            if ( ! res.ok ) {
                res = await fetch( buildLegacyUrl( visitId, eventKey, lang ), {
                    signal  : controller.signal,
                    headers : { 'X-Requested-With': 'XMLHttpRequest' },
                });
            }

            if ( ! res.ok ) {
                throw new Error( `HTTP ${ res.status }` );
            }

            const html = await res.text();

            currentEventKey = eventKey;
            setActive( btn );
            swapStage( html );

            // Atualiza URL sem reload (deep link)
            const url = new URL( window.location.href );
            url.searchParams.set( 'event', eventKey );
            history.replaceState( { eventKey }, '', url.toString() );

        } catch ( err ) {
            if ( err.name === 'AbortError' ) return; // request cancelado — ok
            console.error( '[VanaEventController] Erro ao buscar stage:', err );

            // Fallback: recarrega a página no event_key correto
            const url = new URL( window.location.href );
            url.searchParams.set( 'event', eventKey );
            window.location.href = url.toString();

        } finally {
            setLoading( btn, false );
            inflightRequest = null;
        }
    }

    // ── Inicialização ─────────────────────────────────────

    function init() {
        // Lê event_key inicial a partir do stage já renderizado no SSR
        const stageEl = document.getElementById( 'vana-stage' );
        if ( stageEl ) {
            currentEventKey = stageEl.dataset.eventKey || null;
        }

        // Delegação de eventos — funciona mesmo com DOM atualizado dinamicamente
        document.addEventListener( 'click', function ( e ) {
            const btn = e.target.closest( '[data-vana-event-key]' );
            if ( ! btn ) return;

            e.preventDefault();
            fetchStage( btn, readBtnData( btn ) );
        });

        // Suporte a teclado (Enter / Space no botão seletor)
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key !== 'Enter' && e.key !== ' ' ) return;
            const btn = e.target.closest( '[data-vana-event-key]' );
            if ( ! btn ) return;

            e.preventDefault();
            fetchStage( btn, readBtnData( btn ) );
        });

        // Popstate — navegação via browser back/forward
        window.addEventListener( 'popstate', function ( e ) {
            const key = e.state?.eventKey;
            if ( ! key ) return;

            const btn = document.querySelector(
                `[data-vana-event-key="${ CSS.escape( key ) }"]`
            );
            if ( btn ) fetchStage( btn, readBtnData( btn ) );
        });
    }

    // ── Bootstrap ─────────────────────────────────────────
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

})();