/* Passage Nav — delegated click handlers to open passages via stateRouter */
(function () {
  'use strict';

  // Prevent double initialization
  if (window.__vana_passage_nav_initialized) return;
  window.__vana_passage_nav_initialized = true;

  function getRouterType() {
    if (window.VanaRouter && typeof window.VanaRouter.toPassage === 'function') return 'new';
    if (window.stateRouter && typeof window.stateRouter.openPassage === 'function') return 'legacy';
    return null;
  }

  function openPassage(id) {
    var t = getRouterType();
    if (t === 'new') {
      window.VanaRouter.toPassage(id);
      return;
    }
    if (t === 'legacy') {
      window.stateRouter.openPassage(id);
      return;
    }
    // Last-resort fallback
    window.location.hash = 'passage-' + id;
  }

  function handleActivation(e) {
    var isKey = e.type === 'keydown';
    if (isKey && e.key !== 'Enter' && e.key !== ' ') return;

    var el = e.target;
    if (!el) return;
    var btn = (typeof el.closest === 'function') ? el.closest('[data-vana-open-passage]') : null;
    if (!btn) return;

    e.preventDefault();
    var id = btn.getAttribute('data-passage-id') || btn.getAttribute('data-id') || (btn.dataset && btn.dataset.passageId);
    if (!id) return;
    openPassage(id);
  }

  document.addEventListener('click', handleActivation, false);
  document.addEventListener('keydown', handleActivation, false);

})();
