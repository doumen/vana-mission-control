<?php
/**
 * Partial: Anchor Chips (Navegação por seção)
 * Arquivo: templates/visit/parts/anchor-chips.php
 *
 * Renderiza chips de âncora que:
 *   1. Fazem scroll suave para a seção correspondente
 *   2. Ficam sticky abaixo do header
 *   3. Destacam o chip da seção visível (Intersection Observer)
 *
 * Chips exibidos condicionalmente:
 *   📅 Agenda     → sempre (se há schedule)
 *   🎬 Aulas      → se há vods no dia
 *   📰 Revista    → se visita tem revista publicada
 *   💛 Sangha     → sempre
 */
defined('ABSPATH') || exit;

// ── Quais chips exibir ────────────────────────────────────────
// schema 5.1 — FIX B3
$has_schedule = !empty($active_day['schedule']);
$has_vods       = !empty($vod_list);            // $vod_list vem do _bootstrap_shim.php
$has_hari_katha = function_exists('vana_visit_day_has_kathas')
    ? vana_visit_day_has_kathas($visit_id, $active_day_date)
    : false;

$has_gallery = (bool) get_posts([
    'post_type'      => 'vana_submission',
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => '_visit_id',
            'value'   => $visit_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => '_subtype',
            'value'   => 'gurudeva_gallery',
            'compare' => '=',
        ],
    ],
]);

$has_moments  = true; // Sangha sempre visível — lê CPT vana_submission

// Revista: verifica meta da visita
$mag_state  = (string) get_post_meta($visit_id, '_vana_mag_state', true);
$has_revista = $mag_state === 'publicada';

// Monta lista de chips ativos
$chips = [];

if ($has_schedule):
    $chips[] = [
        'id'    => 'vana-section-schedule',
        'icon'  => '📅',
        'label' => vana_t('anchor.agenda', $lang),
    ];
endif;

if ($has_vods):
    $chips[] = [
        'id'    => 'vana-section-vods',
        'icon'  => '🎬',
        'label' => vana_t('anchor.classes', $lang),
    ];
endif;

if ($has_hari_katha):
    $chips[] = [
        'id'    => 'vana-section-hari-katha',
        'icon'  => '📖',
        'label' => vana_t('anchor.hari_katha', $lang),
    ];
endif;

if ($has_gallery):
    $chips[] = [
        'id'    => 'vana-section-gallery',
        'icon'  => '📸',
        'label' => vana_t('anchor.photos', $lang),
    ];
endif;

if ($has_revista):
    $chips[] = [
        'id'    => 'vana-section-revista',
        'icon'  => '📰',
        'label' => vana_t('anchor.magazine', $lang),
    ];
endif;

if ($has_moments):
    $chips[] = [
        'id'    => 'vana-section-sangha',
        'icon'  => '💛',
        'label' => vana_t('anchor.sangha', $lang),
    ];
endif;

// Sem chips → não renderiza nada
if (empty($chips)) return;
?>

<nav
  id="vana-anchor-chips"
  class="vana-anchor-chips"
  aria-label="<?php echo esc_attr(vana_t('anchor.nav_aria', $lang)); ?>"
  style="
    position:        sticky;
    top:             56px; /* altura do vana-header */
    z-index:         900;
    background:      rgba(255,255,255,0.96);
    backdrop-filter: blur(10px);
    border-bottom:   1px solid var(--vana-line);
    padding:         0 16px;
    overflow-x:      auto;
    overflow-y:      hidden;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  "
>
  <div style="
    display:      flex;
    gap:          4px;
    padding:      10px 0;
    width:        max-content;
    min-width:    100%;
  ">

    <?php foreach ($chips as $chip): ?>
      <a
        href="#<?php echo esc_attr($chip['id']); ?>"
        class="vana-anchor-chip"
        data-target="<?php echo esc_attr($chip['id']); ?>"
        style="
          display:         inline-flex;
          align-items:     center;
          gap:             6px;
          padding:         7px 16px;
          border-radius:   999px;
          border:          1px solid var(--vana-line);
          background:      transparent;
          color:           var(--vana-text-soft);
          font-weight:     700;
          font-size:       0.85rem;
          font-family:     'Syne', sans-serif;
          text-decoration: none;
          white-space:     nowrap;
          transition:      background .18s, border-color .18s,
                           color .18s, box-shadow .18s;
          cursor:          pointer;
        "
        aria-label="<?php echo esc_attr(vana_t('anchor.go_to', $lang) . $chip['label']); ?>"
      >
        <span aria-hidden="true"><?php echo esc_html($chip['icon']); ?></span>
        <span><?php echo esc_html($chip['label']); ?></span>
      </a>
    <?php endforeach; ?>

  </div>
</nav>

<style>
/* Esconde scrollbar dos chips no webkit */
#vana-anchor-chips::-webkit-scrollbar { display: none; }

/* Chip ativo */
.vana-anchor-chip.is-active {
  background:   var(--vana-gold) !important;
  border-color: var(--vana-gold) !important;
  color:        #0f172a !important;
  box-shadow:   0 2px 10px rgba(255,217,6,.35) !important;
}
.vana-anchor-chip:hover:not(.is-active) {
  background:   rgba(255,217,6,.10);
  border-color: rgba(255,217,6,.4);
  color:        var(--vana-text);
}
</style>

<script>
(function () {
  'use strict';

  // ── Scroll suave (previne o salto brusco) ──────────────────
  document.querySelectorAll('.vana-anchor-chip').forEach(function (chip) {
    chip.addEventListener('click', function (e) {
      e.preventDefault();
      var targetId = this.getAttribute('data-target');
      var target   = document.getElementById(targetId);
      if (!target) return;

      // Offset = header (56px) + chips (45px) + folga (8px)
      var offset = 56 + 45 + 8;
      var top    = target.getBoundingClientRect().top
                   + window.pageYOffset
                   - offset;

      window.scrollTo({ top: top, behavior: 'smooth' });

      // Atualiza aria-current imediatamente (feedback visual)
      setActive(this);
    });
  });

  // ── Intersection Observer: chip ativo no scroll ────────────
  var chips    = document.querySelectorAll('.vana-anchor-chip');
  var sections = [];

  chips.forEach(function (chip) {
    var id  = chip.getAttribute('data-target');
    var sec = document.getElementById(id);
    if (sec) sections.push({ chip: chip, section: sec });
  });

  if (!sections.length || !window.IntersectionObserver) return;

  var activeChip = null;

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      sections.forEach(function (item) {
        if (item.section === entry.target) {
          setActive(item.chip);
        }
      });
    });
  }, {
    rootMargin: '-56px 0px -60% 0px', // considera o header sticky
    threshold:  0,
  });

  sections.forEach(function (item) {
    observer.observe(item.section);
  });

  function setActive(chip) {
    chips.forEach(function (c) {
      c.classList.remove('is-active');
      c.removeAttribute('aria-current');
    });
    chip.classList.add('is-active');
    chip.setAttribute('aria-current', 'true');

    // Scroll horizontal dos chips para centralizar o ativo
    var nav = document.getElementById('vana-anchor-chips');
    if (!nav) return;
    var chipLeft  = chip.offsetLeft;
    var chipWidth = chip.offsetWidth;
    var navWidth  = nav.offsetWidth;
    nav.scrollTo({
      left:     chipLeft - (navWidth / 2) + (chipWidth / 2),
      behavior: 'smooth',
    });
  }

}());
</script>
