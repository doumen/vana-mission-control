<?php
/**
 * Orquestrador da Página de Visita
 * Arquivo: templates/visit/visit-template.php
 *
 * Incluído por: single-vana_visit.php
 *
 * Responsabilidades:
 *   1. Carregar o _bootstrap.php (dados + utilitários)
 *   2. Renderizar cada partial na ordem correta
 *   3. Incluir assets (CSS e JS) nos lugares certos
 *
 * Variáveis expostas pelo _bootstrap.php:
 *   $visit_data       array   — todos os campos da visita
 *   $days             array   — dias indexados por data YYYY-MM-DD
 *   $active_day       array   — dados do dia ativo
 *   $active_day_date  string  — YYYY-MM-DD do dia ativo
 *   $active_vod_index int     — índice do VOD ativo (0-based)
 *   $lang             string  — 'pt' | 'en'
 *   $visit_tz         DateTimeZone
 *   $visit_id         int     — post ID do WordPress
 */
defined('ABSPATH') || exit;

// ── 1. Bootstrap (dados + utils) ─────────────────────────────
require_once __DIR__ . '/_bootstrap.php';

// ── 2. CSS crítico inline ─────────────────────────────────────
require_once __DIR__ . '/assets/visit-style.php';
?>

<div class="vana-wrap" id="vanaVisitRoot">

  <?php
  // ── 3. Hero Header ──────────────────────────────────────────
  require __DIR__ . '/parts/hero-header.php';

  // ── 4. Abas de dias ────────────────────────────────────────
  require __DIR__ . '/parts/day-tabs.php';

  // ── 5. Palco principal (YouTube + Facebook + Segmentos) ─────
  require __DIR__ . '/parts/stage.php';

  // ── 6. Lista de VODs do dia ─────────────────────────────────
  if ( !empty($active_day['vods']) ) :
      require __DIR__ . '/parts/vod-list.php';
  endif;

  // ── 7. Programação do dia ───────────────────────────────────
  if ( !empty($active_day['schedule']) ) :
      require __DIR__ . '/parts/schedule.php';
  endif;

  // ── 8. Links da comunidade ──────────────────────────────────
  require __DIR__ . '/parts/community-links.php';

  // ── 9. Galeria de fotos ─────────────────────────────────────
  if ( !empty($active_day['gallery']) ) :
      require __DIR__ . '/parts/gallery.php';
  endif;

  // ── 10. Momentos da Sangha ──────────────────────────────────
  if ( !empty($active_day['sangha_moments']) ) :
      require __DIR__ . '/parts/sangha-moments.php';
  endif;
  ?>

</div><!-- /.vana-wrap -->

<?php
// ── 11. JS no final do body ────────────────────────────────────
require_once __DIR__ . '/visit-scripts.php';
?>
