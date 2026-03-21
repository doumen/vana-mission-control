<?php
/**
 * Partial: Hari-kathā
 * Arquivo: templates/visit/parts/hari-katha.php
 */
defined('ABSPATH') || exit;
?>
<section
  id="vana-section-hari-katha"
  class="vana-section vana-section--hari-katha"
  data-visit-id="<?php echo (int) $visit_id; ?>"
  data-day="<?php echo esc_attr($active_day_date); ?>"
  data-lang="<?php echo esc_attr($lang); ?>"
  aria-labelledby="vana-hk-heading"
>
  <h2 class="vana-section-title" id="vana-hk-heading">
    <?php echo esc_html(vana_t('hk.section', $lang)); ?>
  </h2>

  <p class="vana-hk__intro" data-role="hk-intro">
    <?php echo esc_html(vana_t('hk.loading_kathas', $lang)); ?>
  </p>

  <div class="vana-hk__list"     data-role="katha-list"></div>
  <div class="vana-hk__passages" data-role="passage-list" hidden></div>
</section>
