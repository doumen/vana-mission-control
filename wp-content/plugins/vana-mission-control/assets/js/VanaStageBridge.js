;( function () {
  'use strict';

  // ── Fix: busca o iframe de forma lazy ────────────────────────
  // O iframe só existe no DOM quando há VOD ativo.
  // Não mata o bridge se não encontrar na inicialização.
  function getIframe() {
    return document.getElementById( 'vanaStageIframe' );
  }

  let _currentVideoId  = null;
  let _currentProvider = null;

  function buildSrc( provider, videoId, ts ) {
    const t = parseInt( ts, 10 ) || 0;
    switch ( provider ) {
      case 'youtube':
        return 'https://www.youtube.com/embed/' + videoId
          + '?autoplay=1&rel=0&modestbranding=1'
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

    const src = buildSrc( provider, videoId, ts );
    if ( ! src ) return;

    // ── Fix 3a: injeta iframe se não existir ─────────────────
    let iframe = getIframe();
    if ( ! iframe ) {
      iframe = _createIframe();
      if ( ! iframe ) {
        console.warn( '[VanaStageBridge] Container .vana-stage-video não encontrado.' );
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

  // ── Fix 3b: cria e injeta o iframe no container ──────────────
  function _createIframe() {
    const container = document.querySelector( '.vana-stage-video' );
    if ( ! container ) return null;

    // Remove placeholder se existir
    const placeholder = container.querySelector( '.vana-stage-placeholder' );
    placeholder?.remove();

    // Wrapper responsivo
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
      setTimeout( () => { window.removeEventListener( 'message', handler ); resolve( 0 ); }, 2000 );
    } );
  }

  window.VanaStageBridge = { loadVod, seekTo, getCurrentTime };

} )();
