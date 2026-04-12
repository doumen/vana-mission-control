/* State Router — controla a zona mutável (visita | passage | lente)
   Fornece API pública: stateRouter.open(state, opts), stateRouter.openPassage(id)
*/
(function (window) {
  'use strict';

  var root = document.getElementById('vana-mutable-zone');
  if (!root) {
    window.stateRouter = window.stateRouter || { ready: false };
    return;
  }

  function setActive(state) {
    root.setAttribute('data-state', state);
    root.querySelectorAll('.vana-mz__panel').forEach(function (p) {
      var ps = p.getAttribute('data-mz-state');
      var active = ps === state;
      p.classList.toggle('is-active', active);
      p.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    var ev = new CustomEvent('vana:mz:state', { detail: { state: state } });
    document.dispatchEvent(ev);
  }

  function fetchPassage(passageId) {
    var container = document.getElementById('mz-passage');
    if (!container) return Promise.reject('no container');
    container.innerHTML = '<div class="vana-mz__loading">' + (window.__vanaLang === 'en' ? 'Loading…' : 'Carregando…') + '</div>';

    var restRoot = (window.CFG && window.CFG.restRoot) ? window.CFG.restRoot : '/wp-json/vana/v1/';
    var nonce = (window.CFG && window.CFG.restNonce) ? window.CFG.restNonce : '';

    return fetch(restRoot + 'passage/' + encodeURIComponent(passageId), {
      headers: { 'X-WP-Nonce': nonce },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function openPassage(passageId, push) {
    setActive('passage');
    return fetchPassage(passageId).then(function (json) {
      var container = document.getElementById('mz-passage');
      if (!json || !json.success) {
        container.innerHTML = '<div style="padding:36px;color:var(--vana-muted);text-align:center;">' + (window.__vanaLang === 'en' ? 'Failed to load passage' : 'Falha ao carregar passage') + '</div>';
        return null;
      }
      var html = json.data && json.data.html ? json.data.html : ('<article class="vana-passage">' + (json.data.title ? ('<h3>' + json.data.title + '</h3>') : '') + (json.data.content ? ('<div>' + json.data.content + '</div>') : '') + '</article>');
      container.innerHTML = html;
      if (push !== false) {
        try { history.pushState({ mz: 'passage', id: passageId }, '', window.location.pathname + '#passage-' + passageId); } catch (e) {}
      }
      document.dispatchEvent(new CustomEvent('vana:mz:opened', { detail: { state: 'passage', id: passageId } }));
      return json.data;
    }).catch(function () {
      var container = document.getElementById('mz-passage');
      if (container) container.innerHTML = '<div style="padding:36px;color:var(--vana-muted);text-align:center;">' + (window.__vanaLang === 'en' ? 'Failed to load passage' : 'Falha ao carregar passage') + '</div>';
      return null;
    });
  }

  function openLens(topicSlug) {
    setActive('lente');
    var container = document.getElementById('mz-lens');
    if (!container) return Promise.reject('no container');
    container.innerHTML = '<div class="vana-mz__loading">' + (window.__vanaLang === 'en' ? 'Loading…' : 'Carregando…') + '</div>';
    var restRoot = (window.CFG && window.CFG.restRoot) ? window.CFG.restRoot : '/wp-json/vana/v1/';
    var nonce = (window.CFG && window.CFG.restNonce) ? window.CFG.restNonce : '';
    return fetch(restRoot + 'passages?topic=' + encodeURIComponent(topicSlug) + '&limit=20', {
      headers: { 'X-WP-Nonce': nonce },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (json) {
      if (!json || !json.success) {
        container.innerHTML = '<div style="padding:36px;color:var(--vana-muted);text-align:center;">' + (window.__vanaLang === 'en' ? 'Failed to load lens' : 'Falha ao carregar lente') + '</div>';
        return null;
      }
      var list = json.data.items || [];
      var html = '<div class="vana-lens-list">';
      list.forEach(function (it) {
        var title = it.title || it.title_pt || it.title_en || '';
        var visit = it.visit_title || it.visit || '';
        var id = it.id || it.passage_id || '';
        html += '<article class="vana-lens-card">' +
                '<h4>' + (visit ? (escapeHtml(visit) + ' — ') : '') + escapeHtml(title) + '</h4>' +
                '<div><button data-vana-open-passage data-passage-id="' + escapeHtml(id) + '">Abrir</button></div>' +
                '</article>';
      });
      html += '</div>';
      container.innerHTML = html;
      document.dispatchEvent(new CustomEvent('vana:mz:opened', { detail: { state: 'lente', topic: topicSlug } }));
      return json.data;
    }).catch(function () {
      container.innerHTML = '<div style="padding:36px;color:var(--vana-muted);text-align:center;">' + (window.__vanaLang === 'en' ? 'Failed to load lens' : 'Falha ao carregar lente') + '</div>';
      return null;
    });
  }

  function escapeHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
  }

  window.stateRouter = {
    open: function (state, opts) {
      if (state === 'visita') return setActive('visita');
      if (state === 'passage' && opts && opts.id) return openPassage(opts.id, opts.push !== false);
      if (state === 'lente' && opts && opts.topic) return openLens(opts.topic);
      return Promise.resolve();
    },
    openPassage: openPassage,
    openLens: openLens,
    ready: true
  };

  window.addEventListener('popstate', function (e) {
    // Simples: quando volta, restaura para visita (poderíamos analisar state)
    setActive('visita');
  });

})(window);
