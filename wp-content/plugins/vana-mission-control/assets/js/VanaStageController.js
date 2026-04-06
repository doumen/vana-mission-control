/**
 * VanaStageController.js
 *
 * Responsabilidades:
 *  1. Detectar evento ativo no Stage (data-event-key, data-katha-id)
 *  2. Carregar passages via REST /vana/v1/katha/{katha_ref}
 *  3. Injetar passages na zona #vana-stage-katha
 *  4. Sincronizar passage ativo com o tempo do iframe (postMessage YT API)
 *  5. Seek no iframe ao clicar em timestamp de passage
 *  6. Modo Acompanhar (opt-in)
 *  7. Expor API pública: window.VanaStage
 *
 * Dependências: nenhuma (vanilla JS)
 * Compatível com: VanaEventController.js, VanaVisitController.js
 *
 * @since 5.1.2 — Schema 6.1
 */

( function () {
    'use strict';

    // ══════════════════════════════════════════════════════════════
    // CONSTANTES
    // ══════════════════════════════════════════════════════════════

    const REST_BASE      = window.vanaStageConfig?.restBase  || '/wp-json/vana/v1';
    const LANG           = window.vanaStageConfig?.lang      || 'pt';
    const PASSAGES_PAGE  = 10;   // passages por página (lazy load)
    const SYNC_THROTTLE  = 800;  // ms entre syncs no modo acompanhar

    // ══════════════════════════════════════════════════════════════
    // SELETORES DOM
    // ══════════════════════════════════════════════════════════════

    const $ = ( sel, ctx = document ) => ctx.querySelector( sel );
    const $$
= ( sel, ctx = document ) => Array.from( ctx.querySelectorAll( sel ) ); // ══════════════════════════════════════════════════════════════ // UTILITÁRIOS // ══════════════════════════════════════════════════════════════ /** * Converte "HH:MM:SS" ou "MM:SS" → segundos (int). */ function timecodeToSeconds( tc ) { if ( ! tc ) return 0; const parts = String( tc ).split( ':' ).map( Number ); if ( parts.length === 3 ) return parts[0] * 3600 + parts[1] * 60 + parts[2]; if ( parts.length === 2 ) return parts[0] * 60 + parts[1]; return parseInt( tc, 10 ) || 0; } /** * Converte segundos → "HH:MM:SS". */ function secondsToTimecode( s ) { const h = Math.floor( s / 3600 ); const m = Math.floor( ( s % 3600 ) / 60 ); const sec = Math.floor( s % 60 ); return [ h > 0 ? String( h ).padStart( 2, '0' ) : null, String( m ).padStart( 2, '0' ), String( sec ).padStart( 2, '0' ), ].filter( Boolean ).join( ':' ); } /** * Throttle simples. */ function throttle( fn, ms ) { let last = 0; return function ( ...args ) { const now = Date.now(); if ( now - last < ms ) return; last = now; return fn.apply( this, args ); }; } /** * Escapa HTML (segurança ao injetar texto do servidor). */ function escHtml( str ) { const d = document.createElement( 'div' ); d.textContent = String( str ?? '' ); return d.innerHTML; } // ══════════════════════════════════════════════════════════════ // REST CLIENT // ══════════════════════════════════════════════════════════════ /** * Carrega katha + passages do endpoint canônico. * * GET /vana/v1/katha/{katha_ref}?lang=pt&page=1&per_page=10 * * Retorna: { katha:{}, passages:[], total:int, pages:int } */ async function fetchKatha( kathaRef, page = 1 ) { const url = new URL( `${ REST_BASE }/katha/${ encodeURIComponent( kathaRef ) }`, window.location.origin ); url.searchParams.set( 'lang',     LANG ); url.searchParams.set( 'page',     page ); url.searchParams.set( 'per_page', PASSAGES_PAGE ); const resp = await fetch( url.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', } ); if ( ! resp.ok ) { throw new Error( `REST ${ resp.status } — katha/${ kathaRef }` ); } return resp.json(); } // ══════════════════════════════════════════════════════════════ // IFRAME CONTROLLER (YouTube postMessage) // ══════════════════════════════════════════════════════════════ const IframeCtrl = { _iframe: null, _ready:  false, _queue:  [], /** * Registra o iframe do Stage e habilita YT postMessage. */ attach( iframe ) { this._iframe = iframe; this._ready  = false; this._queue  = []; // YT Iframe API envia 'onReady' via postMessage window.addEventListener( 'message', this._onMessage.bind( this ) ); }, detach() { window.removeEventListener( 'message', this._onMessage.bind( this ) ); this._iframe = null; this._ready  = false; this._queue  = []; }, _onMessage( e ) { if ( ! e.data ) return; let data; try { data = typeof e.data === 'string' ? JSON.parse( e.data ) : e.data; } catch { return; } // YT informa prontidão if ( data.event === 'onReady' ) { this._ready = true; this._flushQueue(); } // Delega ao StageController para sincronização if ( data.event === 'onStateChange' || data.event === 'infoDelivery' ) { VanaStageController._onIframeMessage( data ); } }, _flushQueue() { this._queue.forEach( cmd => this._send( cmd ) ); this._queue = []; }, _send( cmd ) { if ( ! this._iframe?.contentWindow ) return; this._iframe.contentWindow.postMessage( JSON.stringify( cmd ), '*' ); }, /** * Seek para `seconds` no vídeo YT. */ seek( seconds ) { const cmd = { event: 'command', func:  'seekTo', args:  [ Math.floor( seconds ), true ], }; if ( this._ready ) { this._send( cmd ); } else { this._queue.push( cmd ); } }, /** * Solicita currentTime periodicamente (para modo acompanhar). * YT responde com infoDelivery → info.currentTime */ requestCurrentTime() { this._send( { event: 'listening', id:    'vana-stage', } ); this._send( { event: 'command', func:  'getPlayerState', args:  [], } ); }, }; // ══════════════════════════════════════════════════════════════ // PASSAGE RENDERER // ══════════════════════════════════════════════════════════════ const PassageRenderer = { /** * Renderiza lista de passages como HTML. * Retorna string segura (usa escHtml). */ renderList( passages, lang ) { if ( ! passages?.length ) return ''; return passages.map( p => this.renderItem( p, lang ) ).join( '' ); }, renderItem( p, lang ) { const ts      = p.timestamp_start ?? 0; const label   = secondsToTimecode( ts ); const quote   = escHtml( p.key_quote   ?? '' ); const text    = escHtml( p[ `text_${ lang }` ] ?? p.text_pt ?? '' ); const pid     = escHtml( p.passage_id  ?? '' ); const order   = escHtml( p.order       ?? '' ); const vodKey  = escHtml( p.vod_key     ?? '' ); return ` <article class="vana-katha-passage" data-passage-id="${ pid }" data-ts="${ ts }" data-vod-key="${ vodKey }" aria-label="Passage ${ order }" > <button type="button" class="vana-katha-passage__seek" data-ts="${ ts }" aria-label="${ LANG === 'en' ? 'Jump to' : 'Ir para' } ${ label }" > <span class="vana-katha-passage__time" aria-hidden="true">▶ ${ label }</span> </button> ${ quote ? ` <blockquote class="vana-katha-passage__quote"> ${ quote } </blockquote>` : '' } ${ text ? ` <div class="vana-katha-passage__text"> ${ text } </div>` : '' } </article>`; }, /** * Renderiza botão "Carregar mais". */ renderLoadMore( remaining ) { return ` <button type="button" class="vana-katha-load-more" id="vana-katha-load-more" aria-label="${ LANG === 'en' ? `Load ${ remaining } more passages` : `Carregar mais ${ remaining } passages` }" > ${ LANG === 'en' ? `↓ Load ${ remaining } more` : `↓ Carregar mais ${ remaining }` } </button>`; }, /** * Renderiza cabeçalho da katha. */ renderHeader( katha, lang ) { const title    = escHtml( katha[ `title_${ lang }` ] ?? katha.title_pt ?? '' ); const scripture = escHtml( katha.scripture ?? '' ); const total    = katha.passage_count ?? 0; return ` <header class="vana-katha-header"> <div class="vana-katha-header__meta"> ${ scripture ? `<span class="vana-katha-header__scripture">${ scripture }</span>` : '' } <span class="vana-katha-header__count"> ${ total } ${ LANG === 'en' ? 'passages' : 'passages' } </span> </div> <h3 class="vana-katha-header__title">${ title }</h3> <!-- Modo Acompanhar toggle --> <button type="button" class="vana-katha-follow-toggle" id="vana-katha-follow-toggle" aria-pressed="false" title="${ LANG === 'en' ? 'Follow video' : 'Acompanhar vídeo' }" > <span class="vana-katha-follow-toggle__icon" aria-hidden="true">▶</span> <span class="vana-katha-follow-toggle__label"> ${ LANG === 'en' ? 'Follow video' : 'Acompanhar vídeo' } </span> </button> </header>`; }, /** * Renderiza banner "Acompanhamento pausado". */ renderResumeBanner() { return ` <div class="vana-katha-resume-banner" id="vana-katha-resume-banner" hidden> <span>${ LANG === 'en' ? '⚠️ Following paused' : '⚠️ Acompanhamento pausado' }</span> <button type="button" id="vana-katha-resume-btn"> ${ LANG === 'en' ? '▶ Resume' : '▶ Retomar' } </button> </div>`; }, /** * Renderiza estado de erro. */ renderError( msg ) { return ` <div class="vana-katha-error" role="alert"> <span>${ escHtml( msg ) }</span> </div>`; }, /** * Renderiza skeleton de carregamento. */ renderSkeleton( count = 3 ) { return Array.from( { length: count }, () => ` <div class="vana-katha-skeleton" aria-hidden="true"> <div class="vana-katha-skeleton__time"></div> <div class="vana-katha-skeleton__text"></div> <div class="vana-katha-skeleton__text vana-katha-skeleton__text--short"></div> </div>` ).join( '' ); }, }; // ══════════════════════════════════════════════════════════════ // STAGE CONTROLLER — núcleo // ══════════════════════════════════════════════════════════════ const VanaStageController = { // ── Estado interno ────────────────────────────────────── _stageEl:       null,   // <section#vana-stage> _videoWrap:     null,   // <div#vana-stage-video-wrap> _kathaZone:     null,   // <div#vana-stage-katha> _iframe:        null,   // <iframe#vana-stage-iframe> _currentEventKey: '', _currentKathaRef: '', _currentPage:     1, _totalPages:      1, _totalPassages:   0, _isFollowing:   false, _userScrolled:  false, _syncTimer:     null, _activePassageId: null, // ── Bootstrap ─────────────────────────────────────────── init() { this._stageEl   = $( '#vana-stage' ); this._videoWrap = $( '#vana-stage-video-wrap' ); this._kathaZone = $( '#vana-stage-katha' ); if ( ! this._stageEl || ! this._kathaZone ) return; // Lê contexto SSR this._currentEventKey = this._stageEl.dataset.eventKey  || ''; this._currentKathaRef = this._stageEl.dataset.kathaId   || ''; // Anexa iframe se já existe no DOM (SSR) this._attachIframe( $( '#vana-stage-iframe' ) ); // Carrega passages se há katha if ( this._currentKathaRef ) { this._loadKatha( this._currentKathaRef, 1 ); } // Escuta eventos de outros controllers this._bindGlobalEvents(); // Expõe API pública window.VanaStage = this._publicApi(); }, // ── Iframe ────────────────────────────────────────────── _attachIframe( iframe ) { if ( ! iframe ) return; this._iframe = iframe; IframeCtrl.attach( iframe ); }, /** * Opção B — destroi e recria o iframe ao trocar de evento. * Preserva o src no data-iframe-src do wrapper. */ _swapIframe( src, title ) { if ( ! this._videoWrap ) return; // Remove iframe existente const old = $( 'iframe', this._videoWrap ); if ( old ) { IframeCtrl.detach(); old.remove(); } if ( ! src ) return; const iframe = document.createElement( 'iframe' ); iframe.id            = 'vana-stage-iframe'; iframe.src           = src; iframe.title         = title || ''; iframe.allow         = 'autoplay'; iframe.allowFullscreen = true; iframe.loading       = 'lazy'; iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;'; this._videoWrap.appendChild( iframe ); this._attachIframe( iframe ); }, // ── Katha loader ──────────────────────────────────────── async _loadKatha( kathaRef, page ) { this._showKathaLoading( page === 1 ); try { const data = await fetchKatha( kathaRef, page ); this._totalPages    = data.pages    ?? 1; this._totalPassages = data.total    ?? 0; this._currentPage   = page; if ( page === 1 ) { this._renderKathaFull( data ); } else { this._appendPassages( data.passages ); this._updateLoadMore(); } } catch ( err ) { console.error( '[VanaStage] Katha load error:', err ); this._kathaZone.innerHTML = PassageRenderer.renderError( LANG === 'en' ? 'Could not load passages. Try again.' : 'Não foi possível carregar os passages.' ); this._showKathaZone( true ); } }, _renderKathaFull( data ) { const { katha, passages } = data; const html = [ PassageRenderer.renderResumeBanner(), PassageRenderer.renderHeader( katha, LANG ), `<div class="vana-katha-passages" id="vana-katha-passages">`, PassageRenderer.renderList( passages, LANG ), `</div>`, this._currentPage < this._totalPages ? PassageRenderer.renderLoadMore( this._totalPassages - passages.length ) : '', ].join( '' ); this._kathaZone.innerHTML = html; this._showKathaZone( true ); this._bindKathaEvents(); }, _appendPassages( passages ) { const container = $( '#vana-katha-passages' ); if ( ! container ) return; const frag = document.createDocumentFragment(); const tmp  = document.createElement( 'div' ); tmp.innerHTML = PassageRenderer.renderList( passages, LANG ); while ( tmp.firstChild ) frag.appendChild( tmp.firstChild ); container.appendChild( frag ); this._bindSeekButtons( container ); }, _updateLoadMore() { const btn = $( '#vana-katha-load-more' ); if ( ! btn ) return; const loaded    =
$$( '.vana-katha-passage' ).length;
            const remaining = this._totalPassages - loaded;

            if ( this._currentPage >= this._totalPages || remaining <= 0 ) {
                btn.remove();
                return;
            }

            btn.textContent = LANG === 'en'
                ? `↓ Load ${ remaining } more`
                : `↓ Carregar mais ${ remaining }`;
        },

        _showKathaLoading( full ) {
            if ( full ) {
                this._kathaZone.innerHTML = PassageRenderer.renderSkeleton( 3 );
                this._showKathaZone( true );
            }
        },

        _showKathaZone( show ) {
            this._kathaZone.hidden = ! show;
        },

        // ── Bind de eventos do DOM da katha ─────────────────────

        _bindKathaEvents() {
            // Seek buttons
            this._bindSeekButtons( this._kathaZone );

            // Toggle modo acompanhar
            const followBtn = $( '#vana-katha-follow-toggle' );
            if ( followBtn ) {
                followBtn.addEventListener( 'click', () => {
                    this._isFollowing ? this._stopFollowing() : this._startFollowing();
                } );
            }

            // Botão retomar
            this._kathaZone.addEventListener( 'click', e => {
                if ( e.target.closest( '#vana-katha-resume-btn' ) ) {
                    this._resumeFollowing();
                }
            } );

            // Load more
            const loadMoreBtn = $( '#vana-katha-load-more' );
            if ( loadMoreBtn ) {
                loadMoreBtn.addEventListener( 'click', () => {
                    this._loadKatha( this._currentKathaRef, this._currentPage + 1 );
                } );
            }

            // Scroll manual detectado (pausa modo acompanhar)
            this._kathaZone.addEventListener(
                'scroll',
                this._onManualScroll.bind( this ),
                { passive: true }
            );
            document.addEventListener(
                'scroll',
                this._onManualScroll.bind( this ),
                { passive: true }
            );
        },

        _bindSeekButtons( ctx ) {
            $$
( '.vana-katha-passage__seek', ctx ).forEach( btn => { btn.addEventListener( 'click', e => { const ts = parseFloat( e.currentTarget.dataset.ts ); if ( ! isNaN( ts ) ) this._seekTo( ts, btn ); } ); } ); }, // ── Seek ──────────────────────────────────────────────── _seekTo( seconds, triggerEl ) { // 1. Garante que Stage está visível if ( ! this._isStageVisible() ) { this._stageEl.scrollIntoView( { behavior: 'smooth', block: 'center' } ); setTimeout( () => { IframeCtrl.seek( seconds ); }, 600 ); } else { IframeCtrl.seek( seconds ); } // 2. Destaca passage correspondente if ( triggerEl ) { const article = triggerEl.closest( '.vana-katha-passage' ); if ( article ) { this._highlightPassage( article.dataset.passageId ); } } // 3. Atualiza URL (sem reload) this._updateUrl( { t: Math.floor( seconds ) } ); }, // ── Passage highlight ──────────────────────────────────── _highlightPassage( passageId ) { if ( ! passageId ) return; // Remove ativo anterior const prev = $( '.vana-katha-passage.is-active' ); if ( prev ) prev.classList.remove( 'is-active' ); const el = $( `.vana-katha-passage[data-passage-id="${ passageId }"]` ); if ( el ) { el.classList.add( 'is-active' ); this._activePassageId = passageId; } }, // ── Modo Acompanhar ───────────────────────────────────── _startFollowing() { this._isFollowing  = true; this._userScrolled = false; const btn = $( '#vana-katha-follow-toggle' ); if ( btn ) { btn.setAttribute( 'aria-pressed', 'true' ); btn.querySelector( '.vana-katha-follow-toggle__label' ).textContent = LANG === 'en' ? '■ Following... Stop' : '■ Acompanhando... Parar'; } // Polling do currentTime via postMessage (YT API) this._syncTimer = setInterval( throttle( () => { IframeCtrl.requestCurrentTime(); }, SYNC_THROTTLE ), SYNC_THROTTLE ); }, _stopFollowing() { this._isFollowing = false; clearInterval( this._syncTimer ); const btn = $( '#vana-katha-follow-toggle' ); if ( btn ) { btn.setAttribute( 'aria-pressed', 'false' ); btn.querySelector( '.vana-katha-follow-toggle__label' ).textContent = LANG === 'en' ? '▶ Follow video' : '▶ Acompanhar vídeo'; } this._hideResumeBanner(); }, _resumeFollowing() { this._userScrolled = false; this._hideResumeBanner(); }, _onManualScroll() { if ( ! this._isFollowing ) return; if ( this._userScrolled ) return; this._userScrolled = true; this._showResumeBanner(); }, _showResumeBanner() { const banner = $( '#vana-katha-resume-banner' ); if ( banner ) banner.hidden = false; }, _hideResumeBanner() { const banner = $( '#vana-katha-resume-banner' ); if ( banner ) banner.hidden = true; }, // ── Sync com iframe ───────────────────────────────────── /** * Chamado pelo IframeCtrl quando chega postMessage do YT. */ _onIframeMessage( data ) { // infoDelivery traz currentTime const currentTime = data?.info?.currentTime; if ( typeof currentTime !== 'number' ) return; if ( this._isFollowing && ! this._userScrolled ) { this._syncPassages( currentTime ); } }, _syncPassages: throttle( function ( currentTime ) { const passages =
$$( '.vana-katha-passage[data-ts]' );
            if ( ! passages.length ) return;

            let activeEl  = null;
            let activeId  = null;

            passages.forEach( el => {
                const ts = parseFloat( el.dataset.ts );
                if ( ! isNaN( ts ) && ts <= currentTime ) {
                    activeEl = el;
                    activeId = el.dataset.passageId;
                }
            } );

            if ( activeId && activeId !== this._activePassageId ) {
                this._highlightPassage( activeId );
                if ( activeEl ) {
                    activeEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                }
            }
        }, SYNC_THROTTLE ),

        // ── Troca de evento (VanaEventController) ───────────────

        /**
         * Chamado quando o usuário seleciona novo evento no selector.
         * Atualiza katha_ref + recarrega passages + recria iframe.
         *
         * @param {object} eventData  { event_key, katha_ref, iframe_src, iframe_title }
         */
        _onEventChange( eventData ) {
            const { event_key, katha_ref, iframe_src, iframe_title } = eventData;

            // Atualiza dados internos
            this._currentEventKey = event_key  || '';
            this._currentKathaRef = katha_ref  || '';
            this._currentPage     = 1;
            this._activePassageId = null;

            // Para modo acompanhar
            if ( this._isFollowing ) this._stopFollowing();

            // Atualiza data-* no <section>
            if ( this._stageEl ) {
                this._stageEl.dataset.eventKey = this._currentEventKey;
                this._stageEl.dataset.kathaId  = this._currentKathaRef;
            }

            // Recria iframe (Opção B)
            if ( iframe_src ) {
                this._swapIframe( iframe_src, iframe_title );
                // Atualiza data-iframe-src no wrapper para consistência
                if ( this._videoWrap ) {
                    this._videoWrap.dataset.iframeSrc   = iframe_src;
                    this._videoWrap.dataset.iframeTitle = iframe_title || '';
                }
            }

            // Limpa zona katha
            this._kathaZone.innerHTML = '';
            this._showKathaZone( false );

            // Carrega nova katha
            if ( this._currentKathaRef ) {
                this._loadKatha( this._currentKathaRef, 1 );
            }

            // Atualiza URL
            this._updateUrl( { event_key } );
        },

        // ── Utilitários internos ────────────────────────────────

        _isStageVisible() {
            const rect = this._stageEl?.getBoundingClientRect();
            if ( ! rect ) return false;
            return rect.top >= 0 && rect.bottom <= window.innerHeight;
        },

        _updateUrl( params ) {
            try {
                const url = new URL( window.location.href );
                Object.entries( params ).forEach( ( [ k, v ] ) => {
                    if ( v !== undefined && v !== null && v !== '' ) {
                        url.searchParams.set( k, v );
                    }
                } );
                history.replaceState( {}, '', url.toString() );
            } catch {
                // silencioso
            }
        },

        // ── Eventos globais ─────────────────────────────────────

        _bindGlobalEvents() {
            // VanaEventController emite 'vana:event:change'
            // quando o selector muda (alternativa ao reload SSR).
            // O VanaEventController atual faz reload — este listener
            // é preparação para a Fase E (SPA-like sem reload).
            document.addEventListener( 'vana:event:change', e => {
                if ( e.detail ) this._onEventChange( e.detail );
            } );

            // VanaVisitController emite 'vana:visit:controller:ready'
            // → garante que o Stage se inicializa após fade-in
            document.addEventListener( 'vana:visit:controller:ready', () => {
                // Já inicializado, nada a fazer
            } );

            // Escape key — sai do modo acompanhar
            document.addEventListener( 'keydown', e => {
                if ( e.key === 'Escape' && this._isFollowing ) {
                    this._stopFollowing();
                }
            } );
        },

        // ── API pública ──────────────────────────────────────────

        _publicApi() {
            return {
                /** Seek direto no Stage (chamável por HariKathaNavigator) */
                seek: ( seconds ) => this._seekTo( seconds, null ),

                /** Carrega katha por ref (chamável externamente) */
                loadKatha: ( ref ) => {
                    this._currentKathaRef = ref;
                    this._currentPage     = 1;
                    this._loadKatha( ref, 1 );
                },

                /** Destaca passage por ID */
                highlightPassage: ( id ) => this._highlightPassage( id ),

                /** Retorna event_key ativo */
                get currentEventKey() { return VanaStageController._currentEventKey; },

                /** Retorna katha_ref ativo */
                get currentKathaRef() { return VanaStageController._currentKathaRef; },
            };
        },
    };

    // ══════════════════════════════════════════════════════════════
    // INIT
    // ══════════════════════════════════════════════════════════════

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', () => VanaStageController.init() );
    } else {
        VanaStageController.init();
    }

} )();
