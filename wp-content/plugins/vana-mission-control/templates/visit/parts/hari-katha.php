<?php
/**
 * Partial: Hari-kathā — Sprint 1 (Modo Foco)
 * Arquivo: templates/visit/parts/hari-katha.php
 *
 * Variáveis do _bootstrap.php:
 *   $visit_id, $active_day_date, $lang
 *
 * Responsabilidades:
 *   1. Container da seção HK
 *   2. Zona mutável com 3 estados: lista | passage-foco | vazio
 *   3. Data-attributes para o JS orquestrar
 *
 * v2.0 — Sprint 1 Modo Foco
 */
defined('ABSPATH') || exit;

// ── i18n ──
$hk_strings = [
    'pt' => [
        'section'       => '🙏 Hari-kathā',
        'loading'       => 'Carregando kathās…',
        'empty'         => 'Nenhuma kathā registrada para este dia.',
        'back_list'     => '← Kathās do dia',
        'back_passages' => '← Passages',
        'passages'      => 'passages',
        'prev'          => '← Anterior',
        'next'          => 'Próximo →',
        'of'            => 'de',
        'see_full'      => '📜 Ver aula completa',
        'permalink'     => '🔗 Link permanente',
        'copied'        => 'Copiado!',
        'seek'          => 'Ir para este trecho',
        'morning'       => 'Manhã',
        'midday'        => 'Tarde',
        'night'         => 'Noite',
        'other'         => 'Outro',
        'err_kathas'    => 'Erro ao carregar kathās.',
        'err_passages'  => 'Erro ao carregar passages.',
        'reel'          => 'Potencial para Reels',
        'confidential'  => 'Conteúdo confidencial',
        'load_more'     => 'Carregar mais',
    ],
    'en' => [
        'section'       => '🙏 Hari-kathā',
        'loading'       => 'Loading kathās…',
        'empty'         => 'No kathā registered for this day.',
        'back_list'     => '← Day kathās',
        'back_passages' => '← Passages',
        'passages'      => 'passages',
        'prev'          => '← Previous',
        'next'          => 'Next →',
        'of'            => 'of',
        'see_full'      => '📜 See full class',
        'permalink'     => '🔗 Permalink',
        'copied'        => 'Copied!',
        'seek'          => 'Jump to this moment',
        'morning'       => 'Morning',
        'midday'        => 'Afternoon',
        'night'         => 'Night',
        'other'         => 'Other',
        'err_kathas'    => 'Error loading kathās.',
        'err_passages'  => 'Error loading passages.',
        'reel'          => 'Reel potential',
        'confidential'  => 'Confidential content',
        'load_more'     => 'Load more',
    ],
];
$hk_i18n = $hk_strings[$lang] ?? $hk_strings['pt'];
?>

<section
  class="vana-section vana-section--hari-katha"
  id="vana-hari-katha-root"
  data-visit-id="<?php echo (int) $visit_id; ?>"
  data-day="<?php echo esc_attr($active_day_date); ?>"
  data-lang="<?php echo esc_attr($lang); ?>"
  aria-labelledby="vana-hk-heading"
>

  <!-- ── Cabeçalho da seção ── -->
  <h2 class="vana-section-title" id="vana-hk-heading">
    <?php echo esc_html($hk_i18n['section']); ?>
  </h2>

  <!-- ══════════════════════════════════════════════════════════
       ZONA MUTÁVEL — 3 painéis exclusivos (só 1 visível por vez)
       ══════════════════════════════════════════════════════════ -->
  <div class="vana-hk-zone" id="vana-hk-zone" data-state="list">

    <!-- ── Painel 1: Lista de Kathās ── -->
    <div class="vana-hk-panel" data-panel="list" aria-hidden="false">
      <p class="vana-hk__intro" data-role="hk-intro">
        <?php echo esc_html($hk_i18n['loading']); ?>
      </p>
      <div class="vana-hk__list" data-role="katha-list"></div>
    </div>

    <!-- ── Painel 2: Lista de Passages (scroll) ── -->
    <div class="vana-hk-panel" data-panel="passages" aria-hidden="true" hidden>
      <div class="vana-hk-passages-header">
        <button type="button" class="vana-hk-back" data-action="back-to-list">
          <?php echo esc_html($hk_i18n['back_list']); ?>
        </button>
        <h3 class="vana-hk-katha-title" data-role="katha-title"></h3>
        <div class="vana-hk-katha-meta" data-role="katha-meta"></div>
      </div>
      <div class="vana-hk-passage-list" data-role="passage-list"></div>
      <div class="vana-hk-pagination" data-role="pagination" hidden>
        <button type="button" class="vana-hk-load-more" data-action="load-more">
          <?php echo esc_html($hk_i18n['load_more']); ?>
        </button>
      </div>
    </div>

    <!-- ── Painel 3: Passage Foco (isolado com prev/next) ── -->
    <div class="vana-hk-panel" data-panel="focus" aria-hidden="true" hidden>
      <div class="vana-hk-focus-header">
        <button type="button" class="vana-hk-back" data-action="back-to-passages">
          <?php echo esc_html($hk_i18n['back_passages']); ?>
        </button>
        <span class="vana-hk-focus-position" data-role="focus-position">
          <!-- "3 de 9" -->
        </span>
      </div>
      <article class="vana-hk-focus-content" data-role="focus-content">
        <!-- Conteúdo do passage injetado via JS -->
      </article>
      <nav class="vana-hk-focus-nav" data-role="focus-nav" aria-label="Passage navigation">
        <button type="button" class="vana-hk-focus-prev" data-action="prev" disabled>
          <?php echo esc_html($hk_i18n['prev']); ?>
        </button>
        <button type="button" class="vana-hk-focus-next" data-action="next" disabled>
          <?php echo esc_html($hk_i18n['next']); ?>
        </button>
      </nav>
      <a class="vana-hk-see-full" data-action="see-full" href="#">
        <?php echo esc_html($hk_i18n['see_full']); ?>
      </a>
    </div>

  </div>
  <!-- /.vana-hk-zone -->

</section>

<!-- ── Config JSON para o JS ── -->
<script>
window.vanaHKConfig = {
  restBase  : <?php echo wp_json_encode(rest_url('vana/v1')); ?>,
  restNonce : <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>,
  visitId   : <?php echo (int) $visit_id; ?>,
  day       : <?php echo wp_json_encode($active_day_date); ?>,
  lang      : <?php echo wp_json_encode($lang); ?>,
  i18n      : <?php echo wp_json_encode($hk_i18n); ?>
};
</script>
