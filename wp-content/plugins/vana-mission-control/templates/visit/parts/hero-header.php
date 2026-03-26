<?php
/**
 * Hero Header — Vana Madhuryam Daily
 * Template Part: templates/visit/parts/hero-header.php
 * v3C — consome $tour (montado pelo _bootstrap.php §9e)
 *
 * Variáveis consumidas (exportadas por _bootstrap.php):
 *   $tour             array   — view-model completa (§9e)
 *   $lang             string  — 'pt' | 'en'
 *   $visit_id         int
 *   $active_day       array
 *   $data             array   — alias de $timeline (fallback desc/cover)
 */
defined('ABSPATH') || exit;

// ── 1. Extração segura de $tour ───────────────────────────────────────────────
$_t = is_array($tour ?? null) ? $tour : [];

// Descrição
$desc = Vana_Utils::pick_i18n_key($_t, 'description', $lang);

// Cidade (city_ref do location_meta)
$city = trim((string)($visit_city_ref ?? $data['location_meta']['city_ref'] ?? ''));

// Título do header central (canônico): cidade > meta i18n > post title
$display_title = Vana_Utils::resolve_visit_title($_t, $lang, $visit_id ?? 0, $city);

// ── 2. Background image ───────────────────────────────────────────────────────
$bg_image = '';
$_thumb   = (string)($_t['thumbnail'] ?? '');
if ($_thumb !== '') {
    $bg_image = esc_url($_thumb);
} elseif (!empty($_t['video_url'])) {
    if (preg_match(
        '/(?:v=|\/embed\/|\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/',
        $_t['video_url'], $_m
    )) {
        $bg_image = 'https://i.ytimg.com/vi/' . esc_attr($_m[1]) . '/maxresdefault.jpg';
    }
}

// ── 3. Badges ─────────────────────────────────────────────────────────────────
$region_code  = (string)($_t['region_code'] ?? '');   // ex: BR, IN
$season_code  = (string)($_t['season_code'] ?? '');   // ex: INDIA_2026
$has_live     = !empty($_t['has_live']);
$is_new       = !empty($_t['is_new']);

// Badge "NOVO" — aparece se created_at < 30 dias
if (!$is_new && !empty($_t['created_at'])) {
    $is_new = (time() - strtotime($_t['created_at'])) < (30 * DAY_IN_SECONDS);
}

// ── 4. Tour Counter — Visita X de Y ──────────────────────────────────────────
$tour_counter_label = '';
if (!empty($tour_id) && $tour_id > 0) {
    $tour_visits = get_posts([
        'post_type'      => 'vana_visit',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_vana_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [[
            'key'     => '_vana_tour_id',
            'value'   => $tour_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ]],
    ]);

    if (!empty($tour_visits)) {
        $position = array_search($visit_id, $tour_visits, true);
        if ($position !== false) {
            $tour_counter_label = sprintf(
                $lang === 'en' ? 'Visit %d of %d' : 'Visita %d de %d',
                $position + 1,
                count($tour_visits)
            );
        }
    }
}

// ── 5. Day label ativo ────────────────────────────────────────────────────────
$active_label = (string)(
    $active_day['label_' . $lang]
    ?? $active_day['label_pt']
    ?? ''
);

// ── 6. Lang toggle ────────────────────────────────────────────────────────────
$lang_alt = $lang === 'pt' ? 'en' : 'pt';
$lang_url  = add_query_arg('lang', $lang_alt);

// ── 7. Limpeza ────────────────────────────────────────────────────────────────
unset($_t, $_thumb, $_m);
?>

<!-- ═══════════════════════════════════════════════════════════
     HEADER FIXO CONTEXTUAL
     ═══════════════════════════════════════════════════════════ -->
<header class="vana-header" role="banner">
    <div class="vana-header__inner">

        <!-- Esquerda: botão Tours -->
        <button
            class="vana-header__tours-btn"
            data-drawer="vana-tour-drawer"
            aria-label="<?php echo esc_attr(vana_t('hero.tours', $lang)); ?>"
            aria-expanded="false"
            aria-controls="vana-tour-drawer"
        >
            <span class="vana-header__tours-icon" aria-hidden="true">
                <svg width="18" height="14" viewBox="0 0 18 14" fill="none">
                    <rect width="18" height="2" rx="1" fill="currentColor"/>
                    <rect y="6" width="18" height="2" rx="1" fill="currentColor"/>
                    <rect y="12" width="12" height="2" rx="1" fill="currentColor"/>
                </svg>
            </span>
            <span class="vana-header__tours-label">
                <?php echo esc_html(vana_t('hero.tours', $lang)); ?>
            </span>
        </button>

        <!-- Centro: label da tour (spec: REGIÃO · ESTAÇÃO · ANO) -->
        <div class="vana-header__context">
            <?php if ( $header_tour_label !== '' ): ?>
                <span class="vana-header__title">
                    <?php echo esc_html( $header_tour_label ); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Direita: agenda + notificações + idioma -->
                <!-- Direita: só gaveta agenda (🔔 e 🌐 migram para dentro da gaveta) -->
        <div class="vana-header__actions">
            <button
                type="button"
                class="vana-header__agenda-btn"
                id="vana-agenda-open-btn"
                data-vana-agenda-open
                aria-expanded="false"
                aria-controls="vana-agenda-drawer"
                aria-label="<?php echo esc_attr( vana_t( 'agenda.title', $lang ) ?: 'Agenda' ); ?>"
                title="<?php echo esc_attr( vana_t( 'agenda.title', $lang ) ?: 'Agenda' ); ?>"
            >
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="17" rx="2"/>
                    <line x1="8"  y1="2" x2="8"  y2="6"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="3"  y1="9" x2="21" y2="9"/>
                    <polyline points="8 15 12 11 16 15"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════════
     TOUR DRAWER
     ═══════════════════════════════════════════════════════════ -->
<?php require VANA_MC_PATH . 'templates/visit/parts/tour-drawer.php'; ?>

<!-- ═══════════════════════════════════════════════════════════
     HERO SECTION
     ═══════════════════════════════════════════════════════════ -->
<section
    class="vana-hero <?php echo $bg_image ? 'vana-hero--has-image' : 'vana-hero--gradient'; ?>"
    <?php if ($bg_image): ?>
        style="--vana-hero-bg: url('<?php echo esc_url($bg_image); ?>')"
    <?php endif; ?>
    aria-label="<?php echo esc_attr($title); ?>"
>
    <div class="vana-hero__overlay" aria-hidden="true"></div>

    <div class="vana-hero__content">

        <!-- Breadcrumb -->
        <p class="vana-hero__badge"
           aria-label="<?php echo esc_attr(vana_t('hero.breadcrumb', $lang)); ?>">
            <?php echo esc_html(vana_t('hero.breadcrumb', $lang)); ?>
        </p>

        <!-- Título + região -->
        <!-- Título: cidade como H1 + badge país (spec 3.1) -->
        <div class="vana-hero__heading">
            <h1 class="vana-hero__title">
                <?php
                // Fonte 1: city_ref do location_meta (canônico — spec)
                // Fonte 2: display_title (fallback — dados legados)
                $hero_city = ( isset( $visit_city_ref ) && $visit_city_ref !== '' )
                    ? $visit_city_ref
                    : $display_title;
                echo esc_html( $hero_city );
                ?>
            </h1>
            <?php if ( $country_code !== '' ): ?>
                <span
                    class="vana-hero__country-badge"
                    data-country="<?php echo esc_attr( strtolower( $country_code ) ); ?>"
                    title="<?php echo esc_attr( $country_code ); ?>"
                    aria-label="<?php echo esc_attr( $country_code ); ?>"
                >
                    <?php echo esc_html( strtoupper( $country_code ) ); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Badges: LIVE · NEW · SEASON -->
        <?php if ($has_live || $is_new || $season_code !== ''): ?>
        <div class="vana-hero__badges">
            <?php if ($has_live): ?>
                <span class="vana-hero__badge-chip vana-hero__badge-chip--live">
                    🔴 <?php echo esc_html(vana_t('hero.badge_live', $lang) ?: 'AO VIVO'); ?>
                </span>
            <?php endif; ?>
            <?php if ($is_new): ?>
                <span class="vana-hero__badge-chip vana-hero__badge-chip--new">
                    ✨ <?php echo esc_html(vana_t('hero.badge_new', $lang) ?: 'NOVO'); ?>
                </span>
            <?php endif; ?>
            <?php if ($season_code !== ''): ?>
                <span class="vana-hero__badge-chip vana-hero__badge-chip--season">
                    <?php echo esc_html($season_code); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Ordinal da tour (spec 3.2: "Visita X de Y" fica no Hero) -->
        <?php if ( $tour_counter_label ): ?>
            <p class="vana-hero__visit-counter">
                <?php echo esc_html( $tour_counter_label ); ?>
            </p>
        <?php endif; ?>

        <!-- Descrição -->
        <?php if ($desc): ?>
            <p class="vana-hero__desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>

        <!-- Day Selector (multi-dia) -->
        <?php
        $days_count  = count($tour['days'] ?? []);
        if ($days_count > 1):
            $days_by_month = [];
            foreach (($tour['days'] ?? []) as $day) {
                $ts = strtotime($day['date_local'] ?? $day['date'] ?? '');
                if ($ts === false) continue;
                $month_key = wp_date('Y-m', $ts);  // ← usa wp_date (timezone correto)
                $days_by_month[$month_key][] = $day;
            }
        ?>
        <nav class="vana-hero__day-selector"
             aria-label="<?php echo esc_attr(vana_t('aria.day_selector', $lang) ?: 'Seletor de dias'); ?>">
            <?php foreach ($days_by_month as $month_key => $month_days): ?>
                <?php if (count($days_by_month) > 1): ?>
                    <span class="vana-hero__day-selector-month">
                        <?php echo esc_html(wp_date('M/Y', strtotime($month_key))); ?>
                    </span>
                <?php endif; ?>
                <div class="vana-hero__day-selector-group">
                    <?php foreach ($month_days as $day): ?>
                        <?php
                        $day_date  = $day['date_local'] ?? $day['date'] ?? '';
                        $day_label = $day['label_' . $lang] ?? $day['label_pt'] ?? '';
                        $is_active = $active_day && ($active_day['date_local'] ?? $active_day['date'] ?? '') === $day_date;
                        ?>
                        <button
                            class="vana-hero__day-btn <?php echo $is_active ? 'vana-hero__day-btn--active' : ''; ?>"
                            data-day-date="<?php echo esc_attr($day_date); ?>"
                            aria-label="<?php echo esc_attr($day_label); ?>"
                            <?php echo $is_active ? 'aria-current="date"' : ''; ?>
                        >
                            <?php echo esc_html($day_label); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <!-- Prev / Next — delega ao partial dedicado -->
        <?php require VANA_MC_PATH . 'templates/visit/parts/_hero-nav.php'; ?>

    </div>
</section>
