<?php
/**
 * Asset: Vana Visit — Scripts
 * Arquivo: templates/visit/assets/visit-scripts.php
 *
 * Incluído pelo orquestrador APÓS o conteúdo principal.
 *
 * Módulos (cada um é um IIFE isolado):
 *   1. VanaStage       — troca de vídeo no palco + seek por segmento
 *   2. VanaFbFallback  — fallback do player Facebook
 *   3. VanaCopyLink    — copiar link do Facebook
 *   4. VanaMap         — carregamento lazy do Google Maps
 *   5. VanaDualTZ      — conversão de horário UTC → fuso local do visitante
 *   6. VanaGallery     — lightbox de fotos (prev/next + teclado)
 *   7. VanaSangha      — modal de momentos da Sangha
 *   8. VanaForm        — envio AJAX do formulário de oferenda
 *   9. VanaModal       — núcleo do modal (open/close/esc)
 */
defined('ABSPATH') || exit;

// Dados PHP → JS (passados de forma segura via wp_json_encode)
$js_i18n = [
    'sending'    => $lang === 'en' ? 'Sending...'                          : 'Enviando...',
    'submit'     => $lang === 'en' ? 'Send Offering'                       : 'Enviar Oferenda',
    'ok'         => $lang === 'en' ? 'Sent! Awaiting moderation.'          : 'Enviado! Aguardando moderação.',
    'err'        => $lang === 'en' ? 'Error.'                              : 'Erro.',
    'conn'       => $lang === 'en' ? 'Connection error.'                   : 'Erro de conexão.',
    'rate'       => $lang === 'en' ? 'Too many submissions. Try again later.' : 'Muitos envios. Tente mais tarde.',
    'copy_ok'    => $lang === 'en' ? 'Copied!'                             : 'Copiado!',
    'copy_err'   => $lang === 'en' ? 'Could not copy.'                     : 'Não foi possível copiar.',
    'your_time'  => $lang === 'en' ? 'Your time'                           : 'Seu horário',
    'close'      => $lang === 'en' ? 'Close'                               : 'Fechar',
];

$js_ajax_url = admin_url('admin-ajax.php');
$js_visit_id = (int) $visit_id;
$js_nonce    = wp_create_nonce('vana_checkin_' . $visit_id);
?>
<script>
/* =============================================================
   VANA VISIT SCRIPTS — <?php echo esc_js(get_the_title()); ?>
   ============================================================= */

(function () {
  'use strict';

  /* -----------------------------------------------------------
     DADOS GLOBAIS (injetados pelo PHP)
     ----------------------------------------------------------- */
  const VANA = {
    ajaxUrl : <?php echo wp_json_encode($js_ajax_url); ?>,
    visitId : <?php echo (int) $js_visit_id; ?>,
    nonce   : <?php echo wp_json_encode($js_nonce); ?>,
    i18n    : <?php echo wp_json_encode($js_i18n); ?>,
  };

  /* ===========================================================
     1. VanaStage — Palco principal
        - Escuta cliques nos cards VOD  → troca iframe do Stage
        - Escuta cliques nos segmentos → seek no iframe YouTube
     =========================================================== */
  (function VanaStage() {
    const iframe = document.getElementById('vanaStageIframe');
    const title  = document.getElementById('vanaStageTitle');

    // 1a. Seek por segmento (capítulos)
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-vana-stage-seg]');
      if (!btn || !iframe) return;

      const t = (btn.dataset.t || '').trim();
      if (!t) return;

      // Converte MM:SS ou HH:MM:SS → segundos
      const parts = t.split(':').map(Number);
      let seconds = 0;
      if (parts.length === 2) seconds = parts[0] * 60 + parts[1];
      if (parts.length === 3) seconds = parts[0] * 3600 + parts[1] * 60 + parts[2];

      // Reconstrói src com start=N (força reload no timestamp)
      const src = iframe.src.split('?')[0];
      iframe.src = src + '?rel=0&autoplay=1&start=' + seconds;

      // Scroll suave até o Stage
      iframe.closest('.vana-stage')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  })();

  /* ===========================================================
     2. VanaFbFallback — Fallback do player Facebook
        Detecta se o iframe falhou a carregar (via timeout)
        e exibe o painel de fallback com link + copiar.
     =========================================================== */
  (function VanaFbFallback() {
    const fbIframe  = document.getElementById('vanaFbIframe');
    const fbFallback = document.getElementById('vanaFbFallback');
    if (!fbIframe || !fbFallback) return;

    // Tenta detectar erro de carregamento após 8s
    const timer = setTimeout(function () {
      try {
        // Se contentDocument está acessível e vazio, falhou
        const doc = fbIframe.contentDocument || fbIframe.contentWindow?.document;
        if (!doc || doc.body?.innerHTML === '') {
          fbFallback.style.display = 'flex';
        }
      } catch (_) {
        // Cross-origin block = iframe carregou (Facebook bloqueou JS, não o vídeo)
      }
    }, 8000);

    fbIframe.addEventListener('load', function () {
      clearTimeout(timer);
    });
  })();

  /* ===========================================================
     3. VanaCopyLink — Copiar link do Facebook
     =========================================================== */
  (function VanaCopyLink() {
    const btn = document.getElementById('vanaCopyFbLink');
    if (!btn) return;

    btn.addEventListener('click', function () {
      const url = btn.dataset.url || '';
      if (!url) return;

      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url).then(function () {
          btn.textContent = VANA.i18n.copy_ok;
          setTimeout(function () {
            btn.textContent = VANA.i18n.copy_err.includes('copy') ? 'Copy link' : 'Copiar Link';
          }, 2500);
        }).catch(function () {
          btn.textContent = VANA.i18n.copy_err;
        });
      } else {
        // Fallback para navegadores antigos
        const ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity  = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand('copy');
          btn.textContent = VANA.i18n.copy_ok;
        } catch (_) {
          btn.textContent = VANA.i18n.copy_err;
        }
        document.body.removeChild(ta);
      }
    });
  })();

  /* ===========================================================
     4. VanaMap — Carregamento lazy do Google Maps
     =========================================================== */
  (function VanaMap() {
    const btn    = document.getElementById('vanaLoadMapBtn');
    const wrap   = document.getElementById('vanaMapWrap');
    const iframe = document.getElementById('vanaMapIframe');
    if (!btn || !wrap || !iframe) return;

    btn.addEventListener('click', function () {
      if (!iframe.getAttribute('src')) {
        iframe.setAttribute('src', iframe.getAttribute('data-src') || '');
      }
      wrap.style.display = 'block';
      btn.disabled = true;
    });
  })();

  /* ===========================================================
     5. VanaDualTZ — Conversão UTC → fuso local do visitante
        Lê data-ts (Unix timestamp) e injeta horário local
        formatado abaixo do horário do evento.
     =========================================================== */
  (function VanaDualTZ() {
    const targets = document.querySelectorAll('.vana-local-time-target[data-ts]');
    if (!targets.length) return;

    // Detecta se o visitante está num fuso diferente do evento
    // (o offset do evento já está no timestamp absoluto)
    targets.forEach(function (el) {
      const ts    = parseInt(el.dataset.ts, 10);
      const label = el.dataset.label || 'Your time';
      if (!ts) return;

      try {
        const date = new Date(ts * 1000);
        const hhmm = date.toLocaleTimeString([], {
          hour:   '2-digit',
          minute: '2-digit',
          hour12: false,
        });
        el.textContent = label + ': ' + hhmm;
      } catch (_) {
        // Silencia erros de locale em browsers antigos
      }
    });
  })();

  /* ===========================================================
     6. VanaGallery — Lightbox de fotos
        Abre modal ao clicar numa foto da galeria.
        Suporta navegação prev/next e teclado (← →  Esc).
     =========================================================== */
  (function VanaGallery() {
    // Coleta todas as fotos da galeria ativa
    const items   = Array.from(document.querySelectorAll('[data-vana-photo]'));
    if (!items.length) return;

    const modal   = document.getElementById('vanaModal');
    const imgEl   = document.getElementById('vanaModalImg');
    const caption = document.getElementById('vanaModalCaption');
    if (!modal || !imgEl) return;

    let current = 0;

    function show(idx) {
      if (idx < 0 || idx >= items.length) return;
      current = idx;
      const item = items[idx];
      imgEl.src = item.dataset.full || '';
      if (caption) caption.textContent = item.dataset.caption || '';
      openModal();
    }

    items.forEach(function (el, idx) {
      el.addEventListener('click', function () { show(idx); });
      el.setAttribute('tabindex', '0');
      el.setAttribute('role', 'button');
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); show(idx); }
      });
    });

    // Navegação prev/next dentro do modal de galeria
    document.getElementById('vanaModalPrev')
      ?.addEventListener('click', function () { show(current - 1); });
    document.getElementById('vanaModalNext')
      ?.addEventListener('click', function () { show(current + 1); });

    document.addEventListener('keydown', function (e) {
      if (!modal.classList.contains('is-active')) return;
      if (e.key === 'ArrowLeft')  show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });
  })();

  /* ===========================================================
     7. VanaSangha — Modal de momentos da Sangha
        Abre modal ao clicar num cartão de momento.
        Popula título, mensagem, imagem ou vídeo externo.
     =========================================================== */
  (function VanaSangha() {
    const triggers = document.querySelectorAll('[data-vana-sangha-item]');
    if (!triggers.length) return;

    const modal      = document.getElementById('vanaModal');
    const msgEl      = document.getElementById('vanaModalMessage');
    const kickerEl   = document.getElementById('vanaModalKicker');
    const titleEl    = document.getElementById('vanaModalTitle');
    if (!modal || !msgEl) return;

    triggers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const kicker  = btn.dataset.kicker      || '';
        const name    = btn.dataset.title        || '';
        const message = btn.dataset.message      || '';
        const image   = btn.dataset.image        || '';
        const extUrl  = btn.dataset.externalUrl  || '';

        if (kickerEl) kickerEl.textContent = kicker;
        if (titleEl)  titleEl.textContent  = name;

        // Monta o conteúdo do corpo do modal
        let html = '';

        if (extUrl) {
          // Vídeo externo (YouTube, Drive, Facebook)
          html += buildEmbedHtml(extUrl);
        } else if (image) {
          html += '<img src="' + escHtml(image) + '" alt="" style="max-width:100%;border-radius:8px;margin-bottom:16px;">';
        }

        if (message) {
          html += '<p style="line-height:1.7;font-size:1.05rem;color:#334155;">' + escHtml(message) + '</p>';
        }

        msgEl.innerHTML = html;
        openModal();
      });
    });

    // Constrói HTML de embed seguro para o modal
    function buildEmbedHtml(url) {
      // YouTube
      const ytMatch = url.match(
        /(?:youtube(?:-nocookie)?\.com\/(?:[^/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?/ ]{11})/i
      );
      if (ytMatch) {
        return '<div class="vana-embed"><iframe src="https://www.youtube-nocookie.com/embed/'
          + escHtml(ytMatch[1]) + '?rel=0&autoplay=1" allowfullscreen loading="lazy"></iframe></div>';
      }

      // Google Drive
      const driveMatch = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
      if (driveMatch && url.includes('drive.google.com')) {
        return '<div class="vana-embed"><iframe src="https://drive.google.com/file/d/'
          + escHtml(driveMatch[1]) + '/preview" allow="autoplay" loading="lazy"></iframe></div>';
      }

      // Facebook
      if (url.includes('facebook.com') || url.includes('fb.watch')) {
        const fbEmbed = 'https://www.facebook.com/plugins/video.php?href='
          + encodeURIComponent(url) + '&show_text=0&width=800';
        return '<div class="vana-embed"><iframe src="' + escHtml(fbEmbed)
          + '" scrolling="no" allowfullscreen></iframe></div>';
      }

      // Fallback genérico
      return '<p><a href="' + escHtml(url) + '" target="_blank" rel="noopener" '
        + 'style="font-weight:900;color:var(--vana-blue);">▶ ' + escHtml(url) + '</a></p>';
    }
  })();

  /* ===========================================================
     8. VanaForm — Envio AJAX do formulário de oferenda
        - Validação client-side
        - Geolocalização opcional (LGPD consent)
        - Rate-limit client-side (localStorage)
        - Feedback visual de estado
     =========================================================== */
  (function VanaForm() {
    const form = document.getElementById('vanaCheckinForm');
    if (!form) return;

    const submitBtn = form.querySelector('[type="submit"]');
    const feedback  = document.getElementById('vanaFormFeedback');

    // Geolocalização (só captura se consent marcado)
    const consentBox = document.getElementById('consentCityPublic');
    const latInput   = document.getElementById('vana_lat');
    const lngInput   = document.getElementById('vana_lng');

    if (consentBox && latInput && lngInput) {
      consentBox.addEventListener('change', function () {
        if (!this.checked) {
          latInput.value = '';
          lngInput.value = '';
          return;
        }
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            latInput.value = pos.coords.latitude.toFixed(6);
            lngInput.value = pos.coords.longitude.toFixed(6);
          },
          function () {
            latInput.value = '';
            lngInput.value = '';
          },
          { timeout: 8000 }
        );
      });
    }

    // Rate-limit client-side: máximo 3 envios por hora
    function isRateLimited() {
      const key  = 'vana_form_submissions';
      const now  = Date.now();
      const hour = 3600 * 1000;
      let   log  = [];
      try { log = JSON.parse(localStorage.getItem(key) || '[]'); } catch (_) {}
      log = log.filter(function (t) { return now - t < hour; });
      if (log.length >= 3) return true;
      log.push(now);
      try { localStorage.setItem(key, JSON.stringify(log)); } catch (_) {}
      return false;
    }

    function setFeedback(msg, isError) {
      if (!feedback) return;
      feedback.textContent  = msg;
      feedback.style.color  = isError ? '#dc2626' : '#16a34a';
      feedback.style.display = 'block';
    }

    function setLoading(loading) {
      if (!submitBtn) return;
      submitBtn.disabled    = loading;
      submitBtn.textContent = loading ? VANA.i18n.sending : VANA.i18n.submit;
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      // Honeypot
      if (form.querySelector('[name="website"]')?.value) return;

      if (isRateLimited()) {
        setFeedback(VANA.i18n.rate, true);
        return;
      }

      setLoading(true);
      if (feedback) feedback.style.display = 'none';

      const fd = new FormData(form);
      fd.set('action', 'vana_checkin');
      fd.set('nonce',  VANA.nonce);

      fetch(VANA.ajaxUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success || res.ok) {
            setFeedback(VANA.i18n.ok, false);
            form.reset();
          } else {
            setFeedback(res.data?.message || VANA.i18n.err, true);
          }
        })
        .catch(function () {
          setFeedback(VANA.i18n.conn, true);
        })
        .finally(function () {
          setLoading(false);
        });
    });
  })();

  /* ===========================================================
     9. VanaModal — Núcleo do modal (open / close / Esc)
        Funções globais openModal() e closeModal()
        usadas pelos módulos Gallery e Sangha.
     =========================================================== */
  (function VanaModal() {
    const modal    = document.getElementById('vanaModal');
    const backdrop = document.getElementById('vanaModalBackdrop');
    const closeBtn = document.getElementById('vanaModalClose');
    if (!modal) return;

    window.openModal = function () {
      modal.classList.add('is-active');
      document.body.style.overflow = 'hidden';
      closeBtn?.focus();
    };

    window.closeModal = function () {
      modal.classList.remove('is-active');
      document.body.style.overflow = '';

      // Limpa iframes para parar áudio/vídeo
      modal.querySelectorAll('iframe').forEach(function (f) {
        f.src = f.src; // reload vazio
      });

      const msgEl = document.getElementById('vanaModalMessage');
      if (msgEl) msgEl.innerHTML = '';
    };

    closeBtn?.addEventListener('click', closeModal);
    backdrop?.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-active')) closeModal();
    });
  })();

  /* -----------------------------------------------------------
     UTIL: escHtml — escapa strings para inserção em innerHTML
     ----------------------------------------------------------- */
  function escHtml(str) {
    return String(str)
      .replace(/&/g,  '&amp;')
      .replace(/</g,  '&lt;')
      .replace(/>/g,  '&gt;')
      .replace(/"/g,  '&quot;')
      .replace(/'/g,  '&#039;');
  }

})(); // fim do IIFE global
</script>
