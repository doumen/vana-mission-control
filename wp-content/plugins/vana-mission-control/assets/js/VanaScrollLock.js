(function () {
  'use strict';

  if ( window.VanaScrollLock ) return;

  let _count = 0;
  const body = typeof document !== 'undefined' ? document.body : null;

  window.VanaScrollLock = {
    acquire() {
      try {
        if ( ++_count === 1 && body ) body.style.overflow = 'hidden';
      } catch ( e ) { /* ignore */ }
    },
    release() {
      try {
        if ( _count <= 0 ) return;
        if ( --_count === 0 && body ) body.style.overflow = '';
      } catch ( e ) { /* ignore */ }
    },
    getCount() { return _count; },
    forceRelease() { try { _count = 0; if ( body ) body.style.overflow = ''; } catch ( e ) { /* ignore */ } }
  };
})();
