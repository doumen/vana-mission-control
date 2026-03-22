<?php
/**
 * Scripts da Página de Visita
 * Arquivo: templates/visit/visit-scripts.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id, $visit_tz
 *   $active_day_date, $active_vod_index
 *
 * Responsabilidades:
 *   1. Dual-timezone  — converte timestamps Unix p/ fuso local do visitante
 *   2. Lightbox       — galeria de fotos (VanaGallery)
 *   3. Segmentos      — seek no player YouTube via postMessage
 *   4. Fallback FB    — detecta falha no iframe Facebook e exibe painel
 *   5. Copy link      — botão copiar link (Facebook fallback)
 *   6. Tabs keyboard  — navegação por teclado nas abas de dias
 *   7. Lazy map       — já tratado inline em stage.php (sem JS extra aqui)
 */
defined('ABSPATH') || exit;

// Dados PHP → JS (localize seguro, sem dados sensíveis)
$js_data = [
    'lang'            => $lang,
    'visitId'         => $visit_id,
    'activeDayDate'   => $active_day_date,
    'activeVodIndex'  => (int) $active_vod_index,
    'eventTz'         => $visit_tz->getName(),
    'timeline'        => $data ?? [],
    'i18n'            => [
        'lightbox_close'  => $lang === 'en' ? 'Close'          : 'Fechar',
      'lightbox_dialog' => $lang === 'en' ? 'Image viewer'   : 'Visualizador de imagens',
        'lightbox_prev'   => $lang === 'en' ? 'Previous photo'  : 'Foto anterior',
        'lightbox_next'   => $lang === 'en' ? 'Next photo'      : 'Próxima foto',
        'lightbox_of'     => $lang === 'en' ? 'of'              : 'de',
        'copy_ok'         => $lang === 'en' ? 'Copied!'         : 'Copiado!',
        'copy_fail'       => $lang === 'en' ? 'Copy manually'   : 'Copie manualmente',
        'your_time'       => $lang === 'en' ? 'Your time'       : 'Seu horário',
        'fb_error'        => $lang === 'en'
                               ? 'The Facebook player did not load.'
                               : 'O player do Facebook não carregou.',
    ],
];

  $drawer_data = [
    'visitId' => (int) $visit_id,
    'lang'    => $lang,
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'vana_visit_drawer' ),
  ];
?>
  <script>
  window.vanaDrawer = <?php echo wp_json_encode( $drawer_data ); ?>;
  </script>
<script>
/* ============================================================
   VANA VISIT PAGE — scripts v2.6
   ============================================================ */
(function (CFG) {
  'use strict';

  /* ----------------------------------------------------------
     0. UTILITÁRIOS
     ---------------------------------------------------------- */

  /**
   * Formata timestamp Unix no fuso local do navegador.
   * Retorna string "HH:MM" ou "" se Intl indisponível.
   */
  function fmtLocalTime(ts) {
    if (!ts || !window.Intl) return '';
    try {
      return new Intl.DateTimeFormat(
        CFG.lang === 'en' ? 'en-US' : 'pt-BR',
        { hour: '2-digit', minute: '2-digit', timeZoneName: 'short' }
      ).format(new Date(ts * 1000));
    } catch (_) { return ''; }
  }

  /**
   * Converte string "HH:MM" ou "H:MM:SS" para segundos.
   */
  function timeToSec(t) {
    if (!t) return 0;
    var p = String(t).split(':').map(Number);
    if (p.length === 3) return p[0] * 3600 + p[1] * 60 + p[2];
    if (p.length === 2) return p[0] * 60  + p[1];
    return 0;
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  var _vodIndex = null;

  function getVodIndex() {
    if (_vodIndex) return _vodIndex;

    _vodIndex = {};

    var timeline = CFG.timeline || {};
    var days = Array.isArray(timeline.days) ? timeline.days : [];

    function addVod(vod) {
      if (!vod || typeof vod !== 'object') return;

      var vodId = vod.id || vod.vod_id || '';
      if (!vodId) return;

      _vodIndex[String(vodId)] = vod;
    }

    days.forEach(function (day) {
      var events = [];

      if (Array.isArray(day.active_events)) {
        events = day.active_events;
      } else if (Array.isArray(day.events)) {
        events = day.events;
      }

      events.forEach(function (event) {
        var media = event && event.media ? event.media : {};
        var vods = [];

        if (Array.isArray(media.vods)) {
          vods = media.vods;
        } else if (Array.isArray(event.vods)) {
          vods = event.vods;
        } else if (event.vod && typeof event.vod === 'object') {
          vods = [event.vod];
        }

        vods.forEach(addVod);
      });
    });

    return _vodIndex;
  }

  /**
   * Manda postMessage para o iframe do YouTube Stage.
   */
  function ytPostMessage(msg) {
    var iframe = document.getElementById('vanaStageIframe');
    if (iframe && iframe.contentWindow) {
      iframe.contentWindow.postMessage(JSON.stringify(msg), 'https://www.youtube-nocookie.com');
    }
  }

  /* ----------------------------------------------------------
     1. DUAL-TIMEZONE
        Preenche todos os [data-vana-photo] com horário local
        apenas quando fuso do visitante ≠ fuso do evento.
     ---------------------------------------------------------- */
  function initDualTimezone() {
    var localTz;
    try { localTz = Intl.DateTimeFormat().resolvedOptions().timeZone; }
    catch (_) { return; }

    // Mesmo fuso → nada a exibir
    if (!localTz || localTz === CFG.eventTz) return;

    var targets = document.querySelectorAll('.vana-local-time-target[data-ts]');
    if (!targets.length) return;

    targets.forEach(function (el) {
      var ts  = parseInt(el.getAttribute('data-ts'), 10);
      var fmt = fmtLocalTime(ts);
      if (!fmt) return;

      el.textContent = CFG.i18n.your_time + ': ' + fmt;

      // Garante visibilidade (display pode estar '' por CSS)
      el.style.display = 'block';
    });
  }

  /* ----------------------------------------------------------
     2. LIGHTBOX — VanaGallery
        Abre fotos em overlay fullscreen com nav prev/next.
     ---------------------------------------------------------- */
  var _lb = {
    overlay : null,
    img     : null,
    caption : null,
    counter : null,
    items   : [],
    current : 0,
  };

  function lbBuild() {
    if (_lb.overlay) return;                     // já construído

    var ov = document.createElement('div');
    ov.id              = 'vanaLightbox';
    ov.setAttribute('role',            'dialog');
    ov.setAttribute('aria-modal',      'true');
    ov.setAttribute('aria-label',      CFG.i18n.lightbox_dialog);
    ov.style.cssText = [
      'position:fixed', 'inset:0', 'z-index:9999',
      'background:rgba(0,0,0,.92)',
      'display:none', 'flex-direction:column',
      'align-items:center', 'justify-content:center',
      'padding:20px',
    ].join(';');

    // Imagem
    var img = document.createElement('img');
    img.id           = 'vanaLbImg';
    img.alt          = '';
    img.style.cssText = [
      'max-width:90vw', 'max-height:75vh',
      'object-fit:contain', 'border-radius:8px',
      'box-shadow:0 8px 40px rgba(0,0,0,.6)',
      'transition:opacity .2s',
    ].join(';');

    // Caption
    var cap = document.createElement('div');
    cap.id           = 'vanaLbCaption';
    cap.style.cssText = [
      'color:#e2e8f0', 'font-size:.9rem', 'margin-top:14px',
      'text-align:center', 'max-width:600px', 'line-height:1.5',
    ].join(';');

    // Counter
    var ctr = document.createElement('div');
    ctr.id           = 'vanaLbCounter';
    ctr.style.cssText = [
      'color:#94a3b8', 'font-size:.78rem', 'margin-top:6px',
      'font-family:monospace',
    ].join(';');

    // Botões nav
    function mkBtn(label, html, pos) {
      var b = document.createElement('button');
      b.type                   = 'button';
      b.setAttribute('aria-label', label);
      b.innerHTML              = html;
      b.style.cssText = [
        'position:absolute', pos + ':14px', 'top:50%',
        'transform:translateY(-50%)',
        'background:rgba(255,255,255,.15)',
        'border:none', 'border-radius:50%',
        'width:44px', 'height:44px',
        'cursor:pointer', 'font-size:1.4rem', 'color:#fff',
        'display:flex', 'align-items:center', 'justify-content:center',
        'transition:background .2s',
      ].join(';');
      b.addEventListener('mouseover', function () {
        b.style.background = 'rgba(255,255,255,.3)';
      });
      b.addEventListener('mouseout', function () {
        b.style.background = 'rgba(255,255,255,.15)';
      });
      return b;
    }

    var btnPrev = mkBtn(CFG.i18n.lightbox_prev, '&#8592;', 'left');
    var btnNext = mkBtn(CFG.i18n.lightbox_next, '&#8594;', 'right');
    var btnClose = mkBtn(CFG.i18n.lightbox_close, '&#10005;', 'right');
    btnClose.style.top    = '14px';
    btnClose.style.transform = 'none';

    btnPrev.addEventListener('click',  function () { lbNav(-1); });
    btnNext.addEventListener('click',  function () { lbNav(+1); });
    btnClose.addEventListener('click', lbClose);

    ov.appendChild(img);
    ov.appendChild(cap);
    ov.appendChild(ctr);
    ov.appendChild(btnPrev);
    ov.appendChild(btnNext);
    ov.appendChild(btnClose);
    document.body.appendChild(ov);

    // Fecha ao clicar fora da imagem
    ov.addEventListener('click', function (e) {
      if (e.target === ov) lbClose();
    });

    _lb.overlay = ov;
    _lb.img     = img;
    _lb.caption = cap;
    _lb.counter = ctr;
  }

  function lbOpen(idx) {
    lbBuild();
    _lb.current = idx;
    lbRender();
    _lb.overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    _lb.overlay.focus();
  }

  function lbClose() {
    if (!_lb.overlay) return;
    _lb.overlay.style.display = 'none';
    document.body.style.overflow = '';
    // Devolve foco ao item que abriu o lightbox
    var opener = _lb.items[_lb.current];
    if (opener) opener.focus();
  }

  function lbNav(dir) {
    _lb.current = (_lb.current + dir + _lb.items.length) % _lb.items.length;
    lbRender();
  }

  function lbRender() {
    var item = _lb.items[_lb.current];
    if (!item) return;

    var full    = item.getAttribute('data-full') || '';
    var caption = item.getAttribute('data-caption') || '';

    _lb.img.style.opacity = '0';
    _lb.img.src = full;
    _lb.img.onload = function () {
      _lb.img.style.opacity = '1';
    };
    _lb.caption.textContent = caption;
    _lb.counter.textContent = (_lb.current + 1)
      + ' ' + CFG.i18n.lightbox_of
      + ' ' + _lb.items.length;
  }

  function initGallery() {
    var items = document.querySelectorAll('[data-vana-photo="1"]');
    if (!items.length) return;

    _lb.items = Array.prototype.slice.call(items);

    _lb.items.forEach(function (el, idx) {
      // Clique
      el.addEventListener('click', function () { lbOpen(idx); });

      // Teclado: Enter ou Espaço
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          lbOpen(idx);
        }
      });
    });
  }

  // Teclado global no lightbox
  document.addEventListener('keydown', function (e) {
    if (!_lb.overlay || _lb.overlay.style.display === 'none') return;
    if (e.key === 'ArrowLeft')  lbNav(-1);
    if (e.key === 'ArrowRight') lbNav(+1);
    if (e.key === 'Escape')     lbClose();
  });

  /* ----------------------------------------------------------
     3. SEGMENTOS / CAPÍTULOS
        Clique em .vana-seg-btn faz seek no YouTube via API JS.
     ---------------------------------------------------------- */
  function initSegments() {
    var btns = document.querySelectorAll('[data-vana-stage-seg="1"]');
    if (!btns.length) return;

    // Garante que o iframe do YT usa enablejsapi=1 e origin explícito
    var iframe = document.getElementById('vanaStageIframe');
    if (iframe) {
      var src = iframe.getAttribute('src') || '';
      if (src.indexOf('enablejsapi') === -1) {
        src += (src.indexOf('?') > -1 ? '&' : '?') + 'enablejsapi=1';
      }
      if (src.indexOf('origin=') === -1) {
        src += (src.indexOf('?') > -1 ? '&' : '?') + 'origin=' + encodeURIComponent(window.location.origin);
      }
      iframe.setAttribute('src', src);
    }

    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var t   = btn.getAttribute('data-t') || '0:00';
        var sec = timeToSec(t);

        ytPostMessage({ event: 'command', func: 'seekTo', args: [sec, true] });

        // Scroll suave até o player
        if (iframe) {
          iframe.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Feedback visual no botão
        var prev = btn.style.background;
        btn.style.background = 'var(--vana-gold)';
        btn.style.color      = '#111';
        setTimeout(function () {
          btn.style.background = prev;
          btn.style.color      = '';
        }, 900);
      });
    });
  }

  /* ----------------------------------------------------------
     4. FALLBACK FACEBOOK
        Detecta se o iframe FB carregou; se não, exibe painel.
     ---------------------------------------------------------- */
  function initFbFallback() {
    var iframe   = document.getElementById('vanaFbIframe');
    var fallback = document.getElementById('vanaFbFallback');
    if (!iframe || !fallback) return;

    var TIMEOUT = 5000; // ms para considerar falha

    var timer = setTimeout(function () {
      // Tenta checar se o iframe tem conteúdo
      var loaded = false;
      try {
        // Só funciona se same-origin (geralmente não é) — usamos como último recurso
        loaded = !!(iframe.contentDocument && iframe.contentDocument.body);
      } catch (_) {}

      if (!loaded) {
        fallback.style.display = 'flex';
        iframe.setAttribute('aria-hidden', 'true');
        fallback.setAttribute('tabindex', '-1');
        fallback.focus();
      }
    }, TIMEOUT);

    iframe.addEventListener('load', function () {
      clearTimeout(timer);
    });
  }

  /* ----------------------------------------------------------
     5. COPY LINK (botão no painel fallback Facebook)
     ---------------------------------------------------------- */
  function initCopyLink() {
    var btn = document.getElementById('vanaCopyFbLink');
    if (!btn) return;

    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-url') || '';
      if (!url) return;

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () {
          btn.textContent = CFG.i18n.copy_ok;
          setTimeout(function () {
            btn.textContent = CFG.lang === 'en' ? 'Copy link' : 'Copiar Link';
          }, 2500);
        }).catch(function () {
          btn.textContent = CFG.i18n.copy_fail + ': ' + url;
        });
      } else {
        // Fallback execCommand (browsers antigos)
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.cssText = 'position:fixed;opacity:0;';
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand('copy');
          btn.textContent = CFG.i18n.copy_ok;
        } catch (_) {
          btn.textContent = CFG.i18n.copy_fail;
        }
        document.body.removeChild(ta);
        setTimeout(function () {
          btn.textContent = CFG.lang === 'en' ? 'Copy link' : 'Copiar Link';
        }, 2500);
      }
    });
  }

  /* ----------------------------------------------------------
     6. TABS — NAVEGAÇÃO POR TECLADO
        ArrowLeft/Right entre abas, Home/End para primeira/última.
     ---------------------------------------------------------- */
  function initTabsKeyboard() {
    var tablist = document.querySelector('[role="tablist"]');
    if (!tablist) return;

    var tabs = Array.prototype.slice.call(
      tablist.querySelectorAll('[role="tab"]')
    );
    if (tabs.length < 2) return;

    tablist.addEventListener('keydown', function (e) {
      var idx = tabs.indexOf(document.activeElement);
      if (idx === -1) return;

      var next = idx;
      if (e.key === 'ArrowRight') next = (idx + 1) % tabs.length;
      if (e.key === 'ArrowLeft')  next = (idx - 1 + tabs.length) % tabs.length;
      if (e.key === 'Home')       next = 0;
      if (e.key === 'End')        next = tabs.length - 1;

      if (next !== idx) {
        e.preventDefault();
        tabs[next].focus();
        // Não navega automaticamente — aguarda Enter/Espaço (padrão ARIA)
      }

      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        tabs[idx].click();
      }
    });
  }

  /* ----------------------------------------------------------
     8. SCHEDULE → STAGE
        5 casos:
          C    — sem vod              → noop
          A    — 1 vod, sem segmento  → swapStage
          D    — 1 vod, com segmento  → swapStage + seek
          E    — N vods               → toggle accordion
          (B)  — vod sem agenda       → não passa por aqui
     ---------------------------------------------------------- */
  function initScheduleVod() {
    // ── Caso A / D — itens com vod_id único ──
    var singles = document.querySelectorAll(
      '.vana-schedule-item[data-vod-case="single"]'
    );
    singles.forEach(function (item) {
      item.addEventListener('click',   function () { handleSingle(item); });
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSingle(item); }
      });
    });

    // ── Caso E — itens com N vods (accordion toggle) ──
    var multis = document.querySelectorAll(
      '.vana-schedule-item[data-vod-case="multi"]'
    );
    multis.forEach(function (item) {
      item.addEventListener('click',   function () { toggleAccordion(item); });
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleAccordion(item); }
      });
    });

    // ── Caso E — botões internos do accordion ──
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.vana-vod-accordion__btn');
      if (!btn) return;
      var vodId    = btn.getAttribute('data-vod-id')       || '';
      var segStart = btn.getAttribute('data-segment-start') || '';
      if (vodId) loadAndPlay(vodId, segStart);
    });
  }

  /* — Caso A / D — */
  function handleSingle(item) {
    var vodId    = item.getAttribute('data-vod-id')       || '';
    var segStart = item.getAttribute('data-segment-start') || '';
    if (!vodId) return;

    highlightScheduleItem(item);
    loadAndPlay(vodId, segStart);
  }

  /* — Caso E — toggle accordion — */
  function toggleAccordion(item) {
    var accId   = item.getAttribute('data-accordion-id') || '';
    var accEl   = accId ? document.getElementById(accId) : null;
    var chevron = item.querySelector('.vana-acc-chevron');
    if (!accEl) return;

    var isOpen = !accEl.hidden;

    // Fecha todos os outros acordeons abertos
    document.querySelectorAll('.vana-vod-accordion:not([hidden])').forEach(function (el) {
      if (el !== accEl) {
        el.hidden = true;
        var trigger = document.querySelector(
          '[data-accordion-id="' + el.id + '"]'
        );
        if (trigger) {
          trigger.setAttribute('aria-expanded', 'false');
          var ch = trigger.querySelector('.vana-acc-chevron');
          if (ch) ch.style.transform = '';
        }
      }
    });

    // Toggle atual
    accEl.hidden = isOpen;
    item.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';

    if (!isOpen) highlightScheduleItem(item);
  }

  /* — Resolve VOD no índice e dispara swap + seek — */
  function loadAndPlay(vodId, segStart) {
    var vodsIndex = getVodIndex();
    var vod = vodsIndex[vodId] || null;

    if (vod && vod.provider === 'youtube' && vod.video_id) {
      swapStageYouTube(vod.video_id, vod['title_' + CFG.lang] || vod.title_pt || vod.title || '', segStart);
      return;
    }

    // Fallback: reload com query string
    var url = new URL(window.location.href);
    url.searchParams.set('vod_id', vodId);
    window.location.href = url.toString();
  }

  /* — Highlight visual no item clicado — */
  function highlightScheduleItem(item) {
    document.querySelectorAll('.vana-schedule-item.is-active').forEach(function (el) {
      el.classList.remove('is-active');
    });
    item.classList.add('is-active');
  }

  /* — Swap Stage YouTube + seek opcional — */
  function swapStageYouTube(videoId, title, segStart) {
    var sec    = segStart ? timeToSec(segStart) : 0;
    var iframe = document.getElementById('vanaStageIframe');

    if (iframe) {
      var src = 'https://www.youtube-nocookie.com/embed/' + videoId
        + '?rel=0&modestbranding=1&enablejsapi=1&autoplay=1&origin=' + encodeURIComponent(window.location.origin);
      if (sec > 0) src += '&start=' + sec;
      iframe.src = src;
    }

    var titleEl = document.getElementById('vanaStageTitle')
                  || document.querySelector('[data-vana-stage-title]');
    if (titleEl && title) titleEl.textContent = title;

    var stage = document.querySelector('.vana-stage');
    if (!stage && iframe) stage = iframe.closest('section');
    if (stage) stage.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function initDrawer() {
    var btn     = document.querySelector("[data-drawer=\"vana-tour-drawer\"]");
    var drawer  = document.getElementById("vana-tour-drawer");
    var overlay = document.getElementById("vana-drawer-overlay");
    var close   = drawer ? drawer.querySelector(".vana-drawer__close") : null;
    var back    = document.getElementById("vana-drawer-back");
    var titleEl = document.getElementById("vana-drawer-title");
    var bodyL1  = document.getElementById("vana-drawer-body");
    var bodyL2  = document.getElementById("vana-drawer-visits");

    if (!btn || !drawer) {
      console.warn("[VanaDrawer] elemento nao encontrado", { btn: btn, drawer: drawer });
      return;
    }

    function openDrawer() {
      drawer.classList.add("is-open");
      if (overlay) overlay.classList.add("is-open");
      btn.setAttribute("aria-expanded", "true");
      document.body.style.overflow = "hidden";
      if (close) close.focus();
      loadToursIfNeeded();
    }

    function closeDrawer() {
      drawer.classList.remove("is-open");
      if (overlay) overlay.classList.remove("is-open");
      btn.setAttribute("aria-expanded", "false");
      document.body.style.overflow = "";
      btn.focus();
    }

    function setLevel(level) {
      if (!bodyL1 || !bodyL2 || !back) return;
      if (level === 1) {
        bodyL1.style.display  = "block";
        bodyL2.style.display  = "none";
        back.style.display    = "none";
        if (titleEl) titleEl.textContent = "Tours";
      } else {
        bodyL1.style.display  = "none";
        bodyL2.style.display  = "block";
        back.style.display    = "flex";
      }
    }

    var toursLoaded = false;

    function loadToursIfNeeded() {
      if (toursLoaded) return;
      var loadingEl = document.getElementById("vana-drawer-loading");
      var listEl    = document.getElementById("vana-drawer-tour-list");
      if (!loadingEl || !listEl) return;

      var wd = window.vanaDrawer || {};
      var fd = new FormData();
      fd.append("action",   "vana_get_tours");
      fd.append("visit_id", wd.visitId || 0);
      fd.append("_wpnonce", wd.nonce   || "");

      loadingEl.style.display = "flex";
      listEl.removeAttribute("hidden");
      listEl.style.display    = "none";

      fetch(wd.ajaxUrl || "/wp-admin/admin-ajax.php", { method: "POST", body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
          if (!res.success) throw new Error("erro ajax");
          renderTours(res.data, listEl, wd);
          toursLoaded = true;
        })
        .catch(function(err) {
          console.error("[VanaDrawer] loadTours:", err);
          listEl.innerHTML     = "<li style=\"padding:20px;color:rgba(240,237,230,.4);font-size:13px;\">Erro ao carregar tours.</li>";
          listEl.style.display = "block";
        })
        .finally(function() { loadingEl.style.display = "none"; });
    }

    function renderTours(tours, listEl, wd) {
      listEl.innerHTML = "";
      var autoId = null, autoTitle = "";

      tours.forEach(function(tour) {
        var li = document.createElement("li");
        li.className = "vana-drawer__tour-item";
        if (tour.is_current) {
          li.classList.add("is-current-tour");
          autoId    = tour.id;
          autoTitle = tour.title;
        }
        li.innerHTML =
          "<button class=\"vana-drawer__tour-btn\">" +
            "<span class=\"vana-drawer__tour-name\">" + escHtml(tour.title) + "</span>" +
            "<span class=\"vana-drawer__tour-meta\">" + tour.visit_count +
              " visita" + (tour.visit_count !== 1 ? "s" : "") + "</span>" +
            "<svg class=\"vana-drawer__chevron\" width=\"12\" height=\"12\" viewBox=\"0 0 12 12\" fill=\"none\">" +
              "<path d=\"M4 2L8 6L4 10\" stroke=\"currentColor\" stroke-width=\"1.5\" stroke-linecap=\"round\"/>" +
            "</svg>" +
          "</button>";
        li.querySelector("button").addEventListener("click", function() {
          loadVisits(tour.id, tour.title, wd);
        });
        listEl.appendChild(li);
      });

      listEl.style.display = "block";
      if (autoId) setTimeout(function() { loadVisits(autoId, autoTitle, wd); }, 120);
    }

    function loadVisits(tourId, tourTitle, wd) {
      setLevel(2);
      if (titleEl) titleEl.textContent = tourTitle;
      var loadingEl = document.getElementById("vana-drawer-visits-loading");
      var listEl    = document.getElementById("vana-drawer-visit-list");
      if (!loadingEl || !listEl) return;

      loadingEl.style.display = "flex";
      listEl.style.display    = "none";
      listEl.innerHTML        = "";

      var fd = new FormData();
      fd.append("action",   "vana_get_tour_visits");
      fd.append("tour_id",  tourId);
      fd.append("visit_id", wd.visitId || 0);
      fd.append("lang",     wd.lang    || "pt");
      fd.append("_wpnonce", wd.nonce   || "");

      fetch(wd.ajaxUrl || "/wp-admin/admin-ajax.php", { method: "POST", body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
          if (!res.success) throw new Error("erro ajax");
          renderVisits(res.data, listEl, wd);
        })
        .catch(function(err) {
          console.error("[VanaDrawer] loadVisits:", err);
          listEl.innerHTML     = "<li style=\"padding:20px;color:rgba(240,237,230,.4);font-size:13px;\">Erro ao carregar visitas.</li>";
          listEl.style.display = "block";
        })
        .finally(function() { loadingEl.style.display = "none"; });
    }

    function renderVisits(visits, listEl, wd) {
      listEl.innerHTML = "";
      visits.forEach(function(v) {
        var li   = document.createElement("li");
        li.className = "vana-drawer__visit-item" + (v.is_current ? " is-current-visit" : "");
        var href = wd.lang === "en" ? v.permalink + "?lang=en" : v.permalink;
        var dot  = "vana-drawer__visit-dot" + (v.is_current ? "" : " vana-drawer__visit-dot--empty");
        var date = v.start_date ? fmtDate(v.start_date, wd.lang) : "";
        li.innerHTML =
          "<a class=\"vana-drawer__visit-link\" href=\"" + escHtml(href) + "\">" +
            "<span class=\"" + dot + "\" aria-hidden=\"true\"></span>" +
            "<span class=\"vana-drawer__visit-name\">" + escHtml(v.title) + "</span>" +
            (date ? "<span class=\"vana-drawer__visit-date\">" + date + "</span>" : "") +
          "</a>";
        listEl.appendChild(li);
      });
      listEl.style.display = "block";
      var cur = listEl.querySelector(".is-current-visit");
      if (cur) setTimeout(function() { cur.scrollIntoView({ block: "center", behavior: "smooth" }); }, 100);
    }

    function fmtDate(d, lang) {
      try {
        return new Date(d + "T12:00:00").toLocaleDateString(
          lang === "en" ? "en-US" : "pt-BR",
          { day: "numeric", month: "short", year: "numeric" }
        );
      } catch(e) { return d; }
    }

    btn.addEventListener("click",  openDrawer);
    if (close)   close.addEventListener("click",   closeDrawer);
    if (back)    back.addEventListener("click",    function() { setLevel(1); });
    if (overlay) overlay.addEventListener("click", closeDrawer);
    document.addEventListener("keydown", function(e) {
      if (e.key === "Escape" && drawer.classList.contains("is-open")) closeDrawer();
    });
  }

  /* ----------------------------------------------------------
     9. NOTIFY — Sino (Fase 2 — push notifications)
     ---------------------------------------------------------- */
  function initNotify() {
    var btn = document.getElementById("vana-notify-btn");
    if (!btn) return;
    var subscribed = localStorage.getItem("vana_notify_" + CFG.visitId) === "1";
    if (subscribed) btn.classList.add("is-subscribed");
    btn.addEventListener("click", function() {
      subscribed = !subscribed;
      if (subscribed) {
        localStorage.setItem("vana_notify_" + CFG.visitId, "1");
        btn.classList.add("is-subscribed");
        btn.setAttribute("aria-label", "Notificações ativas");
      } else {
        localStorage.removeItem("vana_notify_" + CFG.visitId);
        btn.classList.remove("is-subscribed");
        btn.setAttribute("aria-label", "Ativar notificações");
      }
    });
  }

  /* ----------------------------------------------------------
     10. TITLE POPOVER
     ---------------------------------------------------------- */
  function initTitlePopover() {
    var pop = document.createElement('div');
    pop.className = 'vana-title-popover';
    document.body.appendChild(pop);

    var activeWrap = null;

    function show(wrap) {
      var full = wrap.dataset.titleFull || '';
      if (!full) return;

      pop.innerHTML = '<strong>' + escHtml(full) + '</strong>';

      var rect = wrap.getBoundingClientRect();
      var left = rect.left + 100;
      var top  = rect.top + rect.height / 2;

      if (left + 270 > window.innerWidth) {
        left = rect.right - 270;
      }

      pop.style.cssText = 'position:fixed;top:' + top + 'px;left:' + left + 'px;transform:translateY(-50%);pointer-events:none;';
      pop.classList.add('is-visible');
      activeWrap = wrap;
    }

    function hide() {
      pop.classList.remove('is-visible');
      activeWrap = null;
    }

    document.addEventListener('click', function (e) {
      var strong = e.target.closest('.vana-schedule-title strong');

      if (strong) {
        var wrap = strong.closest('.vana-schedule-item-wrap');
        if (activeWrap === wrap) {
          hide();
        } else {
          show(wrap);
        }
        e.stopPropagation();
        return;
      }

      hide();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') hide();
    });
  }

  /* ----------------------------------------------------------
     7. INIT — aguarda DOM pronto
     ---------------------------------------------------------- */
  function init() {
    initDualTimezone();
    initGallery();
    initSegments();
    initFbFallback();
    initCopyLink();
    initTabsKeyboard();
    initScheduleVod();
    initDrawer();
    initNotify();
    initTitlePopover();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

}(<?php echo wp_json_encode($js_data); ?>));
</script>

<?php /* ── HARI-KATHĀ LOADER ─────────────────────────────────── */ ?>
<script>
(function () {
  'use strict';

  var API_BASE = <?php echo wp_json_encode( rest_url( 'vana/v1' ) ); ?>;

  var state = {
    visitId     : null,
    activeDay   : null,
    lang        : 'pt',
    activeKatha : null,
    page        : 1,
    hasMore     : false,
    loading     : false,
  };

  var root, introEl, listEl, passagesEl;

  function init() {
    root = document.getElementById('vana-section-hari-katha');
    if (!root) return;

    introEl    = root.querySelector('.vana-hk__intro');
    listEl     = root.querySelector('[data-role="katha-list"]');
    passagesEl = root.querySelector('[data-role="passage-list"]');

    state.visitId   = root.getAttribute('data-visit-id')  || '';
    state.activeDay = root.getAttribute('data-day')        || '';
    state.lang      = root.getAttribute('data-lang')       || 'pt';

    if (!state.visitId || !state.activeDay) return;

    fetchKathas();
  }

  function fetchKathas() {
    var url = API_BASE
      + '/kathas?visit_id=' + encodeURIComponent(state.visitId)
      + '&day='             + encodeURIComponent(state.activeDay);

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json.success || !json.data || !json.data.items) {
          introEl.textContent = t('errK');
          return;
        }
        renderKathaList(json.data.items);
      })
      .catch(function () {
        introEl.textContent = t('errK');
      });
  }

function renderKathaList(kathas) {
  if (!kathas.length) {
    introEl.textContent = t('empty');
    return;
  }

  introEl.hidden   = true;
  listEl.innerHTML = '';

  var periodLabel = {
    morning: '🌅 ' + t('morning'),
    midday:  '☀️ ' + t('midday'),
    night:   '🌙 ' + t('night'),
    other:   '📌 ' + t('other'),
  };

  kathas.forEach(function (katha) {
    var title   = pickLang(katha, 'title')   || t('untitled');
    var excerpt = pickLang(katha, 'excerpt') || '';
    var badge   = periodLabel[katha.period]  || '';
    var count   = katha.passage_count        || 0;

    var btn = document.createElement('button');
    btn.type      = 'button';
    btn.className = 'vana-hk-card';
    btn.setAttribute('aria-pressed', 'false');
    btn.dataset.kathaId = katha.id;

    btn.innerHTML =
      '<span class="vana-hk-card__period">'  + esc(badge)  + '</span>' +
      '<span class="vana-hk-card__count">'   + count + ' ' + t('passages') + '</span>' +
      '<span class="vana-hk-card__title">'   + esc(title)  + '</span>' +
      (excerpt
        ? '<span class="vana-hk-card__excerpt">' + esc(excerpt) + '</span>'
        : '');

    btn.addEventListener('click', function () { openKatha(katha); });
    listEl.appendChild(btn);
  });
}

function openKatha(katha) {
  listEl.querySelectorAll('.vana-hk-card').forEach(function (el) {
    var active = String(el.dataset.kathaId) === String(katha.id);
    el.setAttribute('aria-pressed', active ? 'true' : 'false');
  });

  state.activeKatha = katha;
  state.page        = 1;
  state.hasMore     = false;

  listEl.hidden     = true;
  passagesEl.hidden = false;
  passagesEl.innerHTML =
    '<button type="button" class="vana-hk-back" id="vana-hk-back">' +
      '← ' + t('backToList') +
    '</button>' +
    '<h3 class="vana-hk-katha-title">' +
      esc(pickLang(katha, 'title') || '') +
    '</h3>' +
    '<div id="vana-hk-passages-inner">' +
      '<p class="vana-hk__intro">' + t('loading') + '</p>' +
    '</div>' +
    '<div id="vana-hk-pagination" hidden>' +
      '<button type="button" class="vana-hk-load-more" id="vana-hk-load-more">' +
        t('loadMore') +
      '</button>' +
    '</div>';

  document.getElementById('vana-hk-back')
    .addEventListener('click', showKathaList);
  document.getElementById('vana-hk-load-more')
    .addEventListener('click', loadMore);

  fetchPassages(katha.id, 1);
}

function showKathaList() {
  state.activeKatha    = null;
  state.page           = 1;
  state.hasMore        = false;
  passagesEl.innerHTML = '';
  passagesEl.hidden    = true;
  listEl.hidden        = false;

  listEl.querySelectorAll('.vana-hk-card').forEach(function (el) {
    el.setAttribute('aria-pressed', 'false');
  });
}

  function fetchPassages(kathaId, page) {
    if (state.loading) return;
    state.loading = true;

    var inner = document.getElementById('vana-hk-passages-inner');
    var url   = API_BASE
      + '/passages?katha_id=' + encodeURIComponent(kathaId)
      + '&page='              + page;

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (json) {
        state.loading = false;

        if (!json.success || !json.data || !json.data.items) {
          if (inner) inner.innerHTML = '<p class="vana-hk__error">' + t('errP') + '</p>';
          return;
        }

        var body = json.data;
        if (page === 1 && inner) inner.innerHTML = '';
        renderPassages(body.items, inner);

        state.page    = page;
        state.hasMore = body.has_more;

        var pag = document.getElementById('vana-hk-pagination');
        if (pag) pag.hidden = !body.has_more;
      })
      .catch(function () {
        state.loading = false;
        var inner2 = document.getElementById('vana-hk-passages-inner');
        if (inner2) inner2.innerHTML = '<p class="vana-hk__error">' + t('errP') + '</p>';
      });
  }

function renderPassages(passages, container) {
  if (!container) return;

  var kindIcon = {
    narrative:           '📖',
    instruction:         '📘',
    verse_commentary:    '🕉️',
    dialogue:            '💬',
    anecdote:            '📜',
    prayer:              '🙏',
    'gaura-lila':        '🌸',
    story:               '📖',
    teaching:            '📘',
    other:               '•',
  };

  passages.forEach(function (p) {
    var kind       = p.passage_kind || 'other';
    var icon       = kindIcon[kind] || '•';
    var hookVal    = pickLangField(p, 'hook');
    var quoteVal   = pickLangField(p, 'key_quote');
    var contentVal = pickLangField(p, 'content');

    var article = document.createElement('article');
    article.className    = 'vana-hk-passage';
    article.id           = 'hk-passage-' + p.id;
    article.dataset.kind = kind;

    var tsHtml = p.t_start
      ? '<span class="vana-hk-passage__ts" role="button" tabindex="0"' +
          ' title="' + t('seekTo') + '" data-t="' + esc(p.t_start) + '">' +
          esc(p.t_start) +
        '</span>'
      : '';

    var badges =
      (p.reel_worthy
        ? '<span class="vana-hk-passage__badge" title="' + t('reelWorthy') + '">🎬</span>'
        : '') +
      (p.contains_confidential_content
        ? '<span class="vana-hk-passage__badge" title="' + t('confidential') + '">🔒</span>'
        : '');

    article.innerHTML =
      '<header class="vana-hk-passage__header">'                                             +
      '  <span class="vana-hk-passage__index">#' + (p.index || '') + '</span>'               +
      '  <span class="vana-hk-passage__kind">'   + icon + ' ' + esc(kind) + '</span>'        +
      badges + tsHtml                                                                          +
      '</header>'                                                                              +
      (hookVal
        ? '<p class="vana-hk-passage__hook">'             + esc(hookVal)  + '</p>'    : '') +
      (quoteVal
        ? '<blockquote class="vana-hk-passage__quote">"'  + esc(quoteVal) + '"</blockquote>' : '') +
      (contentVal ? '<div class="vana-hk-passage__content"></div>' : '') +
      '<footer class="vana-hk-passage__footer">'                                              +
      '  <a class="vana-hk-passage__link" href="' + esc(p.permalink || '') + '">'            +
      '    🔗 permalink'                                                                       +
      '  </a>'                                                                                 +
      '</footer>';

    if (contentVal) {
      var contentEl = article.querySelector('.vana-hk-passage__content');
      if (contentEl) contentEl.textContent = contentVal;
    }

    /* ── Timestamp → seek no Stage ── */
    var tsEl = article.querySelector('.vana-hk-passage__ts');
    if (tsEl) {
      var doSeek = function () {
        var parts = String(tsEl.dataset.t).split(':').map(Number);
        var sec   = parts.length === 3
          ? parts[0] * 3600 + parts[1] * 60 + parts[2]
          : parts[0] * 60 + parts[1];

        var iframe = document.getElementById('vanaStageIframe');
        if (iframe && iframe.contentWindow) {
          iframe.contentWindow.postMessage(
            JSON.stringify({ event: 'command', func: 'seekTo', args: [sec, true] }),
            'https://www.youtube-nocookie.com'
          );
          var target = iframe.closest('section') || iframe;
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      };
      tsEl.addEventListener('click', doSeek);
      tsEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); doSeek(); }
      });
    }

    container.appendChild(article);
  });
}

  function loadMore() {
    if (!state.activeKatha || !state.hasMore || state.loading) return;
    fetchPassages(state.activeKatha.id, state.page + 1);
  }

  function pickLang(obj, field) {
    if (state.lang === 'en') return obj[field + '_en'] || obj[field + '_pt'] || '';
    return obj[field + '_pt'] || obj[field + '_en'] || '';
  }

  function pickLangField(p, field) {
    if (state.lang === 'en') return p[field + '_en'] || p[field + '_pt'] || '';
    return p[field + '_pt'] || p[field + '_en'] || '';
  }

  function esc(str) {
    return String(str || '')
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  }

  function t(key) {
    var strings = {
        pt: {
          errK:          'Erro ao carregar kathās.',
          errP:          'Erro ao carregar passages.',
          empty:         'Nenhuma kathā registrada para este dia.',
          loading:       'Carregando…',
          loadMore:      'Carregar mais',
          backToList:    'Kathās do dia',
          untitled:      'Sem título',
          passages:      'passages',
          pendingReview: 'Revisão pendente',
          reelWorthy:    'Potencial para Reels',
          confidential:  'Conteúdo confidencial',
          morning:       'Manhã',
          midday:        'Tarde',
          night:         'Noite',
          other:         'Outro',
          seekTo:        'Ir para este trecho no player',
        },
        en: {
          errK:          'Error loading kathās.',
          errP:          'Error loading passages.',
          empty:         'No kathā registered for this day.',
          loading:       'Loading…',
          loadMore:      'Load more',
          backToList:    'Day kathās',
          untitled:      'Untitled',
          passages:      'passages',
          pendingReview: 'Pending review',
          reelWorthy:    'Reel potential',
          confidential:  'Confidential content',
          morning:       'Morning',
          midday:        'Afternoon',
          night:         'Night',
          other:         'Other',
          seekTo:        'Jump to this moment in the player',
        },
    };
    return (strings[state.lang] || strings.pt)[key] || key;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

}());
</script>
