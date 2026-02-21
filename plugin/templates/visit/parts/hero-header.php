<?php
/**
 * Partial: Hero Header
 * Arquivo: templates/visit/parts/hero-header.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id, $visit_city_ref
 *   $tour_url, $tour_title
 *   $prev_id, $next_id
 *   $data (para title_pt / title_en da raiz do JSON)
 */
defined('ABSPATH') || exit;

// Subtítulo da visita (campo novo detectado no JSON real)
$visit_subtitle = Vana_Utils::pick_i18n_key($data, 'title', $lang);

// URL base da visita (sem query args de dia/vod)
$base_url = get_permalink($visit_id);

// URL do switcher de idioma
$current_url   = remove_query_arg('lang');
$lang_switch_url = $lang === 'pt'
    ? add_query_arg('lang', 'en', $current_url)
    : add_query_arg('lang', 'pt', $current_url);
$lang_switch_label = $lang === 'pt' ? '🇺🇸 EN' : '🇧🇷 PT';
?>
<header class="vana-hero">
  <div class="vana-wrap" style="padding: 0;">

    <!-- ── Tour Nav: voltar ao Tour + prev/next visita + lang ── -->
    <div class="vana-tour-nav">

      <!-- Lado esquerdo: link de volta ao Tour pai -->
      <div>
        <?php if ($tour_url && $tour_title): ?>
          <a
            href="<?php echo esc_url($tour_url); ?>"
            class="vana-nav-btn"
            style="border: none; background: transparent; padding: 0; color: var(--vana-gold);"
          >
            ← <?php echo esc_html($tour_title); ?>
          </a>
        <?php endif; ?>
      </div>

      <!-- Lado direito: prev / next / switcher de idioma -->
      <div class="vana-visit-siblings" style="display: flex; gap: 10px; align-items: center;">

        <?php if ($prev_id): ?>
          <a
            href="<?php echo esc_url(vana_visit_url($prev_id, '', -1, $lang)); ?>"
            class="vana-nav-btn"
            aria-label="<?php echo esc_attr($lang === 'en' ? 'Previous visit' : 'Visita anterior'); ?>"
          >
            ← <?php echo esc_html($lang === 'en' ? 'Previous' : 'Anterior'); ?>
          </a>
        <?php endif; ?>

        <?php if ($next_id): ?>
          <a
            href="<?php echo esc_url(vana_visit_url($next_id, '', -1, $lang)); ?>"
            class="vana-nav-btn"
            aria-label="<?php echo esc_attr($lang === 'en' ? 'Next visit' : 'Próxima visita'); ?>"
          >
            <?php echo esc_html($lang === 'en' ? 'Next' : 'Próxima'); ?> →
          </a>
        <?php endif; ?>

        <!-- Switcher de idioma -->
        <a
          href="<?php echo esc_url($lang_switch_url); ?>"
          class="vana-nav-btn lang-btn"
          style="border: 2px solid var(--vana-gold); background: #fff;"
          aria-label="<?php echo esc_attr($lang === 'en' ? 'Switch to Portuguese' : 'Switch to English'); ?>"
        >
          <?php echo esc_html($lang_switch_label); ?>
        </a>

      </div>
    </div>
    <!-- /Tour Nav -->

    <!-- ── Badge + Título + Subtítulo ── -->
    <span class="vana-badge">
      <?php echo esc_html($lang === 'en' ? 'Mission Diary' : 'Diário da Missão'); ?>
    </span>

    <h1><?php echo esc_html(get_the_title()); ?></h1>

    <?php if ($visit_subtitle && $visit_subtitle !== get_the_title()): ?>
      <p style="
        margin:      10px auto 0;
        max-width:   680px;
        font-size:   1.15rem;
        color:       var(--vana-muted);
        line-height: 1.5;
      ">
        <?php echo esc_html($visit_subtitle); ?>
      </p>
    <?php endif; ?>

    <!-- ── Localização da visita ── -->
    <?php if ($visit_city_ref): ?>
      <div style="
        margin-top:      15px;
        display:         inline-flex;
        align-items:     center;
        gap:             8px;
        font-weight:     700;
        color:           var(--vana-text);
        background:      rgba(255,255,255,.8);
        padding:         6px 16px;
        border-radius:   20px;
        border:          1px solid var(--vana-line);
      ">
        <span class="dashicons dashicons-location" style="color: var(--vana-pink);" aria-hidden="true"></span>
        <?php echo esc_html($visit_city_ref); ?>
      </div>
    <?php endif; ?>

  </div>
</header>
