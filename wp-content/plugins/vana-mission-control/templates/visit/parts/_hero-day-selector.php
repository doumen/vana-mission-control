<?php
/**
 * Partial: Hero Day Selector
 * Seletor de dias da visita — navegação por abas acessíveis.
 *
 * Variáveis herdadas do hero-header.php:
 *   $tour  (array)   → dados do tour (inclui $tour['days'])
 *   $lang  (string)  → 'pt' | 'en'
 *
 * Estrutura esperada de $tour['days']:
 * [
 *   [
 *     'date'   => '2026-03-24',   // ISO 8601
 *     'label'  => ['pt' => 'Dia 1', 'en' => 'Day 1'],  // opcional
 *     'events' => [ ... ]
 *   ],
 *   ...
 * ]
 *
 * Regras (conforme CONTRATO.md § 8):
 *   - Se só há 1 dia → exibe conteúdo direto, sem seletor de abas
 *   - Dia atual (hoje) recebe badge "Hoje"
 *   - Aba ativa = hoje se presente, senão primeiro dia
 *   - Sem eventos → exibe 'day.empty'
 */
if (!defined('ABSPATH')) exit;

// ─── Segurança ────────────────────────────────────────────────────────────────
$days = isset($tour['days']) && is_array($tour['days']) ? $tour['days'] : [];
if (empty($days)) return;

// ─── Data de hoje (fuso do servidor WP) ──────────────────────────────────────
$today = wp_date('Y-m-d');

// ─── Aba ativa: hoje se presente, senão índice 0 ─────────────────────────────
$active_index = 0;
foreach ($days as $i => $day) {
    $date = trim((string) ($day['date'] ?? ''));
    if ($date === $today) {
        $active_index = $i;
        break;
    }
}

// ─── Tour ID para IDs únicos no DOM ──────────────────────────────────────────
$tour_id = esc_attr((string) ($tour['id'] ?? 'tour'));

// ─── Caso especial: apenas 1 dia ─────────────────────────────────────────────
if (count($days) === 1) :
    $day    = $days[0];
    $events = isset($day['events']) && is_array($day['events']) ? $day['events'] : [];
?>
<div class="vana-day-solo" data-tour="<?php echo $tour_id; ?>">
    <?php if (empty($events)) : ?>
        <p class="vana-day__empty">
            <?php echo esc_html(Vana_Utils::t('day.empty', $lang)); ?>
        </p>
    <?php else : ?>
        <?php include __DIR__ . '/_hero-events.php'; ?>
    <?php endif; ?>
</div>
<?php return; endif; ?>

<?php
// ─── Múltiplos dias: seletor de abas ─────────────────────────────────────────
?>
<div
    class="vana-day-selector"
    role="tablist"
    aria-label="<?php echo esc_attr(Vana_Utils::t('aria.day_selector', $lang)); ?>"
    data-tour="<?php echo $tour_id; ?>"
>

    <?php // ── Abas (triggers) ──────────────────────────────────────────── ?>
    <div class="vana-day-selector__tabs" role="presentation">
    <?php foreach ($days as $i => $day) :
        $date      = trim((string) ($day['date'] ?? ''));
        $is_active = ($i === $active_index);
        $is_today  = ($date === $today);
        $panel_id  = "vana-day-panel-{$tour_id}-{$i}";
        $tab_id    = "vana-day-tab-{$tour_id}-{$i}";

        // Label do dia
        if (isset($day['label'])) {
            $label = Vana_Utils::pick_i18n($day['label'], $lang);
        } else {
            // Formata a data no locale do WP
            $label = ($date !== '')
                ? wp_date(get_option('date_format'), strtotime($date))
                : ($lang === 'en' ? 'Day ' . ($i + 1) : 'Dia ' . ($i + 1));
        }
    ?>
        <button
            id="<?php echo esc_attr($tab_id); ?>"
            class="vana-day-selector__tab<?php echo $is_active ? ' vana-day-selector__tab--active' : ''; ?>"
            role="tab"
            aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
            aria-controls="<?php echo esc_attr($panel_id); ?>"
            data-date="<?php echo esc_attr($date); ?>"
            data-day-key="<?php echo esc_attr( $day['day_key'] ?? $date ); ?>"
            data-index="<?php echo esc_attr((string) $i); ?>"
            <?php echo !$is_active ? 'tabindex="-1"' : ''; ?>
        >
            <span class="vana-day-selector__tab-label">
                <?php echo esc_html($label); ?>
            </span>
            <?php if ($is_today) : ?>
            <span
                class="vana-badge vana-badge--today"
                aria-label="<?php echo esc_attr(Vana_Utils::t('day.today', $lang)); ?>"
            >
                <?php echo esc_html(Vana_Utils::t('day.today', $lang)); ?>
            </span>
            <?php endif; ?>
        </button>
    <?php endforeach; ?>
    </div><!-- /.vana-day-selector__tabs -->

    <?php // ── Painéis (conteúdo) ───────────────────────────────────────── ?>
    <?php foreach ($days as $i => $day) :
        $is_active = ($i === $active_index);
        $panel_id  = "vana-day-panel-{$tour_id}-{$i}";
        $tab_id    = "vana-day-tab-{$tour_id}-{$i}";
        $events    = isset($day['events']) && is_array($day['events']) ? $day['events'] : [];
    ?>
    <div
        id="<?php echo esc_attr($panel_id); ?>"
        class="vana-day-selector__panel<?php echo $is_active ? ' vana-day-selector__panel--active' : ''; ?>"
        role="tabpanel"
        aria-labelledby="<?php echo esc_attr($tab_id); ?>"
        <?php echo !$is_active ? 'hidden' : ''; ?>
    >
        <?php if (empty($events)) : ?>
            <p class="vana-day__empty">
                <?php echo esc_html(Vana_Utils::t('day.empty', $lang)); ?>
            </p>
        <?php else : ?>
            <?php include __DIR__ . '/_hero-events.php'; ?>
        <?php endif; ?>
    </div><!-- /.vana-day-selector__panel -->
    <?php endforeach; ?>

</div><!-- /.vana-day-selector -->
