<?php
/**
 * Partial: Schedule (Programação do Dia)
 * Arquivo: templates/visit/parts/schedule.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id
 *   $active_day, $active_day_date
 *   $visit_tz
 *
 * Responsabilidades:
 *   1. Renderizar lista de itens da programação do dia
 *   2. Exibir status (done / live / upcoming)
 *   3. Dual-timezone: horário local do evento + horário do visitante
 *   4. Indicador visual de item atual (live)
 *   5. Não renderizar nada se não há schedule no dia
 */
defined('ABSPATH') || exit;

$schedule = is_array($active_day['schedule'] ?? null) ? $active_day['schedule'] : [];

// Nada a renderizar se não há itens
if (empty($schedule)) return;

// Labels de status
$status_labels = [
    'done'     => $lang === 'en' ? 'Done'      : 'Concluído',
    'live'     => $lang === 'en' ? 'Live'       : 'Ao Vivo',
    'upcoming' => $lang === 'en' ? 'Soon'       : 'Em Breve',
    'break'    => $lang === 'en' ? 'Break'      : 'Intervalo',
    'optional' => $lang === 'en' ? 'Optional'   : 'Opcional',
];

// Conta quantos itens estão live (para aria-live)
$live_count = 0;
foreach ($schedule as $item) {
    if (is_array($item) && ($item['status'] ?? '') === 'live') $live_count++;
}
?>

<section
  class="vana-section vana-section--schedule"
  aria-labelledby="vana-schedule-heading"
  <?php echo $live_count > 0 ? 'aria-live="polite" aria-atomic="false"' : ''; ?>
>

  <h2 class="vana-section-title" id="vana-schedule-heading">
    <?php echo esc_html($lang === 'en' ? 'Programme' : 'Programação'); ?>
    <?php if ($live_count > 0): ?>
      <span style="
        display:        inline-flex;
        align-items:    center;
        gap:            6px;
        font-size:      0.8rem;
        font-weight:    800;
        color:          #dc2626;
        background:     #fee2e2;
        padding:        3px 12px;
        border-radius:  20px;
        margin-left:    12px;
        vertical-align: middle;
        font-family:    'Questrial', sans-serif;
        text-transform: uppercase;
      ">
        <span style="
          width:         7px;
          height:        7px;
          border-radius: 50%;
          background:    #dc2626;
          animation:     vana-pulse 1.2s infinite;
          flex-shrink:   0;
        " aria-hidden="true"></span>
        <?php echo esc_html($lang === 'en' ? 'Live now' : 'Ao Vivo'); ?>
      </span>
    <?php endif; ?>
  </h2>

  <!-- Legenda dual-timezone (JS a preencher) -->
  <div
    id="vana-tz-hint"
    style="
      display:     none;
      color:       var(--vana-muted);
      font-size:   0.82rem;
      font-weight: 700;
      margin:      -12px 0 14px;
      padding:     6px 12px;
      background:  var(--vana-bg-soft);
      border-radius: 8px;
      border:      1px solid var(--vana-line);
      width:       fit-content;
    "
    aria-label="<?php echo esc_attr($lang === 'en'
      ? 'Times shown in event timezone and your local timezone'
      : 'Horários no fuso do evento e no seu fuso local'
    ); ?>"
  >
    <?php echo esc_html($lang === 'en'
      ? '🕐 Times in event timezone — your local time shown below each item.'
      : '🕐 Horários no fuso do evento — seu horário local aparece abaixo de cada item.'
    ); ?>
  </div>

  <div
    class="vana-schedule-list"
    role="list"
    aria-label="<?php echo esc_attr($lang === 'en' ? 'Schedule items' : 'Itens da programação'); ?>"
  >

    <?php foreach ($schedule as $idx => $item):
      if (!is_array($item)) continue;

      // ── Campos base ─────────────────────────────────────────
      $time_local  = sanitize_text_field((string) ($item['time_local']  ?? $item['time'] ?? ''));
      $title_item  = Vana_Utils::pick_i18n_key($item, 'title', $lang);
      $desc_item   = Vana_Utils::pick_i18n_key($item, 'description', $lang);
      $status      = sanitize_text_field((string) ($item['status'] ?? 'upcoming'));
      $event_key   = sanitize_text_field((string) ($item['event_key'] ?? ''));
      $speaker     = sanitize_text_field((string) ($item['speaker'] ?? ''));
      $location_it = Vana_Utils::pick_i18n_key($item, 'location', $lang);

      // Gera event_key em runtime se ausente (ver bootstrap)
      if ($event_key === '' && $active_day_date !== '' && $time_local !== '') {
          $event_key = vana_make_event_key($active_day_date, $time_local, $title_item);
      }

      // ── Status CSS class e label ─────────────────────────────
      $status_css   = 'status-' . sanitize_html_class($status);
      $status_label = $status_labels[$status] ?? ucfirst($status);

      $is_live = ($status === 'live');
      $is_done = ($status === 'done');

      // ── Timestamp Unix para dual-timezone ────────────────────
      // Combina date_local + time_local no fuso do evento
      $ts_unix = 0;
      if ($active_day_date && $time_local) {
          try {
              $dt = new DateTime(
                  $active_day_date . ' ' . $time_local . ':00',
                  $visit_tz
              );
              $ts_unix = $dt->getTimestamp();
          } catch (Exception $e) {
              $ts_unix = 0;
          }
      }

      // ── IDs únicos ───────────────────────────────────────────
      $item_id       = 'vana-sched-' . ($event_key ?: ($idx . '-' . sanitize_html_class($time_local)));
      $local_time_id = 'vana-ltime-' . ($event_key ?: $idx);

    ?>

      <div
        id="<?php echo esc_attr($item_id); ?>"
        class="vana-schedule-item<?php echo $is_live ? ' vana-schedule-item--live' : ''; ?>"
        role="listitem"
        aria-label="<?php echo esc_attr(
          $time_local . ' — ' . $title_item
          . ($is_live ? (' — ' . ($lang === 'en' ? 'Live now' : 'Ao vivo agora')) : '')
        ); ?>"
        style="<?php echo $is_live
          ? 'border-left: 4px solid #dc2626; background: #fff5f5;'
          : ($is_done ? 'opacity: .75;' : ''); ?>"
      >

        <!-- ── Horário + dual-tz ───────────────────────────── -->
        <div class="vana-schedule-time" aria-hidden="true">

          <div><?php echo esc_html($time_local ?: '—'); ?></div>

          <?php if ($ts_unix > 0): ?>
            <div
              id="<?php echo esc_attr($local_time_id); ?>"
              class="vana-local-time-target"
              data-ts="<?php echo (int) $ts_unix; ?>"
              data-label="<?php echo esc_attr(VANA_Utils::js_i18n('your_time', $lang) ?? ($lang === 'en' ? 'Your time' : 'Seu horário')); ?>"
              style="
                font-size:   0.72rem;
                font-weight: 700;
                color:       var(--vana-muted);
                font-family: monospace;
                margin-top:  2px;
                white-space: nowrap;
              "
              aria-label="<?php echo esc_attr($lang === 'en' ? 'Your local time' : 'Seu horário local'); ?>"
            ></div>
          <?php endif; ?>

        </div>
        <!-- /horário -->

        <!-- ── Conteúdo do item ───────────────────────────── -->
        <div class="vana-schedule-title" style="flex-grow:1; margin: 0 15px;">

          <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">

            <!-- Ícone live pulsante -->
            <?php if ($is_live): ?>
              <span style="
                width:         9px;
                height:        9px;
                border-radius: 50%;
                background:    #dc2626;
                flex-shrink:   0;
                animation:     vana-pulse 1.2s infinite;
              " aria-hidden="true"></span>
            <?php elseif ($is_done): ?>
              <span class="dashicons dashicons-yes-alt"
                    aria-hidden="true"
                    style="color:#16a34a; font-size:16px; width:16px; height:16px;
                           flex-shrink:0;"></span>
            <?php endif; ?>

            <!-- Título -->
            <strong style="font-size: 1.05rem;">
              <?php echo esc_html($title_item ?: ($lang === 'en' ? 'Untitled' : 'Sem título')); ?>
            </strong>

          </div>

          <!-- Descrição (opcional) -->
          <?php if ($desc_item): ?>
            <div style="
              color:       var(--vana-muted);
              font-size:   0.9rem;
              line-height: 1.5;
              margin-top:  4px;
            ">
              <?php echo esc_html($desc_item); ?>
            </div>
          <?php endif; ?>

          <!-- Meta: speaker + location (opcionais) -->
          <?php if ($speaker || $location_it): ?>
            <div style="
              display:     flex;
              gap:         12px;
              flex-wrap:   wrap;
              margin-top:  6px;
              font-size:   0.82rem;
              font-weight: 700;
              color:       var(--vana-muted);
            ">
              <?php if ($speaker): ?>
                <span style="display:inline-flex; align-items:center; gap:4px;">
                  <span class="dashicons dashicons-admin-users"
                        aria-hidden="true"
                        style="font-size:13px; width:13px; height:13px;
                               color:var(--vana-orange);"></span>
                  <?php echo esc_html($speaker); ?>
                </span>
              <?php endif; ?>
              <?php if ($location_it): ?>
                <span style="display:inline-flex; align-items:center; gap:4px;">
                  <span class="dashicons dashicons-location"
                        aria-hidden="true"
                        style="font-size:13px; width:13px; height:13px;
                               color:var(--vana-pink);"></span>
                  <?php echo esc_html($location_it); ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>
        <!-- /conteúdo -->

        <!-- ── Badge de status ───────────────────────────── -->
        <div
          class="vana-schedule-status <?php echo esc_attr($status_css); ?>"
          aria-label="<?php echo esc_attr(
            ($lang === 'en' ? 'Status: ' : 'Status: ') . $status_label
          ); ?>"
        >
          <?php echo esc_html($status_label); ?>
        </div>

      </div>
      <!-- /item -->

    <?php endforeach; ?>

  </div>
  <!-- /schedule-list -->

</section>

<?php if ($live_count > 0): ?>
<style>
@keyframes vana-pulse {
  0%, 100% { opacity: 1;   transform: scale(1);    }
  50%       { opacity: .4; transform: scale(1.35); }
}
</style>
<?php endif; ?>

<!-- Script: ativa a legenda de dual-timezone se o fuso do visitante
     for diferente do fuso do evento -->
<script>
(function () {
  var eventTz   = <?php echo wp_json_encode($visit_tz->getName()); ?>;
  var hint      = document.getElementById('vana-tz-hint');
  if (!hint) return;

  try {
    var localTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (localTz && localTz !== eventTz) {
      hint.style.display = 'block';
    }
  } catch (_) {
    // Browser sem suporte a Intl — omite legenda
  }
})();
</script>
