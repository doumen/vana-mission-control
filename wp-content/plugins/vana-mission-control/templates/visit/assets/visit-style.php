<?php
/**
 * Asset: Vana Visit — Estilos
 * Arquivo: templates/visit/assets/visit-style.php
 * Identidade: vanamadhuryam.com — v3.0 — 2026-02-23
 */
defined('ABSPATH') || exit;
?>
<style id="vana-visit-styles">

/* ============================================================
   1. DESIGN TOKENS — Identidade vanamadhuryam.com
   ============================================================ */
:root {
  /* Cores base */
  --vana-bg:            #ffffff;
  --vana-bg-soft:       #fafaf8;
  --vana-bg-card:       #ffffff;
  --vana-bg-elevated:   #f5f3ee;
  --vana-line:          rgba(15,23,42,0.10);
  --vana-line-solid:    #e2ddd5;

  /* Texto */
  --vana-text:          #0f172a;
  --vana-text-soft:     #334155;
  --vana-muted:         #64748b;

  /* Brand */
  --vana-gold:          #FFD906;
  --vana-gold-soft:     rgba(255,217,6,0.15);
  --vana-blue:          #170DF2;
  --vana-pink:          #F30B73;
  --vana-orange:        #F35C0B;
  --vana-pinksoft:      rgba(243,11,115,0.15);

  /* Hero gradient — fundo escuro com radiais coloridos */
  --vana-hero-gradient:
    radial-gradient(circle at 20% 20%,  rgba(243,11,115,.18)  0%, 25%, rgba(243,11,115,0)  50%),
    radial-gradient(circle at 80% 80%,  rgba(23,13,242,.15)   0%, 25%, rgba(23,13,242,0)   50%),
    radial-gradient(circle at 50% 10%,  rgba(243,92,11,.12)   0%, 20%, rgba(243,92,11,0)   40%),
    radial-gradient(circle at 10% 90%,  rgba(255,217,6,.10)   0%, 20%, rgba(255,217,6,0)   40%),
    linear-gradient(135deg, #FFD906 0%, #F35C0B 25%, #F30B73 55%, #170DF2 100%);
}

/* ============================================================
   2. RESET GLOBAL — garante tema escuro em toda a página
   ============================================================ */
html, body {
  background: var(--vana-bg) !important;
  color:      var(--vana-text) !important;
}

/* Anula estilos do Astra que colocam fundo branco */
.site, .site-content, #content, #primary, .ast-container,
.ast-page-builder-template, .entry-content, main#main,
.hfeed, body.vana-visit-page .ast-article-single {
  background: transparent !important;
  box-shadow: none !important;
  border:     none !important;
  padding:    0 !important;
  margin:     0 !important;
}
body.vana-visit-page .ast-separate-container .ast-article-single {
  background: transparent !important;
}

/* ============================================================
   3. LAYOUT BASE
   ============================================================ */
.vana-wrap {
  max-width:   1200px;
  margin:      0 auto;
  padding:     0 16px 80px;
  font-family: 'Questrial', sans-serif;
  color:       var(--vana-text);
}

/* ============================================================
   4. HERO
   ============================================================ */
.vana-hero {
  position:      relative;
  background:    var(--vana-hero-gradient);
  border-bottom: 3px solid var(--vana-gold);
  padding:       48px 24px 56px;
  text-align:    center;
  overflow:      hidden;
}

/* Versão com imagem de fundo */
.vana-hero--has-image {
  background-image:    var(--vana-hero-gradient), var(--vana-hero-bg);
  background-size:     cover;
  background-position: center;
  background-blend-mode: multiply;
}

.vana-hero__overlay {
  position:   absolute;
  inset:      0;
  background: linear-gradient(to bottom, rgba(0,0,0,0.10) 0%, rgba(0,0,0,0.20) 100%);
  z-index:    0;
}

.vana-hero__content {
  position:   relative;
  z-index:    1;
  max-width:  800px;
  margin:     0 auto;
}

.vana-hero__badge {
  background: rgba(0,0,0,0.25) !important;
  border-color: rgba(255,217,6,0.6) !important;
  color: #FFD906 !important;
  display:        inline-block;
  background:     rgba(255,217,6,0.12);
  border:         1px solid rgba(255,217,6,0.35);
  color:          var(--vana-gold);
  padding:        5px 16px;
  border-radius:  20px;
  font-weight:    700;
  font-size:      0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  margin-bottom:  16px;
  font-family:    'Syne', sans-serif;
}

.vana-hero__title {
  color: #ffffff !important;
  font-family: 'Syne', sans-serif;
  font-size:   clamp(1.8rem, 5vw, 3rem);
  font-weight: 900;
  color:       #ffffff;
  margin:      0 0 16px;
  line-height: 1.15;
  text-shadow: 0 2px 20px rgba(0,0,0,0.5);
}

.vana-hero__desc {
  color: rgba(255,255,255,0.88) !important;
  color:       var(--vana-text-soft);
  font-size:   1.05rem;
  line-height: 1.7;
  max-width:   600px;
  margin:      0 auto 24px;
}

/* ── Prev / Next ── */
.vana-hero__nav {
  display:         flex;
  justify-content: space-between;
  align-items:     center;
  gap:             12px;
  margin-top:      28px;
  padding:         0 4px;
}

.vana-hero__nav-btn {
  background: rgba(0,0,0,0.25) !important;
  border-color: rgba(255,255,255,0.25) !important;
  color: #ffffff !important;
  display:         inline-flex;
  align-items:     center;
  gap:             8px;
  background:      rgba(255,255,255,0.06);
  border:          1px solid rgba(255,255,255,0.12);
  border-radius:   10px;
  padding:         10px 16px;
  text-decoration: none;
  color:           var(--vana-text);
  font-weight:     700;
  font-size:       0.85rem;
  font-family:     'Syne', sans-serif;
  transition:      background .2s, border-color .2s, transform .2s;
  max-width:       45%;
  backdrop-filter: blur(8px);
}
.vana-hero__nav-btn:hover {
  background:    var(--vana-gold);
  border-color:  var(--vana-gold);
  color:         #0f172a;
  transform:     translateY(-2px);
}
.vana-hero__nav-btn--disabled { opacity: 0; pointer-events: none; }
.vana-hero__nav-label  { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.vana-hero__nav-arrow  { flex-shrink: 0; font-size: 1.1rem; }

/* ============================================================
   5. HEADER FIXO
   ============================================================ */
.vana-header {
  position:        sticky;
  top:             0;
  z-index:         1000;
  background:      rgba(255,255,255,0.96);
  backdrop-filter: blur(12px);
  border-bottom:   2px solid var(--vana-gold);
  box-shadow:      0 2px 12px rgba(0,0,0,0.08);
}
.vana-header__inner {
  display:         flex;
  align-items:     center;
  justify-content: space-between;
  max-width:       1200px;
  margin:          0 auto;
  padding:         10px 16px;
  gap:             12px;
}
.vana-header__tours-btn {
  display:       inline-flex;
  align-items:   center;
  gap:           8px;
  background:    rgba(255,255,255,0.06);
  border:        1px solid var(--vana-line);
  border-radius: 8px;
  padding:       8px 14px;
  cursor:        pointer;
  font-family:   'Syne', sans-serif;
  font-weight:   700;
  font-size:     0.9rem;
  color:         var(--vana-text);
  transition:    background .2s, border-color .2s;
  white-space:   nowrap;
}
.vana-header__tours-btn:hover {
  background:   var(--vana-gold);
  border-color: var(--vana-gold);
  color:        #0f172a;
}
.vana-header__context {
  flex:            1;
  text-align:      center;
  display:         flex;
  align-items:     center;
  justify-content: center;
  gap:             6px;
  overflow:        hidden;
}
.vana-header__title {
  font-family:   'Syne', sans-serif;
  font-weight:   700;
  font-size:     0.92rem;
  color:         var(--vana-text);
  white-space:   nowrap;
  overflow:      hidden;
  text-overflow: ellipsis;
  max-width:     280px;
}
.vana-header__sep { color: var(--vana-muted); }
.vana-header__day { color: var(--vana-muted); font-size: 0.82rem; white-space: nowrap; }
.vana-header__actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

.vana-header__notify-btn {
  display:         flex;
  align-items:     center;
  justify-content: center;
  background:      rgba(255,255,255,0.06);
  border:          1px solid var(--vana-line);
  border-radius:   8px;
  width:           38px;
  height:          38px;
  cursor:          pointer;
  color:           var(--vana-text);
  transition:      background .2s, border-color .2s, color .2s;
}
.vana-header__notify-btn:hover        { background: var(--vana-gold);  border-color: var(--vana-gold);  color: #0f172a; }
.vana-header__notify-btn.is-subscribed { background: rgba(22,163,74,.2); border-color: #16a34a; color: #4ade80; }

.vana-header__lang-btn {
  display:         inline-flex;
  align-items:     center;
  justify-content: center;
  background:      rgba(255,255,255,0.06);
  border:          1px solid var(--vana-line);
  border-radius:   8px;
  padding:         0 14px;
  height:          38px;
  cursor:          pointer;
  font-family:     'Syne', sans-serif;
  font-weight:     700;
  font-size:       0.85rem;
  color:           var(--vana-text);
  text-decoration: none;
  transition:      background .2s, border-color .2s, color .2s;
}
.vana-header__lang-btn:hover {
  background:   var(--vana-pink);
  border-color: var(--vana-pink);
  color:        #fff;
}

/* ============================================================
   6. TABS DE DIA
   ============================================================ */
.vana-tabs {
  display:    flex;
  gap:        8px;
  flex-wrap:  wrap;
  margin:     0 0 20px;   /* era 24px 0 28px — remove topo, reduz base */
  padding:    0 2px;
}
.vana-tab {
  padding:         9px 18px;
  border-radius:   999px;
  border:          1px solid var(--vana-line);
  font-weight:     800;
  text-decoration: none;
  transition:      background .2s, border-color .2s, color .2s, box-shadow .2s;
  font-family:     'Syne', sans-serif;
  background:      rgba(255,255,255,0.04);
  color:           var(--vana-text-soft);
  font-size:       0.88rem;
}
.vana-tab.active {
  background:   var(--vana-gold);
  color:        #0f172a;
  border-color: var(--vana-gold);
  box-shadow:   0 4px 14px rgba(255,217,6,0.3);
}
.vana-tab:not(.active):hover {
  background:   rgba(255,255,255,0.08);
  border-color: rgba(255,217,6,0.4);
  color:        var(--vana-text);
}

#vana-stage-spinner {
  min-height: 0;
  height:     0;
  overflow:   hidden;
  opacity:    0;
  transition: opacity .2s, height .2s;
}
#vana-stage-spinner.htmx-request {
  height:     56px;
  min-height: 56px;
  opacity:    1;
}

/* ============================================================
   7. STAGE
   ============================================================ */
.vana-stage {
  background:    var(--vana-bg-card);
  border:        1px solid var(--vana-line);
  border-radius: 16px;
  overflow:      hidden;
  box-shadow:    0 8px 32px rgba(0,0,0,0.3);
  margin-bottom: 24px;
}
.vana-stage-video {
  position:       relative;
  width:          100%;
  padding-bottom: 56.25%;
  height:         0;
  background:     #000;
}
.vana-stage-video iframe {
  position: absolute; inset: 0; width: 100%; height: 100%; border: 0;
}
.vana-stage-info {
  background:  var(--vana-bg-card);
  color:       var(--vana-text);
  border-top:  1px solid var(--vana-line);
  padding:     22px 24px;
}
.vana-stage-info-badge {
  background:     rgba(255,217,6,0.15);
  color:          var(--vana-gold);
  border:         1px solid rgba(255,217,6,0.3);
  padding:        4px 12px;
  border-radius:  6px;
  font-weight:    700;
  font-size:      0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.vana-stage-info h2 {
  color:      var(--vana-text) !important;
  font-size:  1.25rem !important;
}
.vana-stage-desc {
  color:       var(--vana-text-soft) !important;
  line-height: 1.65;
  font-size:   1rem;
}

/* Segmentos */
.vana-stage-segments {
  padding:    14px 24px 22px;
  border-top: 1px solid var(--vana-line);
  background: rgba(0,0,0,0.15);
}
.vana-seg-btn {
  padding:       7px 14px;
  border-radius: 999px;
  border:        1px solid var(--vana-line);
  background:    rgba(255,255,255,0.04);
  font-weight:   700;
  color:         var(--vana-text);
  font-size:     0.88rem;
  cursor:        pointer;
  transition:    border-color .2s, background .2s;
  display:       inline-flex;
  gap:           8px;
  align-items:   center;
  margin:        4px;
}
.vana-seg-btn:hover {
  border-color: var(--vana-gold);
  background:   var(--vana-gold-soft);
  color:        var(--vana-gold);
}
.vana-seg-btn strong {
  color:       var(--vana-orange);
  font-family: monospace;
  font-size:   0.9rem;
}

/* Localização */
.vana-stage-loc > div:first-child {
  background: rgba(255,255,255,0.04) !important;
  border:     1px solid var(--vana-line) !important;
}
.vana-stage-loc strong { color: var(--vana-text) !important; }

/* ============================================================
   8. SECTION TITLE
   ============================================================ */
.vana-section-title {
  font-family:  'Syne', sans-serif;
  font-size:    1.5rem;
  font-weight:  800;
  color:        var(--vana-text);
  margin:       40px 0 20px;
  padding-left: 14px;
  border-left:  4px solid var(--vana-gold);
  line-height:  1.3;
}

/* ============================================================
   9. VOD CARDS GRID
   ============================================================ */
.vana-grid {
  display:               grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap:                   20px;
}
.vana-card {
  background:    var(--vana-bg-card);
  border:        1px solid var(--vana-line);
  border-radius: 12px;
  overflow:      hidden;
  transition:    transform .2s, box-shadow .2s, border-color .2s;
}
.vana-card:hover {
  transform:     translateY(-4px);
  box-shadow:    0 12px 28px rgba(0,0,0,0.35);
  border-color:  rgba(255,217,6,0.3);
}
.vana-card a, .vana-card button.vana-card-trigger {
  all: unset; display: block; cursor: pointer; height: 100%;
}
.vana-card__media {
  position:       relative;
  width:          100%;
  padding-bottom: 56.25%;
  background:     var(--vana-bg-soft);
  overflow:       hidden;
}
.vana-card__media img {
  position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: cover; transition: transform .3s;
}
.vana-card:hover .vana-card__media img { transform: scale(1.06); }
.vana-card__play {
  position:        absolute;
  top:             50%;
  left:            50%;
  transform:       translate(-50%, -50%);
  width:           46px;
  height:          46px;
  background:      rgba(15,23,42,0.75);
  border:          2px solid rgba(255,217,6,0.6);
  border-radius:   50%;
  display:         flex;
  align-items:     center;
  justify-content: center;
  color:           var(--vana-gold);
  transition:      background .2s, transform .2s, border-color .2s;
  pointer-events:  none;
}
.vana-card:hover .vana-card__play {
  background:   var(--vana-gold);
  border-color: var(--vana-gold);
  color:        #0f172a;
  transform:    translate(-50%, -50%) scale(1.12);
}
.vana-card__body {
  padding: 14px 16px 16px;
  display: flex;
  flex-direction: column;
}
.vana-card__name {
  margin:      0;
  font-weight: 700;
  font-family: 'Syne', sans-serif;
  font-size:   1rem;
  line-height: 1.3;
  color:       var(--vana-text);
}

/* ============================================================
   10. SCHEDULE
   ============================================================ */
.vana-schedule-list {
  background:    var(--vana-bg-card);
  border:        1px solid var(--vana-line);
  border-radius: 12px;
  overflow:      hidden;
}
.vana-schedule-item {
  display:     flex;
  align-items: flex-start;
  padding:     14px 20px;
  border-bottom: 1px solid var(--vana-line);
}
.vana-schedule-item-wrap:last-child .vana-schedule-item { border-bottom: none; }
.vana-schedule-item-wrap:nth-child(even) .vana-schedule-item { background: rgba(255,255,255,0.02); }
.vana-schedule-time {
  font-weight:  700;
  color:        var(--vana-orange);
  width: 95px;
  min-width: 0;
  overflow: hidden;
  display:      flex;
  flex-direction: column;
  gap:          2px;
  flex-shrink:  0;
  font-family:  monospace;
  font-size:    1rem;
}
.vana-schedule-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
  flex-grow:   1;
  margin:      0 15px;
  font-weight: 700;
  color:       var(--vana-text);
  font-size:   1rem;
}
.vana-schedule-status {
  font-size:      0.72rem;
  padding:        3px 10px;
  border-radius:  20px;
  text-transform: uppercase;
  font-weight:    800;
  background:     rgba(255,255,255,0.06);
  color:          var(--vana-muted);
  white-space:    nowrap;
}
.status-live { background: rgba(220,38,38,0.15); color: #f87171; }
.status-done { background: rgba(22,163,74,0.15);  color: #4ade80; }

/* ============================================================
   11. GALLERY GRID
   ============================================================ */
.vana-gallery-grid {
  display:               grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap:                   10px;
}
.vana-gallery-item {
  aspect-ratio:  1;
  border-radius: 8px;
  overflow:      hidden;
  cursor:        pointer;
  background:    var(--vana-bg-soft);
  border:        1px solid var(--vana-line);
}
.vana-gallery-item img {
  width: 100%; height: 100%; object-fit: cover; transition: transform .3s;
}
.vana-gallery-item:hover img { transform: scale(1.08); }

/* ============================================================
   12. SANGHA WALL
   ============================================================ */
.vana-sangha-wall {
  display:               grid;
  grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
  gap:                   22px;
  margin-top:            28px;
}
.vana-moment {
  background:    var(--vana-bg-card);
  border:        1px solid var(--vana-line);
  border-radius: 16px;
  overflow:      hidden;
  transition:    transform .3s, border-color .3s, box-shadow .3s;
}
.vana-moment:hover {
  transform:     translateY(-5px);
  border-color:  rgba(255,217,6,0.3);
  box-shadow:    0 14px 32px rgba(0,0,0,0.3);
}
.vana-moment-btn { all: unset; display: block; cursor: pointer; width: 100%; }
.vana-moment-inner { padding: 18px 18px 10px; }
.vana-moment-user  { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.vana-moment-avatar {
  width:           40px;
  height:          40px;
  border-radius:   50%;
  background:      linear-gradient(135deg, var(--vana-pink), var(--vana-blue));
  border:          2px solid rgba(255,217,6,0.3);
  display:         flex;
  align-items:     center;
  justify-content: center;
  color:           #fff;
  font-weight:     900;
  font-family:     'Syne', sans-serif;
  flex-shrink:     0;
  font-size:       1rem;
}
.vana-moment-name  { font-family: 'Syne', sans-serif; font-weight: 900; color: var(--vana-text); font-size: 1rem; }
.vana-moment-text {
  position:           relative;
  background:         rgba(255,255,255,0.04);
  border:             1px solid var(--vana-line);
  border-radius:      14px;
  padding:            14px;
  color:              var(--vana-text-soft);
  line-height:        1.6;
  font-size:          1rem;
  font-style:         italic;
  margin:             0 0 14px;
  display:            -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow:           hidden;
}
.vana-moment-text::after {
  content:     "";
  position:    absolute;
  top:         -7px;
  left:        18px;
  width:       13px;
  height:      13px;
  background:  rgba(255,255,255,0.04);
  border-left: 1px solid var(--vana-line);
  border-top:  1px solid var(--vana-line);
  transform:   rotate(45deg);
}
.vana-moment-media {
  margin:        0 18px 14px;
  border-radius: 10px;
  overflow:      hidden;
  border:        3px solid rgba(255,255,255,0.06);
}
.vana-moment-media img { width: 100%; height: auto; display: block; }
.vana-moment-footer {
  display:         flex;
  justify-content: space-between;
  align-items:     center;
  padding:         0 18px 18px;
  color:           var(--vana-muted);
  font-size:       0.82rem;
  font-weight:     700;
}
.vana-moment-badge {
  display:        inline-flex;
  align-items:    center;
  gap:            5px;
  background:     rgba(255,255,255,0.05);
  border:         1px solid var(--vana-line);
  padding:        3px 10px;
  border-radius:  999px;
  font-weight:    900;
  text-transform: uppercase;
  font-size:      0.68rem;
  color:          var(--vana-muted);
}
.vana-moment-badge .dashicons {
  font-size: 13px; width: 13px; height: 13px; color: var(--vana-gold);
}
.vana-moment--text-only {
  background:    linear-gradient(135deg, var(--vana-bg-card) 0%, rgba(255,217,6,0.04) 100%);
  border-bottom: 3px solid var(--vana-gold);
}
.vana-moment--text-only .vana-moment-text {
  font-size:          1.15rem;
  text-align:         center;
  padding:            18px 14px;
  -webkit-line-clamp: 6;
}

/* ============================================================
   13. SUBMISSION FORM
   ============================================================ */
.vana-form-wrap {
  background:    var(--vana-bg-card);
  padding:       36px;
  border-radius: 16px;
  margin-top:    36px;
  border:        1px solid var(--vana-line);
}
.vana-form-wrap label { color: var(--vana-text-soft); }
.vana-form-wrap input,
.vana-form-wrap textarea {
  width:         100%;
  padding:       11px 16px;
  border-radius: 8px;
  border:        1px solid var(--vana-line-solid);
  background:    rgba(255,255,255,0.05);
  color:         var(--vana-text);
  font-family:   'Questrial', sans-serif;
  transition:    border-color .2s, box-shadow .2s;
  margin-top:    5px;
}
.vana-form-wrap input:focus,
.vana-form-wrap textarea:focus {
  outline:      none;
  border-color: var(--vana-gold);
  background:   rgba(255,217,6,0.04);
  box-shadow:   0 0 0 3px rgba(255,217,6,0.15);
}
.vana-form-wrap input::placeholder,
.vana-form-wrap textarea::placeholder { color: var(--vana-muted); }

/* ============================================================
   14. MODAL / LIGHTBOX
   ============================================================ */
.vana-modal { position: fixed; inset: 0; z-index: 99999; display: none; }
.vana-modal.is-active { display: block; }
.vana-modal__backdrop {
  position: absolute; inset: 0;
  background: rgba(5,10,20,0.92);
  backdrop-filter: blur(8px);
}
.vana-modal__dialog {
  position:       relative;
  max-width:      1000px;
  margin:         3vh auto;
  background:     var(--vana-bg-card);
  border:         1px solid var(--vana-line);
  border-radius:  16px;
  overflow:       hidden;
  display:        flex;
  flex-direction: column;
  max-height:     94vh;
  box-shadow:     0 25px 60px rgba(0,0,0,0.6);
}
.vana-modal__close {
  position:        absolute;
  top:             14px;
  right:           14px;
  z-index:         20;
  background:      rgba(255,255,255,0.1);
  color:           var(--vana-text);
  border:          1px solid var(--vana-line);
  border-radius:   50%;
  width:           38px;
  height:          38px;
  cursor:          pointer;
  font-size:       1.4rem;
  display:         flex;
  align-items:     center;
  justify-content: center;
  transition:      background .2s;
}
.vana-modal__close:hover { background: var(--vana-gold); color: #0f172a; border-color: var(--vana-gold); }
.vana-modal__nav {
  position:        absolute;
  top:             50%;
  transform:       translateY(-50%);
  background:      rgba(255,255,255,0.08);
  color:           var(--vana-text);
  border:          1px solid var(--vana-line);
  width:           42px;
  height:          42px;
  border-radius:   50%;
  font-size:       1.4rem;
  display:         flex;
  align-items:     center;
  justify-content: center;
  cursor:          pointer;
  z-index:         15;
  transition:      background .2s;
}
.vana-modal__nav:hover { background: var(--vana-gold); color: #0f172a; }
.vana-modal__nav--prev { left:  14px; }
.vana-modal__nav--next { right: 14px; }
.vana-modal__media     { background: #000; min-height: 200px; display: flex; align-items: center; justify-content: center; }
.vana-embed { position: relative; width: 100%; height: 0; padding-bottom: 56.25%; background: #000; overflow: hidden; }
.vana-embed iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
.vana-modal__body { padding: 22px 28px; overflow-y: auto; background: var(--vana-bg-card); color: var(--vana-text); }
#vanaModalMessage { padding-top: 16px; font-size: 1.05rem; line-height: 1.7; color: var(--vana-text-soft); }

/* ============================================================
   15. COMMUNITY LINKS
   ============================================================ */
.vana-community-links { display: flex; flex-wrap: wrap; gap: 12px; margin: 20px 0 36px; }
.vana-community-link {
  display:         inline-flex;
  align-items:     center;
  gap:             8px;
  padding:         11px 20px;
  border-radius:   10px;
  text-decoration: none;
  font-weight:     700;
  font-family:     'Syne', sans-serif;
  font-size:       0.9rem;
  border:          1px solid var(--vana-line);
  background:      rgba(255,255,255,0.04);
  color:           var(--vana-text);
  transition:      background .2s, border-color .2s, transform .2s;
}
.vana-community-link:hover { transform: translateY(-2px); }
.vana-community-link--yt { border-color: rgba(255,0,0,0.3); }
.vana-community-link--yt:hover { background: rgba(255,0,0,0.12); border-color: rgba(255,0,0,0.5); }
.vana-community-link--fb { border-color: rgba(24,119,242,0.3); }
.vana-community-link--fb:hover { background: rgba(24,119,242,0.12); border-color: rgba(24,119,242,0.5); }
.vana-community-link--ig { border-color: rgba(243,11,115,0.3); }
.vana-community-link--ig:hover { background: rgba(243,11,115,0.12); border-color: rgba(243,11,115,0.5); }
.vana-community-link--wa { border-color: rgba(37,211,102,0.3); }
.vana-community-link--wa:hover { background: rgba(37,211,102,0.12); border-color: rgba(37,211,102,0.5); }

/* ============================================================
   16. ACESSIBILIDADE
   ============================================================ */
.vana-card a:focus-visible,
.vana-card button:focus-visible,
#vanaCopyFbLink:focus-visible,
.vana-tab:focus-visible,
.vana-hero__nav-btn:focus-visible,
.vana-header__tours-btn:focus-visible,
.vana-moment-btn:focus-visible {
  outline:        3px solid rgba(255,217,6,0.8);
  outline-offset: 3px;
  border-radius:  8px;
}

/* ============================================================
   17. STAGE PLACEHOLDER
   ============================================================ */
.vana-stage-placeholder {
  position:        absolute;
  inset:           0;
  background:      var(--vana-bg-card);
  display:         flex;
  flex-direction:  column;
  align-items:     center;
  justify-content: center;
  padding:         40px;
  color:           var(--vana-text-soft);
}
.vana-stage-cta {
  font-weight:     900;
  text-decoration: none;
  color:           #0f172a;
  font-size:       1.1rem;
  background:      var(--vana-gold);
  padding:         12px 24px;
  border-radius:   8px;
  transition:      opacity .2s, transform .2s;
}
.vana-stage-cta:hover { opacity: .88; transform: translateY(-2px); }

/* ============================================================
   18. RESPONSIVE
   ============================================================ */
@media (max-width: 600px) {
  .vana-header__title       { max-width: 120px; font-size: 0.8rem; }
  .vana-header__tours-label { display: none; }
  .vana-hero                { padding: 36px 16px 44px; }
  .vana-hero__nav-btn       { font-size: 0.78rem; padding: 8px 10px; }
  .vana-form-wrap           { padding: 24px 18px; }
  .vana-modal__body         { padding: 16px 18px; }
}

/* ============================================================
   19. TOUR DRAWER (inalterado — já correto)
   ============================================================ */
.vana-drawer__overlay {
  position: fixed; inset: 0;
  background: rgba(5,10,20,0.6);
  z-index: 1099; display: none; opacity: 0;
  transition: opacity 0.28s ease;
  backdrop-filter: blur(4px);
}
.vana-drawer__overlay.is-open { display: block; opacity: 1; }

.vana-drawer {
  position:       fixed;
  top: 0; left: 0;
  width:          320px;
  max-width:      90vw;
  height:         100dvh;
  background:     #ffffff;
  color:          #0f172a;
  z-index:        1100;
  display:        none;
  flex-direction: column;
  transform:      translateX(-100%);
  transition:     transform 0.28s cubic-bezier(0.4,0,0.2,1);
  border-right:   4px solid var(--vana-gold);
  box-shadow:     4px 0 40px rgba(0,0,0,0.5);
  overflow:       hidden;
}
.vana-drawer.is-open { display: flex; transform: translateX(0); }

.vana-drawer__header {
  display:       flex;
  align-items:   center;
  gap:           8px;
  padding:       15px 16px 13px;
  background:    linear-gradient(135deg, var(--vana-gold) 0%, var(--vana-orange) 100%);
  border-bottom: 2px solid var(--vana-orange);
  flex-shrink:   0;
}
.vana-drawer__header-title {
  flex: 1; font-size: 13px; font-weight: 700;
  letter-spacing: 0.12em; text-transform: uppercase;
  color: #0f172a; font-family: 'Syne', sans-serif;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.vana-drawer__back,
.vana-drawer__close {
  background: none; border: none; cursor: pointer; color: #0f172a;
  padding: 5px; border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  transition: background .15s; flex-shrink: 0; opacity: 0.7;
}
.vana-drawer__back:hover,
.vana-drawer__close:hover { opacity: 1; background: rgba(255,255,255,0.3); }

.vana-drawer__body {
  flex: 1; overflow-y: auto; overscroll-behavior: contain;
  padding: 8px 0; background: #fff;
}
.vana-drawer__body::-webkit-scrollbar       { width: 4px; }
.vana-drawer__body::-webkit-scrollbar-track { background: transparent; }
.vana-drawer__body::-webkit-scrollbar-thumb { background: rgba(243,11,115,0.2); border-radius: 4px; }

.vana-drawer__loading { display: flex; justify-content: center; padding: 40px 0; }
.vana-drawer__spinner {
  width: 24px; height: 24px;
  border: 2px solid rgba(243,11,115,0.15);
  border-top-color: var(--vana-pink);
  border-radius: 50%;
  animation: vana-spin 0.7s linear infinite;
}
@keyframes vana-spin { to { transform: rotate(360deg); } }

.vana-drawer__tour-list { list-style: none; margin: 0; padding: 0; }
.vana-drawer__tour-list::before {
  content: ''; display: block; height: 3px;
  background: linear-gradient(90deg, #FFD906, #F30B73, #170DF2);
}
.vana-drawer__tour-item { border-bottom: 1px solid rgba(255,217,6,0.15); }
.vana-drawer__tour-btn {
  width: 100%; background: none; border: none; cursor: pointer;
  display: flex; align-items: center; gap: 8px; padding: 13px 16px;
  text-align: left; transition: background .15s, padding-left .15s;
  color: #0f172a; position: relative;
}
.vana-drawer__tour-btn:hover { background: rgba(255,217,6,0.10); padding-left: 22px; }
.vana-drawer__tour-btn:hover::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: linear-gradient(180deg, #FFD906, #F35C0B);
  border-radius: 0 2px 2px 0;
}
.vana-drawer__tour-item.is-current-tour .vana-drawer__tour-btn { background: rgba(243,11,115,0.06); padding-left: 22px; }
.vana-drawer__tour-item.is-current-tour .vana-drawer__tour-btn::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: linear-gradient(180deg, #F30B73, #170DF2);
  border-radius: 0 2px 2px 0;
}
.vana-drawer__tour-name { flex: 1; font-size: 14px; line-height: 1.4; font-weight: 600; color: #0f172a; font-family: 'Questrial', sans-serif; }
.vana-drawer__tour-item.is-current-tour .vana-drawer__tour-name { color: #170DF2; font-weight: 700; }
.vana-drawer__tour-meta {
  font-size: 11px; color: #170DF2; background: rgba(255,217,6,0.20);
  border: 1px solid rgba(255,217,6,0.5); padding: 2px 8px;
  border-radius: 10px; white-space: nowrap; flex-shrink: 0; font-weight: 600;
}
.vana-drawer__tour-item.is-current-tour .vana-drawer__tour-meta {
  background: rgba(243,11,115,0.10); border-color: rgba(243,11,115,0.35); color: #F30B73;
}
.vana-drawer__chevron { flex-shrink: 0; color: rgba(255,217,6,0.8); }

.vana-drawer__visit-list { list-style: none; margin: 0; padding: 0; }
.vana-drawer__visit-item { border-bottom: 1px solid rgba(255,217,6,0.12); }
.vana-drawer__visit-link {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; text-decoration: none;
  color: #334155; font-size: 13px; transition: background .15s, color .15s;
}
.vana-drawer__visit-link:hover { background: rgba(255,217,6,0.08); color: #0f172a; }
.vana-drawer__visit-item.is-current-visit .vana-drawer__visit-link { color: #170DF2; background: rgba(23,13,242,0.05); font-weight: 700; }
.vana-drawer__visit-dot { width: 7px; height: 7px; border-radius: 50%; background: #FFD906; flex-shrink: 0; }
.vana-drawer__visit-dot--empty { background: transparent; border: 1px solid rgba(15,23,42,0.15); }
.vana-drawer__visit-name { flex: 1; line-height: 1.4; color: inherit; }
.vana-drawer__visit-date { font-size: 11px; color: #94a3b8; white-space: nowrap; flex-shrink: 0; }
.vana-drawer__error { padding: 24px 16px; font-size: 13px; color: #94a3b8; text-align: center; }

@media (max-width: 480px) {
  .vana-drawer { width: 100vw; max-width: 100vw; }
}

/* Live pulse */
@keyframes vana-pulse {
  0%, 100% { opacity: 1;   transform: scale(1);    }
  50%       { opacity: .4; transform: scale(1.4); }
}

/* ============================================================
   20. MODAL GALLERY — múltiplas fotos (carrossel)
   ============================================================ */
.vana-modal-gallery {
  position:   relative;
  width:       100%;
  background:  #000;
  overflow:    hidden;
}
.vana-modal-gallery__item {
  display:    none;
  width:      100%;
  max-height: 70vh;
  align-items:     center;
  justify-content: center;
  background: #000;
}
.vana-modal-gallery__item.is-active {
  display: flex;
}
.vana-modal-gallery__item img {
  max-width:  100%;
  max-height: 70vh;
  width:      auto;
  height:     auto;
  object-fit: contain;
  display:    block;
}
.vana-modal-gallery__dots {
  display:         flex;
  justify-content: center;
  align-items:     center;
  gap:             8px;
  padding:         12px 0;
  background:      #000;
}
.vana-modal-gallery__dot {
  min-width:     24px;
  height:        24px;
  border-radius: 12px;
  border:        2px solid rgba(255,255,255,0.3);
  background:    transparent;
  cursor:        pointer;
  padding:       0 6px;
  font-size:     10px;
  color:         rgba(255,255,255,0.5);
  transition:    background .2s, border-color .2s, transform .2s, color .2s;
  line-height:   1;
}
.vana-modal-gallery__dot.is-active {
  background:    var(--vana-gold);
  border-color:  var(--vana-gold);
  color:         #0f172a;
  transform:     scale(1.15);
}
.vana-modal-gallery__dot:hover {
  border-color: var(--vana-gold);
}

/* Swipe visual feedback mobile */
@media (max-width: 600px) {
  .vana-modal-gallery__item img {
    max-height: 55vh;
  }
}
/* ============================================================
   21. GALERIA DE GURUDEVA — variações de estilo
   ============================================================ */

/* Avatar dourado para Gurudeva Gallery */
.vana-moment-avatar--gold {
  background: linear-gradient(135deg, var(--vana-gold), var(--vana-orange)) !important;
  color: #0f172a !important;
  border-color: rgba(255,217,6,.5) !important;
}

/* Badge dourado */
.vana-moment-badge--gold {
  border-color: rgba(255,217,6,.4) !important;
  color: var(--vana-gold) !important;
}
.vana-moment-badge--gold .dashicons {
  color: var(--vana-gold) !important;
}

/* Borda especial nos cards de Gurudeva */
.vana-moment--gurudeva {
  border-color: rgba(255,217,6,.25) !important;
  background: linear-gradient(
    160deg,
    var(--vana-bg-card) 0%,
    rgba(255,217,6,.04) 100%
  ) !important;
}
.vana-moment--gurudeva:hover {
  border-color: rgba(255,217,6,.55) !important;
  box-shadow: 0 14px 32px rgba(255,217,6,.12) !important;
}

/* Mídia sem padding lateral (ocupa full width no card) */
.vana-moment-media--gallery {
  margin: 0 0 0 0 !important;
  border-radius: 0 !important;
  border: none !important;
  overflow: hidden;
}
.vana-moment-media--gallery img {
  width: 100%;
  height: 220px;
  object-fit: cover;
  display: block;
  transition: transform .4s;
}
.vana-moment--gurudeva:hover .vana-moment-media--gallery img {
  transform: scale(1.04);
}

/* Badge de contagem de mídias extras */
.vana-media-count {
  position: absolute;
  bottom: 8px;
  right: 8px;
  background: rgba(0,0,0,.7);
  color: #fff;
  font-size: .75rem;
  font-weight: 900;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.2);
  pointer-events: none;
}

/* Posição relativa para o badge de contagem */
.vana-moment-media--gallery {
  position: relative !important;
}

/* ── Accordion VOD (schedule → N vods) ───────────────── */
.vana-vod-accordion {
  margin:        0 0 4px 0;
  border:        1px solid var(--vana-line);
  border-top:    none;
  border-radius: 0 0 10px 10px;
  background:    var(--vana-bg-soft);
  overflow:      hidden;
  animation:     vanaAccOpen .2s ease;
}

@keyframes vanaAccOpen {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0);    }
}

.vana-vod-accordion__list {
  list-style: none;
  margin:     0;
  padding:    6px 0;
}

.vana-vod-accordion__item + .vana-vod-accordion__item {
  border-top: 1px solid var(--vana-line);
}

.vana-vod-accordion__btn {
  display:         flex;
  align-items:     center;
  gap:             10px;
  width:           100%;
  padding:         12px 18px;
  background:      transparent;
  border:          none;
  cursor:          pointer;
  text-align:      left;
  color:           var(--vana-text);
  font-size:       .95rem;
  font-weight:     700;
  transition:      background .15s;
}

.vana-vod-accordion__btn:hover,
.vana-vod-accordion__btn:focus-visible {
  background: rgba(255, 255, 255, .06);
  outline:    none;
}

.vana-vod-accordion__icon {
  color:       var(--vana-gold, #f59e0b);
  font-size:   .8rem;
  flex-shrink: 0;
}

.vana-vod-accordion__label {
  flex-grow: 1;
}

.vana-vod-accordion__seg {
  font-family:    monospace;
  font-size:      .78rem;
  color:          var(--vana-muted);
  flex-shrink:    0;
  background:     var(--vana-bg);
  padding:        2px 7px;
  border-radius:  4px;
  border:         1px solid var(--vana-line);
}

/* Item ativo na agenda */
.vana-schedule-item.is-active {
  border-left: 4px solid var(--vana-gold, #f59e0b) !important;
  background:  rgba(245, 158, 11, .08) !important;
  opacity:     1 !important;
}

/* Arredondamento do item quando accordion aberto */
.vana-schedule-item[aria-expanded="true"] {
  border-radius: 10px 10px 0 0;
}


/* ============================================================
   TITLE POPOVER
   ============================================================ */
.vana-schedule-item-wrap {
  position: relative;
}
.vana-title-popover {
  display:        none;
  position:       fixed;
  z-index:        9999;
  background:     var(--vana-card, #1e1b14);
  border:         1px solid var(--vana-gold, #c9a84c);
  border-radius:  8px;
  padding:        8px 12px;
  max-width:      260px;
  min-width:      160px;
  box-shadow:     0 4px 16px rgba(0,0,0,0.5);
  font-size:      0.82rem;
  color:          var(--vana-text, #f5f0e8);
  line-height:    1.4;
  pointer-events: none;
  transform:      translateY(-50%);
}
.vana-title-popover.is-visible {
  display: block;
}
.vana-title-popover strong {
  display:       block;
  font-size:     0.88rem;
  color:         var(--vana-gold, #c9a84c);
  margin-bottom: 3px;
}

.vana-section--hari-katha {
  margin-top: 40px;
}

.vana-hk__intro {
  color: var(--vana-muted);
  margin-bottom: 16px;
}

.vana-hk__list,
.vana-hk__passages {
  display: grid;
  gap: 14px;
}

.vana-hk__item,
.vana-hk__passage {
  background: var(--vana-bg-card);
  border: 1px solid var(--vana-line);
  border-radius: 12px;
  padding: 16px 18px;
}

.vana-hk__item button {
  all: unset;
  display: block;
  width: 100%;
  cursor: pointer;
}

.vana-hk__meta {
  font-size: 0.82rem;
  color: var(--vana-muted);
  margin-bottom: 6px;
}

.vana-hk__title {
  font-family: 'Syne', sans-serif;
  font-weight: 700;
  color: var(--vana-text);
  margin: 0 0 8px;
}

.vana-hk__excerpt {
  color: var(--vana-text-soft);
  line-height: 1.6;
}

.vana-hk__back {
  margin-bottom: 12px;
  background: transparent;
  border: 1px solid var(--vana-line);
  border-radius: 999px;
  padding: 8px 14px;
  cursor: pointer;
  font-weight: 700;
}

/* ============================================================
   HARI-KATHĀ
   Tokens: usa exclusivamente o design system vanamadhuryam
   ============================================================ */

/* ── Intro / estados ── */
.vana-hk__intro {
  color:      var(--vana-muted);
  font-size:  0.9rem;
  text-align: center;
  padding:    24px 0;
}
.vana-hk__error {
  color:      var(--vana-orange);
  font-size:  0.9rem;
  text-align: center;
  padding:    24px 0;
}

/* ── Lista de kathās ── */
.vana-hk__list {
  display:        flex;
  flex-direction: column;
  gap:            8px;
  margin-top:     4px;
}

/* ── Card de kathā ── */
.vana-hk-card {
  width:          100%;
  display:        grid;
  grid-template:
    "period count" auto
    "title  title" auto
    "excp   excp " auto
    / 1fr auto;
  gap:            3px 12px;
  background:     var(--vana-bg-soft);
  border:         1px solid var(--vana-line);
  border-radius:  10px;
  padding:        14px 16px;
  text-align:     left;
  cursor:         pointer;
  color:          var(--vana-text);
  font-family:    'Questrial', sans-serif;
  font-size:      1rem;
  transition:     border-color .2s, background .2s,
                  transform .18s, box-shadow .2s;
  -webkit-appearance: none;
  appearance:     none;
}
.vana-hk-card:hover {
  border-color: var(--vana-gold);
  background:   var(--vana-bg-elevated);
  transform:    translateY(-1px);
  box-shadow:   0 4px 16px rgba(255,217,6,.12);
}
.vana-hk-card:focus-visible {
  outline:        2px solid var(--vana-gold);
  outline-offset: 2px;
}
.vana-hk-card[aria-pressed="true"] {
  border-color: var(--vana-gold);
  background:   rgba(255,217,6,.06);
  box-shadow:   0 2px 12px rgba(255,217,6,.15);
}

.vana-hk-card__period {
  grid-area:      period;
  font-size:      0.7rem;
  font-weight:    700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color:          var(--vana-gold);
  align-self:     center;
  font-family:    'Syne', sans-serif;
}
.vana-hk-card__count {
  grid-area:   count;
  font-size:   0.78rem;
  color:       var(--vana-muted);
  align-self:  center;
  white-space: nowrap;
}
.vana-hk-card__title {
  grid-area:   title;
  font-family: 'Syne', sans-serif;
  font-weight: 700;
  font-size:   clamp(.95rem, 2.2vw, 1.05rem);
  color:       var(--vana-text);
  line-height: 1.35;
}
.vana-hk-card__excerpt {
  grid-area:          excp;
  font-size:          0.85rem;
  color:              var(--vana-muted);
  line-height:        1.55;
  display:            -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow:           hidden;
}

/* ── Painel de passages ── */
.vana-hk__passages { padding-top: 4px; }

/* ── Botão voltar ── */
.vana-hk-back {
  display:        inline-flex;
  align-items:    center;
  gap:            6px;
  background:     var(--vana-bg-soft);
  border:         1px solid var(--vana-line);
  border-radius:  20px;
  padding:        5px 14px;
  font-size:      0.78rem;
  font-weight:    700;
  font-family:    'Syne', sans-serif;
  color:          var(--vana-text-soft);
  cursor:         pointer;
  margin-bottom:  16px;
  transition:     border-color .2s, background .2s;
  -webkit-appearance: none;
  appearance:     none;
}
.vana-hk-back:hover {
  border-color: var(--vana-gold);
  background:   rgba(255,217,6,.06);
  color:        var(--vana-text);
}

/* ── Título da kathā aberta ── */
.vana-hk-katha-title {
  font-family:    'Syne', sans-serif;
  font-weight:    700;
  font-size:      clamp(1rem, 2.8vw, 1.25rem);
  color:          var(--vana-text);
  margin-bottom:  20px;
  padding-bottom: 12px;
  border-bottom:  2px solid rgba(255,217,6,.3);
}

/* ── Passage ── */
.vana-hk-passage {
  --kind-color:  var(--vana-gold);
  background:    var(--vana-bg-soft);
  border:        1px solid var(--vana-line);
  border-left:   3px solid var(--kind-color);
  border-radius: 0 10px 10px 0;
  padding:       16px 18px;
  margin-bottom: 10px;
  color:         var(--vana-text);
}

/* Cor por kind — tokens do sistema */
.vana-hk-passage[data-kind="narrative"],
.vana-hk-passage[data-kind="story"]            { --kind-color: var(--vana-orange); }
.vana-hk-passage[data-kind="instruction"],
.vana-hk-passage[data-kind="teaching"]         { --kind-color: var(--vana-blue);   }
.vana-hk-passage[data-kind="verse_commentary"] { --kind-color: #7c3aed;            }
.vana-hk-passage[data-kind="dialogue"]         { --kind-color: var(--vana-pink);   }
.vana-hk-passage[data-kind="prayer"]           { --kind-color: var(--vana-gold);   }
.vana-hk-passage[data-kind="gaura-lila"]       { --kind-color: #059669;            }
.vana-hk-passage[data-kind="anecdote"]         { --kind-color: var(--vana-orange); }

/* ── Header do passage ── */
.vana-hk-passage__header {
  display:       flex;
  align-items:   center;
  gap:           8px;
  flex-wrap:     wrap;
  margin-bottom: 10px;
}
.vana-hk-passage__index {
  font-family: monospace;
  font-size:   0.72rem;
  color:       var(--vana-muted);
}
.vana-hk-passage__kind {
  font-size:      0.7rem;
  font-weight:    700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color:          var(--kind-color);
  font-family:    'Syne', sans-serif;
}
.vana-hk-passage__ts {
  margin-left:  auto;
  font-size:    0.72rem;
  font-family:  monospace;
  color:        var(--vana-text-soft);
  background:   var(--vana-bg-elevated);
  border:       1px solid var(--vana-line);
  border-radius:20px;
  padding:      2px 10px;
  cursor:       pointer;
  user-select:  none;
  transition:   border-color .2s, background .2s, color .2s;
  white-space:  nowrap;
}
.vana-hk-passage__ts::before { content: '▶ '; font-size: .65rem; }
.vana-hk-passage__ts:hover {
  border-color: var(--vana-gold);
  background:   rgba(255,217,6,.08);
  color:        var(--vana-text);
}
.vana-hk-passage__badge {
  font-size: 0.8rem;
  opacity:   .6;
}

/* ── Corpo ── */
.vana-hk-passage__hook {
  font-size:     0.9rem;
  font-weight:   600;
  color:         var(--vana-text);
  line-height:   1.5;
  margin-bottom: 8px;
}
.vana-hk-passage__quote {
  border-left:   2px solid var(--kind-color);
  margin:        10px 0;
  padding:       8px 14px;
  font-style:    italic;
  color:         var(--vana-text-soft);
  font-size:     0.9rem;
  line-height:   1.65;
  background:    var(--vana-bg-elevated);
  border-radius: 0 6px 6px 0;
}
.vana-hk-passage__content {
  font-size:   0.88rem;
  color:       var(--vana-text-soft);
  line-height: 1.75;
  margin-top:  8px;
}

/* ── Footer ── */
.vana-hk-passage__footer {
  margin-top:  10px;
  padding-top: 8px;
  border-top:  1px solid var(--vana-line);
}
.vana-hk-passage__link {
  font-size:       0.75rem;
  color:           var(--vana-muted);
  text-decoration: none;
  transition:      color .2s;
}
.vana-hk-passage__link:hover { color: var(--vana-gold); }

/* ── Load more ── */
#vana-hk-pagination {
  text-align: center;
  margin-top: 20px;
}
.vana-hk-load-more {
  background:    var(--vana-bg-soft);
  border:        1px solid var(--vana-line);
  border-radius: 20px;
  color:         var(--vana-text-soft);
  padding:       8px 24px;
  font-size:     0.82rem;
  font-weight:   700;
  font-family:   'Syne', sans-serif;
  cursor:        pointer;
  transition:    border-color .2s, background .2s;
  -webkit-appearance: none;
  appearance:    none;
}
.vana-hk-load-more:hover {
  border-color: var(--vana-gold);
  background:   rgba(255,217,6,.06);
}

/* ── Responsivo ── */
@media (max-width: 480px) {
  .vana-hk-card    { padding: 12px 14px; }
  .vana-hk-passage { padding: 12px 14px; }
}


</style>

