<?php
/**
 * Partial: Schedule (Programação do Dia)
 * Arquivo: templates/visit/parts/schedule.php
 * v2.3 — accordion vod_ids + segment_start — 2026-03-02
 */
defined('ABSPATH') || exit;

$schedule = is_array($active_day['schedule'] ?? null) ? $active_day['schedule'] : [];
if (empty($schedule)) return;

// ── Índice de VODs do schema 5.1: id → objeto completo (O(1) lookup) ──
$vod_index = isset( $vana_vods_index ) && is_array( $vana_vods_index ) ? $vana_vods_index : [];
if ( empty( $vod_index ) && ! empty( $active_events ) && is_array( $active_events ) ) {
  foreach ( $active_events as $_evt ) {
    if ( ! is_array( $_evt ) ) {
      continue;
    }
    foreach ( $_evt['media']['vods'] ?? [] as $_v ) {
      if ( is_array( $_v ) && ! empty( $_v['id'] ) ) {
        $vod_index[ $_v['id'] ] = $_v;
      }
    }
  }
}

$status_labels = [
    'done'     => vana_t('schedule.status.done',     $lang),
    'live'     => vana_t('schedule.status.live',     $lang),
    'upcoming' => vana_t('schedule.status.upcoming', $lang),
    'break'    => vana_t('schedule.status.break',    $lang),
    'optional' => vana_t('schedule.status.optional', $lang),
];

$live_count = 0;
foreach ($schedule as $item) {
    if (is_array($item) && ($item['status'] ?? '') === 'live') $live_count++;
}
?>

<section
  id="vana-section-schedule"
  class="vana-section vana-section--schedule"
  aria-labelledby="vana-schedule-heading"
  <?php echo $live_count > 0 ? 'aria-live="polite" aria-atomic="false"' : ''; ?>
>

  <h2 class="vana-section-title" id="vana-schedule-heading">
    <?php echo esc_html(vana_t('schedule.programme', $lang)); ?>
    <?php if ($live_count > 0): ?>
      <span style="
        display:        inline-flex;
        align-items:    center;
        gap:            6px;
        font-size:      0.78rem;
        font-weight:    800;
        color:          #f87171;
        background:     rgba(220,38,38,0.15);
        border:         1px solid rgba(220,38,38,0.3);
        padding:        3px 12px;
        border-radius:  20px;
        margin-left:    12px;
        vertical-align: middle;
        font-family:    'Questrial', sans-serif;
        text-transform: uppercase;
      ">
        <span style="
          width:         7px; height:7px; border-radius:50%;
          background:    #f87171;
          animation:     vana-pulse 1.2s infinite;
          flex-shrink:   0;
        " aria-hidden="true"></span>
        <?php echo esc_html(vana_t('schedule.live_now', $lang)); ?>
      </span>
    <?php endif; ?>
  </h2>

  <!-- Legenda dual-timezone -->
  <div
    id="vana-tz-hint"
    style="
      display:       none;
      color:         var(--vana-muted);
      font-size:     0.82rem;
      font-weight:   700;
      margin:        -12px 0 14px;
      padding:       6px 12px;
      background:    var(--vana-bg-soft);
      border-radius: 8px;
      border:        1px solid var(--vana-line);
      width:         fit-content;
    "
  >
    <?php echo esc_html(vana_t('schedule.tz_hint', $lang)); ?>
  </div>

  <div
    class="vana-schedule-list"
    role="list"
    aria-label="<?php echo esc_attr(vana_t('schedule.items_aria', $lang)); ?>"
  >

    <?php foreach ($schedule as $idx => $item):
      if (!is_array($item)) continue;

      $time_local  = sanitize_text_field((string) ($item['time_local']  ?? $item['time'] ?? ''));
      $title_item  = Vana_Utils::pick_i18n_key($item, 'title', $lang);
      $desc_item   = Vana_Utils::pick_i18n_key($item, 'description', $lang);
      $status      = sanitize_text_field((string) ($item['status'] ?? 'upcoming'));
      $event_key   = sanitize_text_field((string) ($item['event_key'] ?? ''));
      $speaker     = sanitize_text_field((string) ($item['speaker'] ?? ''));
      $location_it = Vana_Utils::pick_i18n_key($item, 'location', $lang);

      if ($event_key === '' && $active_day_date !== '' && $time_local !== '') {
          // Fallback defensivo — nunca deveria chegar aqui, mas previne fatal
          if ( ! function_exists( 'vana_make_event_key' ) ) {
              function vana_make_event_key( string $date_local, string $time_local = '', string $title = '' ): string {
                  return md5( $date_local . $time_local . $title );
              }
          }
          $event_key = vana_make_event_key($active_day_date, $time_local, $title_item);
      }

      $status_css   = 'status-' . sanitize_html_class($status);
      $status_label = $status_labels[$status] ?? ucfirst($status);
      $is_live      = ($status === 'live');
      $is_done      = ($status === 'done');

      $ts_unix = 0;
      if ($active_day_date && $time_local) {
          try {
              $dt      = new DateTime($active_day_date . ' ' . $time_local . ':00', $visit_tz);
              $ts_unix = $dt->getTimestamp();
          } catch (Exception $e) { $ts_unix = 0; }
      }

      $item_id       = 'vana-sched-' . ($event_key ?: ($idx . '-' . sanitize_html_class($time_local)));
      $local_time_id = 'vana-ltime-' . ($event_key ?: $idx);
      $accordion_id  = 'vana-acc-'   . ($event_key ?: ($idx . '-' . sanitize_html_class($time_local)));

      // ── Estilos do item ──────────────────────────────────────
      if ($is_live) {
          $item_style = 'border-left: 4px solid #f87171; background: rgba(220,38,38,0.08);';
      } elseif ($is_done) {
          $item_style = 'opacity: .65;';
      } else {
          $item_style = '';
      }

      // ── Resolver caso VOD ────────────────────────────────────
      //
      // CASO A / D: vod_id (string)  → 1 vod, com ou sem segment_start
      // CASO E:     vod_ids (array)  → N vods → accordion
      // CASO C:     nenhum           → não clicável
      //
      $vod_id_single   = sanitize_text_field((string) ($item['vod_id'] ?? ''));
      $vod_ids_multi   = is_array($item['vod_ids'] ?? null) ? $item['vod_ids'] : [];
      $segment_start   = sanitize_text_field((string) ($item['segment_start'] ?? ''));

      // Normaliza: vod_id string → trata como array de 1 para simplificar lógica PHP
      $case = 'none'; // C
      if (!empty($vod_ids_multi)) {
          $case = 'multi';  // E
      } elseif ($vod_id_single !== '') {
          $case = 'single'; // A ou D
      }

      $has_vod_lnk = ($case !== 'none');

      // ── Badge de VODs para o caso multi ─────────────────────
      $vod_badge_count = count($vod_ids_multi);
    ?>

      <!-- ══ ITEM WRAPPER — inclui accordion ══════════════════ -->
      <div
        class="vana-schedule-item-wrap" role="listitem"
        data-title-full="<?php echo esc_attr($title_item ?: vana_t('schedule.untitled', $lang)); ?>"
        style="margin-bottom: 4px;"
      >

        <!-- Item principal -->
        <div
          id="<?php echo esc_attr($item_id); ?>"
          class="vana-schedule-item<?php
            echo $is_live     ? ' vana-schedule-item--live' : '';
            echo $has_vod_lnk ? ' has-vod'                 : '';
            echo $case === 'multi' ? ' has-vod-multi'       : '';
          ?>"
          role="<?php echo $has_vod_lnk ? 'button' : 'listitem'; ?>"
          <?php if ($has_vod_lnk): ?>
          tabindex="0"
          data-vod-case="<?php echo esc_attr($case); ?>"
          <?php if ($case === 'single'): ?>
          data-vod-id="<?php echo esc_attr($vod_id_single); ?>"
          data-segment-start="<?php echo esc_attr($segment_start); ?>"
          hx-get="<?php echo esc_url(rest_url('vana/v1/stage-fragment')); ?>"
          hx-vals='{"visit_id":<?php echo (int)$visit_id; ?>,"item_id":"<?php echo esc_attr($vod_id_single); ?>","item_type":"vod","lang":"<?php echo esc_attr($lang); ?>"}'
          hx-target="#vana-stage-wrapper"
          hx-swap="innerHTML transition:true"
          hx-indicator="#vana-stage-spinner"
          <?php endif; ?>
          <?php if ($case === 'multi'): ?>
          data-accordion-id="<?php echo esc_attr($accordion_id); ?>"
          aria-expanded="false"
          aria-controls="<?php echo esc_attr($accordion_id); ?>"
          <?php endif; ?>
          data-day="<?php echo esc_attr($active_day_date); ?>"
          <?php endif; ?>
          aria-label="<?php echo esc_attr(
            ($has_vod_lnk
              ? vana_t('schedule.load_video', $lang)
              : '')
            . $time_local . ' — ' . $title_item
            . ($is_live ? (' — ' . vana_t('schedule.live_aria', $lang)) : '')
          ); ?>"
          style="<?php echo esc_attr($item_style); ?><?php echo $has_vod_lnk ? ' cursor:pointer;' : ''; ?>"
        >

          <!-- Horário + dual-tz -->
          <div class="vana-schedule-time" aria-hidden="true">
            <div><?php echo esc_html($time_local ?: '—'); ?></div>
            <?php if ($ts_unix > 0): ?>
              <div
                id="<?php echo esc_attr($local_time_id); ?>"
                class="vana-local-time-target"
                data-ts="<?php echo (int) $ts_unix; ?>"
                style="font-size:.72rem; font-weight:700; color:var(--vana-muted);
                       font-family:monospace; margin-top:2px; overflow:hidden; max-width:100%; white-space:normal; word-break:break-word;"
                aria-label="<?php echo esc_attr(vana_t('schedule.local_time', $lang)); ?>"
              ></div>
            <?php endif; ?>
          </div>

          <!-- Conteúdo -->
          <div class="vana-schedule-title" style="flex-grow:1; margin:0 15px;">

            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">

              <?php if ($is_live): ?>
                <span style="
                  width:9px; height:9px; border-radius:50%;
                  background:#f87171; flex-shrink:0;
                  animation:vana-pulse 1.2s infinite;
                " aria-hidden="true"></span>
              <?php elseif ($is_done): ?>
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"
                      style="color:#4ade80; font-size:16px; width:16px; height:16px; flex-shrink:0;"></span>
              <?php endif; ?>

              <strong style="font-size:1.05rem; color:var(--vana-text); cursor:pointer;" title="<?php echo esc_attr($title_item); ?>">
                <?php echo esc_html($title_item ?: vana_t('schedule.untitled', $lang)); ?>
              </strong>

              <?php if ($case === 'single'): ?>
                <!-- Badge: ▶ Assistir -->
                <span class="vana-schedule-vod-badge" aria-hidden="true" style="
                  display:inline-flex; align-items:center; gap:4px;
                  font-size:.72rem; font-weight:700;
                  color:var(--vana-gold, #f59e0b);
                  margin-left:auto; flex-shrink:0;
                ">▶ <?php echo esc_html(vana_t('schedule.watch', $lang)); ?></span>

              <?php elseif ($case === 'multi'): ?>
                <!-- Badge: N vídeos + chevron -->
                <span class="vana-schedule-vod-badge" aria-hidden="true" style="
                  display:inline-flex; align-items:center; gap:5px;
                  font-size:.72rem; font-weight:700;
                  color:var(--vana-gold, #f59e0b);
                  margin-left:auto; flex-shrink:0;
                ">
                  ▶ <?php echo (int) $vod_badge_count; ?> <?php echo esc_html(vana_t('schedule.videos', $lang)); ?>
                  <svg class="vana-acc-chevron" width="12" height="12" viewBox="0 0 12 12"
                       fill="none" style="transition:transform .25s;">
                    <path d="M2 4L6 8L10 4" stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
              <?php endif; ?>

            </div>

            <?php if ($desc_item): ?>
              <div style="color:var(--vana-muted); font-size:.9rem; line-height:1.5; margin-top:4px;">
                <?php echo esc_html($desc_item); ?>
              </div>
            <?php endif; ?>

            <?php if ($speaker || $location_it): ?>
              <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:6px;
                          font-size:.82rem; font-weight:700; color:var(--vana-muted);">
                <?php if ($speaker): ?>
                  <span style="display:inline-flex; align-items:center; gap:4px;">
                    <span class="dashicons dashicons-admin-users" aria-hidden="true"
                          style="font-size:13px; width:13px; height:13px; color:var(--vana-orange);"></span>
                    <?php echo esc_html($speaker); ?>
                  </span>
                <?php endif; ?>
                <?php if ($location_it): ?>
                  <span style="display:inline-flex; align-items:center; gap:4px;">
                    <span class="dashicons dashicons-location" aria-hidden="true"
                          style="font-size:13px; width:13px; height:13px; color:var(--vana-pink);"></span>
                    <?php echo esc_html($location_it); ?>
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          </div>

          <!-- Badge status -->
          <div
            class="vana-schedule-status <?php echo esc_attr($status_css); ?>"
            aria-label="Status: <?php echo esc_attr($status_label); ?>"
          >
            <?php echo esc_html($status_label); ?>
          </div>

        </div>
        <!-- /item principal -->

        <?php if ($case === 'multi'): ?>
        <!-- ══ ACCORDION — Caso E: 1 agenda → N vods ══════════ -->
        <div
          id="<?php echo esc_attr($accordion_id); ?>"
          class="vana-vod-accordion"
          role="region"
          aria-label="<?php echo esc_attr(
            vana_t('schedule.videos_for', $lang) . $title_item
          ); ?>"
          hidden
        >
          <ul class="vana-vod-accordion__list" role="list">
            <?php foreach ($vod_ids_multi as $vi => $vod_ref):
              if (!is_array($vod_ref)) continue;

              $ref_id    = sanitize_text_field((string) ($vod_ref['vod_id'] ?? ''));
              $ref_seg   = sanitize_text_field((string) ($vod_ref['segment_start'] ?? ''));
              $ref_label = sanitize_text_field((string) (
                $lang === 'en'
                  ? ($vod_ref['label_en'] ?? $vod_ref['label_pt'] ?? '')
                  : ($vod_ref['label_pt'] ?? $vod_ref['label_en'] ?? '')
              ));

              // Título fallback: pega do vod_index ou usa label
              $ref_vod   = $vod_index[$ref_id] ?? [];
              $ref_title = $ref_label
                ?: Vana_Utils::pick_i18n_key($ref_vod, 'title', $lang)
                ?: $ref_id;

              if ($ref_id === '') continue;
            ?>
              <li
                class="vana-vod-accordion__item"
                role="listitem"
              >
                <button
                  type="button"
                  class="vana-vod-accordion__btn"
                  data-vod-id="<?php echo esc_attr($ref_id); ?>"
                  data-segment-start="<?php echo esc_attr($ref_seg); ?>"
                  aria-label="<?php echo esc_attr(
                    vana_t('schedule.watch_item', $lang) . $ref_title
                    . ($ref_seg ? (' — ' . $ref_seg) : '')
                  ); ?>"
                  hx-get="<?php echo esc_url(rest_url('vana/v1/stage-fragment')); ?>"
                  hx-vals='{"visit_id":<?php echo (int)$visit_id; ?>,"item_id":"<?php echo esc_attr($ref_id); ?>","item_type":"vod","lang":"<?php echo esc_attr($lang); ?>"}'
                  hx-target="#vana-stage-wrapper"
                  hx-swap="innerHTML transition:true"
                  hx-indicator="#vana-stage-spinner"
                >
                  <span class="vana-vod-accordion__icon" aria-hidden="true">▶</span>
                  <span class="vana-vod-accordion__label">
                    <?php echo esc_html($ref_title); ?>
                  </span>
                  <?php if ($ref_seg): ?>
                    <span class="vana-vod-accordion__seg" aria-hidden="true">
                      <?php echo esc_html($ref_seg); ?>
                    </span>
                  <?php endif; ?>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <!-- /accordion -->
        <?php endif; ?>

      </div>
      <!-- /item-wrap -->

    <?php endforeach; ?>

  </div>

</section>

<?php if ($live_count > 0): ?>
<style>
@keyframes vana-pulse {
  0%, 100% { opacity:1; transform:scale(1); }
  50%       { opacity:.4; transform:scale(1.35); }
}
</style>
<?php endif; ?>

<script>
(function () {
  var eventTz = <?php echo wp_json_encode($visit_tz->getName()); ?>;
  var hint    = document.getElementById('vana-tz-hint');
  if (!hint) return;
  try {
    var localTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (localTz && localTz !== eventTz) hint.style.display = 'block';
  } catch (_) {}
})();
</script>
