/**
 * VanaStateRouter.js — Orquestrador da Zona Mutável
 *
 * Controla os 3 estados da página da visita:
 *   - visita  (seções do dia: vods, gallery, sangha)
 *   - passage (conteúdo de um passage / katha)
 *   - lente   (lista por tema / topic)
 *
 * Expondo API pública em `window.VanaRouter` e emitindo CustomEvents:
 *   - vana:state:will-change
 *   - vana:state:changed
 *   - vana:chips:update
 *   - vana:router:ready
 *
 * Deve ser carregado antes dos módulos que o consomem
 * (StageController, ChipController, etc.).
 *
 * @since  5.1.0
 * @updated 6.0.0 — Integração com #vana-mutable-zone (SSR skeleton,
 *                   data-state, passage seek relay).
 */

;(function () {
  'use strict';

  /* ═══════════════════════════════════════════════════════════
     CONSTANTS
     ═══════════════════════════════════════════════════════════ */

  var ZONE_ID        = 'vana-mutable-zone';
  var PANEL_ATTR     = 'data-panel';
  var ACTIVE_CLASS   = 'is-active';
  var ENTERING_CLASS = 'is-entering';
  var LEAVING_CLASS  = 'is-leaving';
  var FADE_MS        = 240; // deve coincidir com --vana-mz-transition no CSS
  var VALID_STATES   = ['visita', 'passage', 'lente'];

  /* ═══════════════════════════════════════════════════════════
     STATE
     ═══════════════════════════════════════════════════════════ */

  var _zone          = null;
  var _panels        = {};
  var _currentState  = 'visita';
  var _currentParams = {};
  var _isTransiting  = false;
  var _history       = [];

  /* ═══════════════════════════════════════════════════════════
     HELPERS
     ═══════════════════════════════════════════════════════════ */

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function emit(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail || {} }));
  }

  function getUrlParam(key) {
    try {
      return new URL(window.location.href).searchParams.get(key) || '';
    } catch (e) {
      return '';
    }
  }

  function _cloneParams(obj) {
    if (!obj) return {};
    var clone = {};
    Object.keys(obj).forEach(function (k) { clone[k] = obj[k]; });
    return clone;
  }

  // Ensure backward-compatible aliases for common param names
  function _aliasParams(params) {
    if (!params) return params;
    // passage_id -> id (legacy consumers/tests expect `id`)
    if (params.passage_id && !params.id) params.id = params.passage_id;
    // also expose short key 'p' when passage_id present
    if (params.passage_id && !params.p) params.p = params.passage_id;
    return params;
  }

  function _isSameParams(newParams) {
    var keys = Object.keys(newParams || {});
    if (keys.length !== Object.keys(_currentParams).length) return false;
    for (var i = 0; i < keys.length; i++) {
      if (String(newParams[keys[i]]) !== String(_currentParams[keys[i]])) return false;
    }
    return true;
  }

  /* ═══════════════════════════════════════════════════════════
     URL SYNC
     ═══════════════════════════════════════════════════════════ */

  function _updateUrl(state, params, replace) {
    try {
      var url = new URL(window.location.href);

      // Limpa todos os params de estado
      url.searchParams.delete('p');
      url.searchParams.delete('lens');
      url.searchParams.delete('passage_id');
      url.searchParams.delete('katha_ref');
      url.searchParams.delete('topic');

      if (state === 'passage' && params && params.passage_id) {
        url.searchParams.set('p', params.passage_id);
        // 6.0: persiste katha_ref na URL se disponível
        if (params.katha_ref) {
          url.searchParams.set('katha_ref', params.katha_ref);
        }
      }

      if (state === 'lente' && params && params.topic_slug) {
        url.searchParams.set('lens', params.topic_slug);
      }

      var historyState = { vanaState: state, vanaParams: _cloneParams(params) };
      if (replace) {
        history.replaceState(historyState, '', url.toString());
      } else {
        history.pushState(historyState, '', url.toString());
      }
    } catch (e) {
      console.warn('[VanaRouter] URL update failed:', e);
    }
  }

  function _inferStateFromUrl() {
    if (getUrlParam('p') || getUrlParam('passage_id')) return 'passage';
    if (getUrlParam('lens') || getUrlParam('topic')) return 'lente';
    return 'visita';
  }

  function _inferParamsFromUrl(state) {
    if (state === 'passage') {
      return {
        passage_id: getUrlParam('p') || getUrlParam('passage_id'),
        katha_ref:  getUrlParam('katha_ref'),
      };
    }
    if (state === 'lente') {
      return { topic_slug: getUrlParam('lens') || getUrlParam('topic') };
    }
    return {};
  }

  /* ═══════════════════════════════════════════════════════════
     PANEL MANAGEMENT
     ═══════════════════════════════════════════════════════════ */

  function _activatePanel(state) {
    Object.keys(_panels).forEach(function (key) {
      var panel = _panels[key];
      if (!panel) return;
      if (key === state) {
        panel.hidden = false;
        panel.classList.add(ACTIVE_CLASS);
        panel.classList.remove(ENTERING_CLASS, LEAVING_CLASS);
      } else {
        panel.hidden = true;
        panel.classList.remove(ACTIVE_CLASS, ENTERING_CLASS, LEAVING_CLASS);
      }
    });
  }

  function _transition(fromState, toState, params, callback) {
    var fromPanel = _panels[fromState];
    var toPanel   = _panels[toState];

    if (!fromPanel || !toPanel) {
      _activatePanel(toState);
      callback && callback();
      return;
    }

    // Same-state transition (ex: troca de passage dentro de passage)
    if (fromState === toState) {
      _isTransiting = true;
      fromPanel.classList.add(LEAVING_CLASS);
      setTimeout(function () {
        fromPanel.classList.remove(LEAVING_CLASS);
        _isTransiting = false;
        callback && callback();
      }, FADE_MS);
      return;
    }

    // Cross-state transition
    _isTransiting = true;
    fromPanel.classList.add(LEAVING_CLASS);

    setTimeout(function () {
      fromPanel.classList.remove(ACTIVE_CLASS, LEAVING_CLASS);
      fromPanel.hidden = true;

      toPanel.hidden = false;
      toPanel.classList.add(ENTERING_CLASS);
      void toPanel.offsetHeight; // force reflow para CSS transição

      toPanel.classList.add(ACTIVE_CLASS);
      toPanel.classList.remove(ENTERING_CLASS);

      setTimeout(function () {
        _isTransiting = false;
        callback && callback();
      }, FADE_MS);
    }, FADE_MS);
  }

  /* ═══════════════════════════════════════════════════════════
     6.0: SSR HYDRATION
     Marca a zona como hidratada e remove skeletons.
     ═══════════════════════════════════════════════════════════ */

  function _hydrateZone() {
    if (!_zone) return;
    if (!_zone.classList.contains('is-hydrated')) {
      _zone.classList.add('is-hydrated');
    }
  }

  /* ═══════════════════════════════════════════════════════════
     6.0: DATA-STATE SYNC
     Mantém data-state do container sincronizado com o estado
     lógico do router. CSS usa isso para min-height etc.
     ═══════════════════════════════════════════════════════════ */

  function _syncDataState(state) {
    if (!_zone) return;

    // Mapeia estados do router para o data-state do CSS
    // visita  → "neutral" (seções do dia, min-height padrão)
    // passage → "katha"   (passages, min-height: 300px)
    // lente   → "katha"   (mesma altura expandida)
    var cssState = (state === 'passage' || state === 'lente') ? 'katha' : 'neutral';
    _zone.setAttribute('data-state', cssState);
  }

  /* ═══════════════════════════════════════════════════════════
     CORE: NAVIGATE
     ═══════════════════════════════════════════════════════════ */

  function navigate(toState, params, options) {
    params  = params  || {};
    options = options || {};

    if (VALID_STATES.indexOf(toState) === -1) {
      console.warn('[VanaRouter] Invalid state:', toState);
      return;
    }

    if (toState === _currentState && _isSameParams(params)) return;
    if (_isTransiting) {
      console.warn('[VanaRouter] Transition in progress, ignoring.');
      return;
    }

    var fromState = _currentState;
    var direction = options.direction || 'forward';

    emit('vana:state:will-change', {
      from: fromState,
      to: toState,
      params: params,
    });

    if (direction === 'forward' && fromState !== toState) {
      _history.push({ state: fromState, params: _cloneParams(_currentParams) });
    }

    _transition(fromState, toState, params, function () {
      _currentState  = toState;
      _currentParams = _aliasParams(_cloneParams(params));

      // 6.0: sync data-state + hydrate
      _syncDataState(toState);
      _hydrateZone();

      if (!options.skipPush) {
        _updateUrl(toState, params, options.replace);
      }

      // Auto-scroll na navegação forward
      if (direction === 'forward' && _zone) {
        var rect = _zone.getBoundingClientRect();
        if (rect.top < 0) {
          _zone.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      emit('vana:state:changed', {
        state: toState,
        params: _cloneParams(params),
        direction: direction,
        from: fromState,
      });

      emit('vana:chips:update', {
        state: toState,
        params: _cloneParams(params),
      });
    });
  }

  function goBack() {
    if (_history.length === 0) {
      navigate('visita', {}, { direction: 'back' });
      return;
    }
    var prev = _history.pop();
    navigate(prev.state, prev.params, { direction: 'back', replace: true });
  }

  /* ═══════════════════════════════════════════════════════════
     POPSTATE
     ═══════════════════════════════════════════════════════════ */

  function _onPopState(e) {
    var historyState = e.state;
    if (historyState && historyState.vanaState) {
      navigate(
        historyState.vanaState,
        historyState.vanaParams || {},
        { skipPush: true, direction: 'back' }
      );
    } else {
      var state  = _inferStateFromUrl();
      var params = _inferParamsFromUrl(state);
      navigate(state, params, { skipPush: true, direction: 'back' });
    }
  }

  /* ═══════════════════════════════════════════════════════════
     EVENT BINDINGS
     ═══════════════════════════════════════════════════════════ */

  function _bindEvents() {
    // Navegação programática
    document.addEventListener('vana:router:navigate', function (e) {
      var d = e.detail || {};
      if (d.state) navigate(d.state, d.params || {}, d.options || {});
    });

    document.addEventListener('vana:router:back', function () {
      goBack();
    });

    // Troca de evento → volta para visita
    document.addEventListener('vana:event:change', function () {
      if (_currentState !== 'visita') {
        navigate('visita', {}, { replace: true });
      }
    });

    // 6.0: Botão Hari-Katha no stage → navega para passage
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action="vana:stage:katha"]');
      if (!btn) return;

      e.preventDefault();
      var kathaRef = btn.getAttribute('data-katha-ref') || '';
      var kathaId  = btn.getAttribute('data-katha-id')  || '';
      if (kathaRef || kathaId) {
        navigate('passage', {
          katha_ref:  kathaRef || kathaId,
          passage_id: '', // será resolvido pelo módulo de passage
        });
      }
    });

    // 6.0: Passage click → seek no player via bridge
    document.addEventListener('click', function (e) {
      var passage = e.target.closest('[data-passage-seek]');
      if (!passage) return;

      var ts = parseInt(passage.getAttribute('data-passage-seek'), 10);
      if (isNaN(ts)) return;

      // Delegate to bridge
      if (window.VanaStageBridge && typeof window.VanaStageBridge.seekTo === 'function') {
        window.VanaStageBridge.seekTo(ts);
      }
      emit('vana:player:seek', { timestamp: ts });
    });

    // 6.0: Day change → reset para visita
    document.addEventListener('vana:day:change', function () {
      if (_currentState !== 'visita') {
        navigate('visita', {}, { replace: true });
      }
    });

    // Popstate (browser back/forward)
    window.addEventListener('popstate', _onPopState);
  }

  /* ═══════════════════════════════════════════════════════════
     INIT
     ═══════════════════════════════════════════════════════════ */

  function init() {
    _zone = document.getElementById(ZONE_ID);
    if (!_zone) {
      console.warn('[VanaRouter] #' + ZONE_ID + ' not found. Router disabled.');
      return;
    }

    // Descobre painéis no DOM (renderizados pelo PHP)
    var panelEls = _zone.querySelectorAll('[' + PANEL_ATTR + ']');
    for (var i = 0; i < panelEls.length; i++) {
      var key = panelEls[i].getAttribute(PANEL_ATTR);
      if (key) _panels[key] = panelEls[i];
    }

    if (!_panels.visita || !_panels.passage || !_panels.lente) {
      console.warn('[VanaRouter] Incomplete panels:', Object.keys(_panels));
    }

    // Resolve estado inicial via URL
    var initialState  = _inferStateFromUrl();
    var initialParams = _inferParamsFromUrl(initialState);

    if (initialState !== 'visita') {
      _activatePanel(initialState);
      _currentState  = initialState;
      _currentParams = _aliasParams(_cloneParams(initialParams));
      _syncDataState(initialState);
      _updateUrl(initialState, initialParams, true);

      emit('vana:state:changed', {
        state: initialState,
        params: _cloneParams(initialParams),
        direction: 'init',
        from: 'visita',
      });
      emit('vana:chips:update', {
        state: initialState,
        params: _cloneParams(initialParams),
      });
    } else {
      _currentState  = 'visita';
      _currentParams = {};
      _syncDataState('visita');
      history.replaceState(
        { vanaState: 'visita', vanaParams: {} },
        '',
        window.location.href
      );
    }

    // 6.0: Hidrata zona (remove skeleton SSR)
    _hydrateZone();

    _bindEvents();

    /* ═════════════════════════════════════════════════════════
       PUBLIC API — window.VanaRouter
       ═════════════════════════════════════════════════════════ */

    window.VanaRouter = {
      go: function (state, params) {
        navigate(state, params);
      },
      back: function () {
        goBack();
      },
      get state() {
        return _currentState;
      },
      get params() {
        return _cloneParams(_currentParams);
      },
      get history() {
        return _history.slice();
      },

      // Convenience methods
      toPassage: function (passageId, kathaRef, extra) {
        navigate('passage', Object.assign(
          { passage_id: passageId, katha_ref: kathaRef || '' },
          extra || {}
        ));
      },
      toLens: function (topicSlug, extra) {
        navigate('lente', Object.assign(
          { topic_slug: topicSlug },
          extra || {}
        ));
      },
      toVisita: function () {
        navigate('visita', {});
      },
    };

    emit('vana:router:ready', {
      state: _currentState,
      params: _cloneParams(_currentParams),
    });

    console.info(
      '[VanaRouter] Initialized. state=%s params=%o',
      _currentState,
      _currentParams
    );
  }

  /* ═══════════════════════════════════════════════════════════
     BOOT
     ═══════════════════════════════════════════════════════════ */

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
