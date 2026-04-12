/**
 * PassageController.js
 * Escuta o VanaStateRouter e carrega o conteúdo do passage via REST
 */
;(function () {
  'use strict';

  var ZONE_ID = 'vana-mutable-zone';

  function emit(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail || {} }));
  }

  function getRestBase() {
    if (window.vanaPassageConfig && window.vanaPassageConfig.restBase) return window.vanaPassageConfig.restBase;
    if (window.vanaStageConfig && window.vanaStageConfig.restBase) return window.vanaStageConfig.restBase;
    try { return (typeof rest_url === 'function') ? rest_url('vana/v1') : '/wp-json/vana/v1/'; } catch (e) { return '/wp-json/vana/v1/'; }
  }

  function selectPanel() {
    var zone = document.getElementById(ZONE_ID);
    if (!zone) return null;
    return zone.querySelector('[data-panel="passage"]');
  }

  function showLoading(panel) {
    if (!panel) return;
    panel.innerHTML = '<div class="vana-mz__loading">Carregando…</div>';
  }

  function showError(panel, msg) {
    if (!panel) return;
    panel.innerHTML = '<div class="vana-mz__error">' + (msg || 'Erro ao carregar.') + '</div>';
  }

  function applyHtml(panel, html) {
    if (!panel) return;
    panel.innerHTML = html || '';
  }

  function fetchPassageById(id) {
    var rest = getRestBase();
    var url = rest.replace(/\/$/, '') + '/passage/' + encodeURIComponent(id);
    return fetch(url, { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      // tenta JSON com { html: '...' } ou texto bruto
      return res.text().then(function (text) {
        try {
          var j = JSON.parse(text);
          if (j && j.html) return j.html;
        } catch (e) {
          // não JSON — usar texto como HTML
        }
        return text;
      });
    });
  }

  function fetchPassageByKathaRef(ref) {
    var rest = getRestBase();
    var url = rest.replace(/\/$/, '') + '/passage?katha_ref=' + encodeURIComponent(ref);
    return fetch(url, { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text().then(function (text) {
        try {
          var j = JSON.parse(text);
          if (j && j.html) return j.html;
        } catch (e) {}
        return text;
      });
    });
  }

  // Fallback: fetch katha endpoint which is known to exist on the server
  function fetchKathaByRef(ref) {
    var rest = getRestBase();
    var url = rest.replace(/\/$/, '') + '/katha/' + encodeURIComponent(ref);
    return fetch(url, { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text().then(function (text) {
        try {
          var j = JSON.parse(text);
          if (j && j.html) return j.html;
        } catch (e) {}
        return text;
      });
    });
  }

  function loadPassage(passageId, kathaRef) {
    var panel = selectPanel();
    if (!panel) return;

    // Prefer delegating to VanaStage (it knows the correct endpoints and rendering).
    var ref = kathaRef || passageId || '';
    if (ref && window.VanaStage && typeof window.VanaStage.loadKatha === 'function') {
      try {
        window.VanaStage.loadKatha(ref);
        emit('vana:passage:delegated', { passage_id: passageId, katha_ref: kathaRef });
        return;
      } catch (e) {
        console.warn('[PassageController] delegation to VanaStage failed', e);
      }
    }

    showLoading(panel);
    emit('vana:passage:loading', { passage_id: passageId, katha_ref: kathaRef });

    var p = null;
    if (ref) {
      // Try the /katha/{ref} endpoint which is present on the server.
      p = fetchKathaByRef(ref);
    } else {
      showError(panel, 'Passage não especificado.');
      emit('vana:passage:error', { message: 'no-id' });
      return;
    }

    p.then(function (html) {
      applyHtml(panel, html);
      emit('vana:passage:loaded', { passage_id: passageId, katha_ref: kathaRef });
    }).catch(function (err) {
      console.error('[PassageController] fetch failed', err);
      showError(panel, 'Falha ao carregar passage.');
      emit('vana:passage:error', { passage_id: passageId, katha_ref: kathaRef, error: String(err) });
    });
  }

  // Listener central — responde quando o Router muda para 'passage'
  document.addEventListener('vana:state:changed', function (e) {
    var d = e && e.detail ? e.detail : {};
    if (!d) return;
    if (d.state !== 'passage') return;
    var params = d.params || {};
    var pid = params.passage_id || params.p || '';
    var kref = params.katha_ref || '';

    // Se existe um skeleton SSR com data-passage-id, e o id for o mesmo, evita fetch redundante
    var panel = selectPanel();
    if (panel) {
      var ssr = panel.querySelector('.vana-mz__skeleton[data-passage-id]');
      if (ssr) {
        var ssrId = ssr.getAttribute('data-passage-id') || '';
        var ssrRef = ssr.getAttribute('data-katha-ref') || '';
        if (ssrId && ssrId === String(pid)) {
          // Deixa o skeleton — mas ainda tentamos buscar para substituir quando chegar
        }
      }
    }

    loadPassage(pid, kref);
  });

  // Expor util pequeno para debug
  window.VanaPassage = {
    load: loadPassage,
  };

})();
