;( function () {
  'use strict';

  // ── Referências DOM ──────────────────────────────────────────
  const drawer  = document.getElementById( 'vana-agenda-drawer' );
  const overlay = document.querySelector( '[data-vana-agenda-overlay]' );
  const trigger = document.querySelector( '[data-vana-agenda-open]' );
  const closeBtn = drawer?.querySelector( '[data-vana-agenda-close]' );

  if ( ! drawer ) return;

  // ScrollLock singleton (reference-counted) — create if absent
  if ( ! window.VanaScrollLock ) {
    ( function () {
      let _count = 0;
      const body = document.body;
      window.VanaScrollLock = {
        acquire() { if ( ++_count === 1 ) body.style.overflow = 'hidden'; },
        release() { if ( --_count <= 0 ) { _count = 0; body.style.overflow = ''; } },
        getCount() { return _count; },
        forceRelease() { _count = 0; body.style.overflow = ''; }
      };
    } )();
  }

  // ── Fix 1: usa classe .is-open em vez de [hidden] ────────────
  function open() {
    drawer.classList.add( 'is-open' );
    overlay?.classList.add( 'is-open' );
    drawer.setAttribute( 'aria-hidden', 'false' );
    // use ScrollLock to prevent body scroll when drawer is open
    document.body.classList.add( 'vana-drawer-open' );
    window.VanaScrollLock.acquire();
    drawer.querySelector( '.vana-day-tab' )?.focus();
  }

  function close() {
    drawer.classList.remove( 'is-open' );
    overlay?.classList.remove( 'is-open' );
    drawer.setAttribute( 'aria-hidden', 'true' );
    document.body.classList.remove( 'vana-drawer-open' );
    // release the scroll lock acquired when opening
    window.VanaScrollLock.release();
    trigger?.focus();
  }

  trigger?.addEventListener( 'click', open );
  closeBtn?.addEventListener( 'click', close );
  overlay?.addEventListener( 'click', close );

  document.addEventListener( 'keydown', e => {
    if ( e.key === 'Escape' && drawer.classList.contains( 'is-open' ) ) close();
  } );

  // ── Fix 2: Toggle PT/EN ──────────────────────────────────────
  document.addEventListener( 'click', e => {
    const btn = e.target.closest( '[data-vana-lang]' );
    if ( ! btn ) return;

    const lang = btn.dataset.vanaLang;           // 'pt' ou 'en'
    if ( ! lang ) return;

    const url = new URL( window.location.href );
    url.searchParams.set( 'lang', lang );
    window.location.href = url.toString();
  } );

  // ── Day Tabs ─────────────────────────────────────────────────
  drawer.addEventListener( 'click', e => {
    const tab = e.target.closest( '.vana-day-tab' );
    if ( ! tab ) return;

    const dayKey = tab.dataset.dayKey;

    drawer.querySelectorAll( '.vana-day-tab' ).forEach( t => {
      t.classList.toggle( 'is-active', t === tab );
      t.setAttribute( 'aria-selected', t === tab ? 'true' : 'false' );
    } );

    drawer.querySelectorAll( '.vana-day-panel' ).forEach( p => {
      const active = p.dataset.dayPanel === dayKey;
      p.classList.toggle( 'is-active', active );
      active ? p.removeAttribute( 'hidden' ) : p.setAttribute( 'hidden', '' );
    } );
  } );

  // ── Expand / Collapse EVENTO ─────────────────────────────────
  drawer.addEventListener( 'click', e => {
    const toggle = e.target.closest( '.vana-event-toggle' );
    if ( ! toggle ) return;

    const item     = toggle.closest( '.vana-event-item' );
    const body     = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
    const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
    const iconEl   = toggle.querySelector( '.vana-event-toggle__icon' );

    toggle.setAttribute( 'aria-expanded', String( !expanded ) );
    item.classList.toggle( 'is-expanded', !expanded );
    if ( iconEl ) iconEl.textContent = expanded ? '+' : '−';
    expanded ? body?.setAttribute( 'hidden', '' ) : body?.removeAttribute( 'hidden' );
  } );

  // ── Expand / Collapse PASSAGE ────────────────────────────────
  drawer.addEventListener( 'click', e => {
    const toggle = e.target.closest( '.vana-passage-toggle' );
    if ( ! toggle ) return;

    const item     = toggle.closest( '.vana-passage-item' );
    const body     = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
    const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
    const iconEl   = toggle.querySelector( '.vana-passage-toggle__icon' );

    toggle.setAttribute( 'aria-expanded', String( !expanded ) );
    item.classList.toggle( 'is-expanded', !expanded );
    if ( iconEl ) iconEl.textContent = expanded ? '+' : '−';
    expanded ? body?.setAttribute( 'hidden', '' ) : body?.removeAttribute( 'hidden' );
  } );

  // ── Delegação de ações ───────────────────────────────────────
  drawer.addEventListener( 'click', e => {
    const btn = e.target.closest( '[data-action]' );
    if ( ! btn ) return;

    const action = btn.dataset.action;

    if ( action === 'load-vod' || action === 'seek-passage' ) {
      const ts = action === 'seek-passage'
        ? ( parseInt( btn.dataset.timestamp, 10 ) || 0 )
        : 0;

      // Fecha agenda ANTES — garante release() antes do acquire() do stage
      // count: 1→0 (close) depois 0→1 (loadVod) = correto
      close();

      requestAnimationFrame( () => {
        window.VanaStageBridge?.loadVod(
          btn.dataset.vodKey,
          btn.dataset.videoId,
          btn.dataset.provider,
          ts
        );
      } );
      return;
    }

    if ( action === 'open-photos' ) {
      document.dispatchEvent( new CustomEvent( 'vana:photos:open', {
        detail: { eventKey: btn.dataset.eventKey }
      } ) );
      return;
    }

    if ( action === 'open-sangha' ) {
      document.dispatchEvent( new CustomEvent( 'vana:sangha:open', {
        detail: { eventKey: btn.dataset.eventKey }
      } ) );
      return;
    }
  } );

  // ── API pública ──────────────────────────────────────────────
  window.VanaAgenda = { open, close };

} )();
