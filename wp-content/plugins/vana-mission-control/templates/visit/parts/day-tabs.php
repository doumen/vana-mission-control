<?php
/**
 * Partial: Day Tabs
 * Arquivo: templates/visit/parts/day-tabs.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id, $days, $active_index
 *
 * Renderiza as abas de navegação entre dias da visita.
 * Não renderiza nada se houver apenas 1 dia (UX limpa).
 */
defined('ABSPATH') || exit;

// Nada a renderizar se não há dias ou só há um
if (empty($days) || count($days) <= 1) return;
?>
<nav
  class="vana-tabs"
  role="tablist"
  aria-label="<?php echo esc_attr(vana_t('tabs.nav_aria', $lang)); ?>"
>
  <?php foreach ($days as $i => $day):

    $date_local = (string) ($day['date_local'] ?? '');
    $ts         = $date_local ? strtotime($date_local . ' 12:00:00') : 0;
    $is_active  = ($i === $active_index);

    // ── Label principal: dd/mm ──────────────────────────────
    $label_date = $ts ? wp_date('d/m', $ts) : vana_t('tabs.day', $lang) . ' ' . ($i + 1);

    // ── Label do dia da semana (ex: Sáb / Sat) ─────────────
    $label_weekday = $ts ? wp_date('D', $ts) : '';

    // ── Título alternativo da aba (title_pt / title_en) ─────
    // Permite sobrescrever label via JSON: days[].tab_label_pt
    $tab_label_pt = (string) ($day['tab_label_pt'] ?? '');
    $tab_label_en = (string) ($day['tab_label_en'] ?? '');
    $tab_label    = $lang === 'en'
      ? ($tab_label_en ?: $tab_label_pt)
      : ($tab_label_pt ?: $tab_label_en);

    // ── Indicador de dia com live ───────────────────────────
    $has_live = false;
    $schedule = is_array($day['schedule'] ?? null) ? $day['schedule'] : [];
    foreach ($schedule as $item) {
      if (is_array($item) && ($item['status'] ?? '') === 'live') {
        $has_live = true;
        break;
      }
    }

    // ── URL da aba ──────────────────────────────────────────
    // Fallback defensivo — nunca deveria chegar aqui, mas previne fatal
    if ( ! function_exists( 'vana_visit_url' ) ) {
        function vana_visit_url( int $post_id, string $v_day = '', int $vod = -1, string $lang = 'pt' ): string {
            $url = get_permalink( $post_id ) ?: '';
            if ( $v_day ) $url = add_query_arg( 'day', $v_day, $url );
            if ( $vod >= 0 ) $url = add_query_arg( 'vod', $vod, $url );
            if ( $lang !== 'pt' ) $url = add_query_arg( 'lang', $lang, $url );
            return $url;
        }
    }
    $tab_url = vana_visit_url($visit_id, $date_local, -1, $lang);

  ?>
    <a
      href="<?php echo esc_url($tab_url); ?>"
      class="vana-tab<?php echo $is_active ? ' active' : ''; ?>"
      role="tab"
      aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
      aria-label="<?php echo esc_attr(
        vana_t('tabs.day', $lang) . ' ' . ($i + 1) .
        ($date_local ? ' — ' . $label_date : '') .
        ($has_live   ? vana_t('tabs.live_now', $lang) : '')
      ); ?>"
    >
      <?php if ($tab_label): ?>

        <?php echo esc_html($tab_label); ?>

      <?php else: ?>

        <span style="display: flex; flex-direction: column; align-items: center; gap: 1px; line-height: 1.2;">
          <?php if ($label_weekday): ?>
            <span style="font-size: 0.7rem; font-weight: 700; opacity: .7; text-transform: uppercase;">
              <?php echo esc_html($label_weekday); ?>
            </span>
          <?php endif; ?>
          <span><?php echo esc_html($label_date); ?></span>
        </span>

      <?php endif; ?>

      <?php if ($has_live): ?>
        <span
          aria-hidden="true"
          style="
            display:       inline-block;
            width:         8px;
            height:        8px;
            border-radius: 50%;
            background:    #dc2626;
            margin-left:   6px;
            animation:     vana-pulse 1.2s infinite;
            flex-shrink:   0;
          "
        ></span>
      <?php endif; ?>

    </a>

  <?php endforeach; ?>
</nav>

<?php if (array_filter(array_column($days, 'schedule'), function($s) {
  if (!is_array($s)) return false;
  foreach ($s as $i) { if (is_array($i) && ($i['status'] ?? '') === 'live') return true; }
  return false;
})): ?>
<style>
@keyframes vana-pulse {
  0%, 100% { opacity: 1;   transform: scale(1);    }
  50%       { opacity: .4; transform: scale(1.35); }
}
</style>
<?php endif; ?>
