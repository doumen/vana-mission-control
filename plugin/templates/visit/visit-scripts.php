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
    'i18n'            => [
        'lightbox_close'  => $lang === 'en' ? 'Close'          : 'Fechar',
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
?>
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

  /**
   * Manda postMessage para o iframe do YouTube Stage.
   */
  function ytPostMessage(msg) {
    var iframe = document.getElementById('vanaStageIframe');
    if (iframe && iframe.contentWindow) {
      iframe.contentWindow.postMessage(JSON.stringify(msg), '*');
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
    ov.setAttribute('aria-label',      CFG.i18n.lightbox_close);
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

    // Garante que o iframe do YT usa enablejsapi=1
    var iframe = document.getElementById('vanaStageIframe');
    if (iframe) {
      var src = iframe.getAttribute('src') || '';
      if (src.indexOf('enablejsapi') === -1) {
        iframe.setAttribute(
          'src',
          src + (src.indexOf('?') > -1 ? '&' : '?') + 'enablejsapi=1'
        );
      }
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

    var TIMEOUT = 8000; // ms para considerar falha

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
     7. INIT — aguarda DOM pronto
     ---------------------------------------------------------- */
  function init() {
    initDualTimezone();
    initGallery();
    initSegments();
    initFbFallback();
    initCopyLink();
    initTabsKeyboard();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

}(<?php echo wp_json_encode($js_data); ?>));
</script>
