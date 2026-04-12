;( function () {
  'use strict';

  if ( ! window.VanaScrollLock ) {
    ( function () {
      let _count = 0;
      const body = document.body;
      window.VanaScrollLock = {
        acquire()      { if ( ++_count === 1 && body ) body.style.overflow = 'hidden'; },
        release()      { if ( _count <= 0 ) return; if ( --_count === 0 && body ) body.style.overflow = ''; },
        getCount()     { return _count; },
        forceRelease() { _count = 0; if ( body ) body.style.overflow = ''; }
      };
    } )();
  }

  function getIframe() {
    return document.getElementById( 'vanaStageIframe' );
  }

  let _currentVideoId  = null;
  let _currentProvider = null;
  let _stageIsOpen     = false; // ← controla se lock já foi adquirido pelo stage

  function buildSrc( provider, videoId, ts ) {
    const t = parseInt( ts, 10 ) || 0;
    switch ( provider ) {
      case 'youtube':
        return 'https://www.youtube.com/embed/' + videoId
          + '?autoplay=1&rel=0&modestbranding=1&enablejsapi=1'
          + ( t > 0 ? '&start=' + t : '' );
      case 'facebook':
        return 'https://www.facebook.com/plugins/video.php?href='
          + encodeURIComponent( 'https://www.facebook.com/watch/?v=' + videoId )
          + '&autoplay=1';
      default:
        console.warn( '[VanaStageBridge] Provider desconhecido:', provider );
        return '';
    }
  }

  function loadVod( vodKey, videoId, provider, ts ) {
    if ( ! videoId ) {
      console.warn( '[VanaStageBridge] video_id ausente para vod_key:', vodKey );
      return;
    }

    // ✅ Só adquire lock se stage ainda não está aberto
    // Evita double-acquire quando usuário troca de vídeo sem fechar
    if ( ! _stageIsOpen ) {
      try { window.VanaScrollLock?.acquire(); } catch ( e ) { /* ignore */ }
      _stageIsOpen = true;
    }

    const src = buildSrc( provider, videoId, ts );
    if ( ! src ) return;

    let iframe = getIframe();
    if ( ! iframe ) {
      iframe = _createIframe();
      if ( ! iframe ) {
        console.warn( '[VanaStageBridge] Container .vana-stage-video não encontrado.' );
        // ✅ Rollback do lock se falhou
        _stageIsOpen = false;
        try { window.VanaScrollLock?.release(); } catch ( e ) { /* ignore */ }
        return;
      }
    }

    if ( _currentVideoId !== videoId || _currentProvider !== provider ) {
      iframe.src       = src;
      _currentVideoId  = videoId;
      _currentProvider = provider;
    } else if ( ts > 0 ) {
      seekTo( ts );
    }

    document.dispatchEvent( new CustomEvent( 'vana:stage:loaded', {
      detail: { vodKey, videoId, provider, ts }
    } ) );

    iframe.closest( '.vana-stage' )?.scrollIntoView( {
      behavior: 'smooth',
      block: 'start',
    } );
  }

  // ✅ NOVO: fecha o stage e libera o scroll lock
  function closeVod() {
    if ( ! _stageIsOpen ) return; // guard: já fechado, não faz nada

    const iframe = getIframe();
    if ( iframe ) {
      iframe.src = ''; // para o vídeo imediatamente
    }

    _currentVideoId  = null;
    _currentProvider = null;
    _stageIsOpen     = false;

    try { window.VanaScrollLock?.release(); } catch ( e ) { /* ignore */ }

    document.dispatchEvent( new CustomEvent( 'vana:stage:closed' ) );
  }

  function _createIframe() {
    const container = document.querySelector( '.vana-stage-video' );
    if ( ! container ) return null;

    const placeholder = container.querySelector( '.vana-stage-placeholder' );
    placeholder?.remove();

    const wrap = document.createElement( 'div' );
    wrap.style.cssText = 'position:relative;width:100%;padding-top:56.25%;';

    const iframe = document.createElement( 'iframe' );
    iframe.id              = 'vanaStageIframe';
    iframe.style.cssText   = 'position:absolute;inset:0;width:100%;height:100%;border:0;';
    iframe.allowFullscreen = true;
    iframe.loading         = 'lazy';

    wrap.appendChild( iframe );
    container.appendChild( wrap );
    return iframe;
  }

  function seekTo( ts ) {
    const iframe = getIframe();
    if ( ! iframe || _currentProvider !== 'youtube' ) return;
    iframe.contentWindow?.postMessage(
      JSON.stringify( { event: 'command', func: 'seekTo', args: [ parseInt( ts, 10 ), true ] } ),
      'https://www.youtube.com'
    );
  }

  function getCurrentTime() {
    return new Promise( resolve => {
      const iframe = getIframe();
      if ( ! iframe || _currentProvider !== 'youtube' ) return resolve( 0 );

      const handler = e => {
        try {
          const data = JSON.parse( e.data );
          if ( data.event === 'infoDelivery' && data.info?.currentTime != null ) {
            window.removeEventListener( 'message', handler );
            resolve( Math.floor( data.info.currentTime ) );
          }
        } catch { /* ignora */ }
      };

      window.addEventListener( 'message', handler );
      iframe.contentWindow?.postMessage(
        JSON.stringify( { event: 'listening' } ),
        'https://www.youtube.com'
      );
      setTimeout( () => {
        window.removeEventListener( 'message', handler );
        resolve( 0 );
      }, 2000 );
    } );
  }

  // ✅ Fecha ao pressionar Escape (stage aberto)
  document.addEventListener( 'keydown', e => {
    if ( e.key === 'Escape' && _stageIsOpen ) closeVod();
  } );

  // ✅ Permite fechar de fora via evento customizado
  document.addEventListener( 'vana:stage:close', closeVod );

  window.VanaStageBridge = { loadVod, closeVod, seekTo, getCurrentTime,
    isOpen() { return _stageIsOpen; }
  };

} )();
