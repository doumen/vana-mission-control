<?php
/**
 * Asset: Vana Visit — Estilos
 * Arquivo: templates/visit/assets/visit-style.php
 *
 * Incluído pelo orquestrador single-vana_visit.php
 * ANTES do get_header() não — dentro do <main>, via ob ou direto.
 *
 * Seções:
 *   1. Design Tokens (CSS Custom Properties)
 *   2. Layout Base
 *   3. Hero & Tour Nav
 *   4. Tabs de Dia
 *   5. Stage (Palco Principal)
 *   6. Segmentos / Capítulos
 *   7. Section Title
 *   8. Cards Grid (VOD)
 *   9. Sangha Wall (Momentos)
 *  10. Schedule (Programação)
 *  11. Gallery Grid
 *  12. Submission Form
 *  13. Modal / Lightbox
 *  14. Acessibilidade (focus-visible)
 */
defined('ABSPATH') || exit;
?>
<style id="vana-visit-styles">

/* ============================================================
   1. DESIGN TOKENS
   ============================================================ */
:root {
  --vana-bg:          #ffffff;
  --vana-bg-soft:     #f8fafc;
  --vana-line:        #e2e8f0;
  --vana-text:        #0f172a;
  --vana-muted:       #64748b;
  --vana-gold:        #FFD906;
  --vana-blue:        #170DF2;
  --vana-pink:        #F30B73;
  --vana-orange:      #F35C0B;
  --vana-pinksoft:    #F288B8;
  --vana-hero-gradient:
    radial-gradient(circle at 28.33% 11.66%, rgba(243,11,115,.10)  0%, 17.5%, rgba(243,11,115,0)   35%),
    radial-gradient(circle at 17.50% 87.50%, rgba(255,217,6, .15)  0%, 17.5%, rgba(255,217,6, 0)   35%),
    radial-gradient(circle at 47.50%  6.66%, rgba(243,92,11, .10)  0%, 17.5%, rgba(243,92,11, 0)   35%),
    radial-gradient(circle at 74.58% 75.00%, rgba(23,13,242, .08)  0%, 17.5%, rgba(23,13,242, 0)   35%),
    radial-gradient(circle at 48.90% 49.52%, #FFFFFF 0%, 100%, rgba(255,255,255,0) 100%);
}

/* ============================================================
   2. LAYOUT BASE
   ============================================================ */
.vana-wrap {
  max-width:   1200px;
  margin:      0 auto;
  padding:     0 16px;
  font-family: 'Questrial', sans-serif;
  color:       var(--vana-text);
}

/* ============================================================
   3. HERO & TOUR NAV
   ============================================================ */
.vana-hero {
  background-color:  var(--vana-bg);
  background-image:  var(--vana-hero-gradient);
  color:             var(--vana-text);
  border-bottom:     4px solid var(--vana-gold);
  padding:           30px 20px 60px;
  text-align:        center;
}

.vana-tour-nav {
  display:         flex;
  justify-content: space-between;
  align-items:     center;
  margin-bottom:   20px;
}

.vana-nav-btn {
  font-weight:     900;
  color:           var(--vana-text);
  text-decoration: none;
  font-size:       0.95rem;
  background:      rgba(255,255,255,.6);
  padding:         8px 16px;
  border-radius:   8px;
  border:          1px solid var(--vana-line);
  transition:      background .2s, border-color .2s;
}
.vana-nav-btn:hover {
  background:    var(--vana-gold);
  border-color:  var(--vana-gold);
}

.vana-badge {
  background:    rgba(255,217,6,.2);
  border:        1px solid var(--vana-gold);
  color:         #8a6b00;
  padding:       4px 12px;
  border-radius: 20px;
  font-weight:   bold;
  font-size:     0.8rem;
  text-transform: uppercase;
}

.vana-hero h1 {
  font-family: 'Syne', sans-serif;
  font-size:   clamp(2rem, 5vw, 3.2rem);
  color:       var(--vana-text);
  margin:      15px 0 0;
  font-weight: 800;
}

/* ============================================================
   4. TABS DE DIA
   ============================================================ */
.vana-tabs {
  display:     flex;
  gap:         10px;
  flex-wrap:   wrap;
  margin:      20px 0 30px;
}

.vana-tab {
  padding:         10px 18px;
  border-radius:   999px;
  border:          1px solid var(--vana-line);
  font-weight:     800;
  text-decoration: none;
  transition:      background .2s, border-color .2s, box-shadow .2s;
  font-family:     'Syne', sans-serif;
  background:      #fff;
  color:           var(--vana-text);
}
.vana-tab.active {
  background:   var(--vana-text);
  color:        #fff;
  border-color: var(--vana-text);
  box-shadow:   0 4px 10px rgba(0,0,0,.1);
}
.vana-tab:not(.active):hover {
  background:   var(--vana-bg-soft);
  border-color: var(--vana-gold);
}

/* ============================================================
   5. STAGE (PALCO PRINCIPAL)
   ============================================================ */
.vana-stage {
  background:    #fff;
  border:        1px solid var(--vana-line);
  border-radius: 16px;
  overflow:      hidden;
  box-shadow:    0 10px 30px rgba(0,0,0,.05);
  margin-bottom: 40px;
}

.vana-stage-video {
  position:        relative;
  width:           100%;
  padding-bottom:  56.25%;
  height:          0;
  background:      #0b1220;
}
.vana-stage-video iframe {
  position: absolute;
  inset:    0;
  width:    100%;
  height:   100%;
  border:   0;
}

.vana-stage-info {
  background:  #fff;
  color:       var(--vana-text);
  border-top:  1px solid var(--vana-line);
  padding:     25px;
}

.vana-stage-info-badge {
  background:     var(--vana-gold);
  color:          #111;
  padding:        4px 10px;
  border-radius:  6px;
  font-weight:    bold;
  font-size:      0.8rem;
  text-transform: uppercase;
}

/* ============================================================
   6. SEGMENTOS / CAPÍTULOS
   ============================================================ */
.vana-stage-segments {
  padding:     15px 25px 25px;
  border-top:  1px solid var(--vana-line);
  background:  #fafcfd;
}

.vana-seg-btn {
  padding:        8px 14px;
  border-radius:  999px;
  border:         1px solid var(--vana-line);
  background:     #fff;
  font-weight:    700;
  color:          var(--vana-text);
  font-size:      0.9rem;
  cursor:         pointer;
  transition:     border-color .2s, background .2s;
  display:        inline-flex;
  gap:            8px;
  align-items:    center;
  margin:         4px;
}
.vana-seg-btn:hover {
  border-color: var(--vana-gold);
  background:   #fffdf0;
}
.vana-seg-btn strong {
  color:       var(--vana-orange);
  font-family: monospace;
  font-size:   0.95rem;
}

/* ============================================================
   7. SECTION TITLE
   ============================================================ */
.vana-section-title {
  font-family:  'Syne', sans-serif;
  font-size:    1.6rem;
  color:        var(--vana-text);
  margin:       40px 0 20px;
  border-left:  4px solid var(--vana-gold);
  padding-left: 12px;
}

/* ============================================================
   8. CARDS GRID (VOD)
   ============================================================ */
.vana-grid {
  display:               grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap:                   20px;
}

.vana-card {
  background:    #fff;
  border:        1px solid var(--vana-line);
  border-radius: 12px;
  overflow:      hidden;
  transition:    transform .2s, box-shadow .2s, border-color .2s;
  box-shadow:    0 4px 6px rgba(0,0,0,.02);
}
.vana-card:hover {
  transform:    translateY(-3px);
  box-shadow:   0 10px 15px rgba(0,0,0,.08);
  border-color: #cbd5e1;
}

.vana-card a,
.vana-card button.vana-card-trigger {
  all:             unset;
  display:         block;
  cursor:          pointer;
  height:          100%;
  text-decoration: none;
}

.vana-card__media {
  position:       relative;
  width:          100%;
  padding-bottom: 56.25%;
  background:     var(--vana-bg-soft);
  overflow:       hidden;
}
.vana-card__media img {
  position:   absolute;
  inset:      0;
  width:      100%;
  height:     100%;
  object-fit: cover;
  transition: transform .3s;
}
.vana-card:hover .vana-card__media img { transform: scale(1.05); }

.vana-card__play {
  position:      absolute;
  top:           50%;
  left:          50%;
  transform:     translate(-50%, -50%);
  width:         44px;
  height:        44px;
  background:    rgba(255,255,255,.9);
  border-radius: 50%;
  display:       flex;
  align-items:   center;
  justify-content: center;
  color:         var(--vana-text);
  box-shadow:    0 4px 10px rgba(0,0,0,.1);
  transition:    background .2s, transform .2s;
  pointer-events: none;
}
.vana-card:hover .vana-card__play {
  background: var(--vana-gold);
  color:      #000;
  transform:  translate(-50%, -50%) scale(1.1);
}

.vana-card__body {
  padding:        15px;
  display:        flex;
  flex-direction: column;
  height:         100%;
}
.vana-card__name {
  margin:      0;
  font-weight: 700;
  font-family: 'Syne', sans-serif;
  font-size:   1.05rem;
  line-height: 1.3;
}

/* ============================================================
   9. SANGHA WALL (MOMENTOS)
   ============================================================ */
.vana-sangha-wall {
  display:               grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap:                   25px;
  margin-top:            30px;
}

.vana-moment {
  background:    #fff;
  border:        1px solid var(--vana-line);
  border-radius: 18px;
  overflow:      hidden;
  box-shadow:    0 8px 20px rgba(0,0,0,.04);
  transition:    transform .3s cubic-bezier(.4,0,.2,1),
                 border-color .3s,
                 box-shadow .3s;
}
.vana-moment:hover {
  transform:    translateY(-6px);
  border-color: var(--vana-gold);
  box-shadow:   0 14px 30px rgba(255,217,6,.12);
}

.vana-moment-btn {
  all:     unset;
  display: block;
  cursor:  pointer;
  width:   100%;
}

.vana-moment-inner      { padding: 20px 20px 10px; flex-grow: 1; }

.vana-moment-user {
  display:       flex;
  align-items:   center;
  gap:           14px;
  margin-bottom: 15px;
}

.vana-moment-avatar {
  width:           42px;
  height:          42px;
  border-radius:   50%;
  background:      var(--vana-hero-gradient);
  border:          1px solid var(--vana-line);
  display:         flex;
  align-items:     center;
  justify-content: center;
  color:           var(--vana-text);
  font-weight:     900;
  font-family:     'Syne', sans-serif;
  flex-shrink:     0;
}

.vana-moment-name {
  font-family: 'Syne', sans-serif;
  font-weight: 900;
  color:       var(--vana-text);
  font-size:   1.1rem;
}

.vana-moment-text {
  position:          relative;
  background:        var(--vana-bg-soft);
  border:            1px solid var(--vana-line);
  border-radius:     16px;
  padding:           16px;
  color:             #334155;
  line-height:       1.6;
  font-size:         1.05rem;
  font-style:        italic;
  margin:            0 0 15px;
  min-height:        40px;
  display:           -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow:          hidden;
}
/* balão de fala */
.vana-moment-text::after {
  content:          "";
  position:         absolute;
  top:              -8px;
  left:             20px;
  width:            14px;
  height:           14px;
  background:       var(--vana-bg-soft);
  border-left:      1px solid var(--vana-line);
  border-top:       1px solid var(--vana-line);
  transform:        rotate(45deg);
}

.vana-moment-media {
  margin:        0 22px 15px;
  border-radius: 12px;
  overflow:      hidden;
  background:    #fff;
  border:        6px solid #fff;
  box-shadow:    0 6px 15px rgba(0,0,0,.12);
}
.vana-moment-media img { width: 100%; height: auto; display: block; }

.vana-moment-footer {
  display:         flex;
  justify-content: space-between;
  align-items:     center;
  padding:         0 22px 20px;
  color:           var(--vana-muted);
  font-size:       0.85rem;
  font-weight:     700;
}

.vana-moment-badge {
  display:        inline-flex;
  align-items:    center;
  gap:            6px;
  background:     #f8fafc;
  border:         1px solid var(--vana-line);
  padding:        4px 12px;
  border-radius:  999px;
  font-weight:    900;
  text-transform: uppercase;
  font-size:      0.7rem;
  color:          var(--vana-muted);
}
.vana-moment-badge .dashicons {
  font-size: 14px;
  width:     14px;
  height:    14px;
  color:     var(--vana-gold);
}

/* Variante texto puro */
.vana-moment--text-only {
  background:    linear-gradient(135deg, #ffffff 0%, #fefcf0 100%);
  border-bottom: 3px solid var(--vana-gold);
}
.vana-moment--text-only .vana-moment-text {
  font-size:          1.25rem;
  line-height:        1.6;
  text-align:         center;
  padding:            20px 10px;
  font-weight:        500;
  -webkit-line-clamp: 6;
}
.vana-moment--text-only .vana-moment-inner::before {
  content:       "\f122";
  font-family:   dashicons;
  position:      absolute;
  top:           15px;
  right:         20px;
  font-size:     3rem;
  color:         var(--vana-gold);
  opacity:       .15;
  pointer-events: none;
}

/* ============================================================
   10. SCHEDULE (PROGRAMAÇÃO)
   ============================================================ */
.vana-schedule-list {
  background:    #fff;
  border:        1px solid var(--vana-line);
  border-radius: 12px;
  overflow:      hidden;
}

.vana-schedule-item {
  display:     flex;
  align-items: flex-start;
  padding:     16px 20px;
  border-bottom: 1px solid var(--vana-line);
}
.vana-schedule-item:last-child   { border-bottom: none; }
.vana-schedule-item:nth-child(even) { background: #fafcfd; }

.vana-schedule-time {
  font-weight: 700;
  color:       var(--vana-orange);
  width:       70px;
  flex-shrink: 0;
  font-family: monospace;
  font-size:   1.1rem;
}

.vana-schedule-title {
  flex-grow:   1;
  margin:      0 15px;
  font-weight: 700;
  color:       var(--vana-text);
  font-size:   1.05rem;
}

.vana-schedule-status {
  font-size:      0.75rem;
  padding:        4px 10px;
  border-radius:  20px;
  text-transform: uppercase;
  font-weight:    800;
  background:     var(--vana-bg-soft);
  color:          var(--vana-muted);
  white-space:    nowrap;
}
.status-live { background: #fee2e2; color: #dc2626; }
.status-done { background: #dcfce7; color: #16a34a; }

/* ============================================================
   11. GALLERY GRID
   ============================================================ */
.vana-gallery-grid {
  display:               grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap:                   12px;
}

.vana-gallery-item {
  aspect-ratio:  1;
  border-radius: 8px;
  overflow:      hidden;
  cursor:        pointer;
  background:    var(--vana-line);
}
.vana-gallery-item img {
  width:      100%;
  height:     100%;
  object-fit: cover;
  transition: transform .3s;
}
.vana-gallery-item:hover img { transform: scale(1.08); }

/* ============================================================
   12. SUBMISSION FORM
   ============================================================ */
.vana-form-wrap {
  background:    #fff;
  padding:       40px;
  border-radius: 16px;
  margin-top:    40px;
  border:        1px solid var(--vana-line);
  box-shadow:    0 10px 30px rgba(0,0,0,.03);
}

.vana-form-wrap input,
.vana-form-wrap textarea {
  width:       100%;
  padding:     12px 16px;
  border-radius: 8px;
  border:      1px solid #cbd5e1;
  background:  var(--vana-bg-soft);
  color:       var(--vana-text);
  font-family: 'Questrial', sans-serif;
  transition:  border-color .2s, box-shadow .2s;
  margin-top:  5px;
}
.vana-form-wrap input:focus,
.vana-form-wrap textarea:focus {
  outline:    none;
  border-color: var(--vana-gold);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(255,217,6,.2);
}

/* ============================================================
   13. MODAL / LIGHTBOX
   ============================================================ */
.vana-modal {
  position: fixed;
  inset:    0;
  z-index:  99999;
  display:  none;
}
.vana-modal.is-active { display: block; }

.vana-modal__backdrop {
  position:       absolute;
  inset:          0;
  background:     rgba(15,23,42,.9);
  backdrop-filter: blur(5px);
}

.vana-modal__dialog {
  position:       relative;
  max-width:      1000px;
  margin:         3vh auto;
  background:     #fff;
  border-radius:  16px;
  overflow:       hidden;
  display:        flex;
  flex-direction: column;
  max-height:     94vh;
  box-shadow:     0 25px 50px -12px rgba(0,0,0,.5);
}

.vana-modal__close {
  position:      absolute;
  top:           15px;
  right:         15px;
  z-index:       20;
  background:    #fff;
  color:         var(--vana-text);
  border:        none;
  border-radius: 50%;
  width:         40px;
  height:        40px;
  cursor:        pointer;
  font-size:     1.5rem;
  display:       flex;
  align-items:   center;
  justify-content: center;
  box-shadow:    0 4px 10px rgba(0,0,0,.15);
  transition:    background .2s;
}
.vana-modal__close:hover { background: var(--vana-gold); }

.vana-modal__nav {
  position:      absolute;
  top:           50%;
  transform:     translateY(-50%);
  background:    rgba(255,255,255,.9);
  color:         var(--vana-text);
  border:        none;
  width:         44px;
  height:        44px;
  border-radius: 50%;
  font-size:     1.5rem;
  display:       flex;
  align-items:   center;
  justify-content: center;
  cursor:        pointer;
  z-index:       15;
  box-shadow:    0 4px 10px rgba(0,0,0,.1);
  transition:    background .2s;
}
.vana-modal__nav:hover        { background: var(--vana-gold); }
.vana-modal__nav--prev        { left:  15px; }
.vana-modal__nav--next        { right: 15px; }

.vana-modal__media {
  background: #000;
  min-height: 200px;
  display:    flex;
  align-items: center;
  justify-content: center;
  position:   relative;
}

.vana-embed {
  position:       relative;
  width:          100%;
  height:         0;
  padding-bottom: 56.25%;
  background:     #000;
  overflow:       hidden;
}
.vana-embed iframe {
  position: absolute;
  top:      0;
  left:     0;
  width:    100%;
  height:   100%;
  border:   0;
}

.vana-modal__media .vana-embed { min-height: 400px; }
@media (max-width: 768px) {
  .vana-modal__media .vana-embed { min-height: 250px; }
}

.vana-media-container {
  width:           100%;
  max-height:      60vh;
  display:         flex;
  align-items:     center;
  justify-content: center;
  background:      var(--vana-bg-soft);
  border-radius:   12px;
  overflow:        hidden;
}
.vana-media-container img {
  max-width:  100%;
  max-height: 60vh;
  object-fit: contain;
  display:    block;
}

.vana-modal__img {
  max-width:  100%;
  max-height: 70vh;
  width:      auto;
  height:     auto;
  object-fit: contain;
  display:    block;
}

.vana-modal__body {
  padding:    25px 30px;
  overflow-y: auto;
  background: #fff;
}
#vanaModalMessage {
  padding-top: 20px;
  font-size:   1.1rem;
  line-height: 1.7;
  color:       var(--vana-text);
}
#vanaModalMessage img { margin-bottom: 20px; display: block; }

/* ============================================================
   14. ACESSIBILIDADE — focus-visible
   ============================================================ */
.vana-card a:focus-visible,
.vana-card button:focus-visible,
#vanaCopyFbLink:focus-visible,
.vana-tab:focus-visible,
.vana-nav-btn:focus-visible,
.vana-moment-btn:focus-visible {
  outline:        3px solid rgba(255,217,6,.9);
  outline-offset: 3px;
  border-radius:  8px;
}

</style>
