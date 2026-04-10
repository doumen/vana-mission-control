/* VanaRouterPatch.js
 * Monkey-patch para garantir emissão de 'vana:state:changed'
 * Aplica-se após VanaStateRouter ser carregado.
 */
(function () {
  'use strict';

  if (window.__vana_router_patch_applied) return;
  window.__vana_router_patch_applied = true;

  function applyPatch() {
    var R = window.VanaRouter;
    if (!R) return;

    ['toPassage', 'toLens', 'toVisita', 'go', 'back'].forEach(function (method) {
      var orig = R[method];
      if (typeof orig !== 'function') return;
      R[method] = function () {
        var res = orig.apply(R, arguments);
        try {
          document.dispatchEvent(new CustomEvent('vana:state:changed', {
            detail: { state: R.state, params: R.params }
          }));
        } catch (e) {
          // noop
        }
        return res;
      };
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyPatch);
  } else {
    applyPatch();
  }
})();
