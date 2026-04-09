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

// ══ FIX: salva o nav antes de sobrescrever $tour ══════════════════════════════
$tour_nav = isset($_t['nav']) && is_array($_t['nav']) ? $_t['nav'] : [];
// ═════════════════════════════════════════════════════════════════════════════

// ========================================================================
// Resolve dados atômicos via Vana_Utils (Fase 2)
// Template compõe os labels locais com os dados atômicos retornados abaixo.
// ========================================================================
$visit_id = get_the_ID();
$lang     = function_exists('vana_get_lang') ? vana_get_lang() : ($lang ?? 'pt');

// Usa $tour_id já resolvido pelo _bootstrap.php (inclui fallback origin_key).
// Só faz get_post_meta como último recurso (evita cache miss no mesmo request).
if ( empty( $tour_id ) ) {
    $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
}

$visit = Vana_Utils::get_visit_identity( $visit_id, $lang );
$tour  = Vana_Utils::get_tour_identity( (int) $tour_id, $lang );

// Reinjeta o nav no novo $tour para o _hero-nav.php
$tour['nav'] = $tour_nav;

// NOTE: do NOT inject `days` into `$tour` here — the Agenda is the source
// of truth for active-day navigation. The hero may still receive `$days`
// from the bootstrap; keep the hero presentation lightweight.

// Composição local — template escolhe o formato
$city         = (string) ($visit['city'] ?? '');
$country_code = (string) ($visit['country_code'] ?? '');
$date_label   = Vana_Utils::visit_date_label($visit_id);
$header_label = (string) ($tour['header_label'] ?? '');
$full_label   = (string) ($tour['full_label'] ?? '');

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
// Delegado a Vana_Utils::visit_counter_label() — chamada única (sem WP_QUERY duplo)
$counter = ($tour_id > 0)
    ? Vana_Utils::visit_counter_label((int) $visit_id, (int) $tour_id, $lang)
    : '';

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
            aria-label="<?php echo esc_attr( vana_t( 'hero.tours', $lang ) ); ?>"
            aria-expanded="false"
            aria-controls="vana-tour-drawer"
            >

                <span class="vana-header__tours-icon" aria-hidden="true">
                    <svg width="18" height="14" viewBox="0 0 18 14" fill="none">
                        <rect width="18" height="2" rx="1" fill="currentColor"/>
                        <rect y="6"  width="18" height="2" rx="1" fill="currentColor"/>
                        <rect y="12" width="12" height="2" rx="1" fill="currentColor"/>
                    </svg>
                </span>
            </button>

        <!-- Centro: Logo + Nome do Site -->
        <div class="vana-header__brand">
            <?php
            $logo_url = '';
            if ( function_exists( 'get_custom_logo' ) ) {
                $logo_id  = get_theme_mod( 'custom_logo' );
                $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
            }
            ?>
            <?php if ( $logo_url ) : ?>
                <img
                    src="<?php echo esc_url( $logo_url ); ?>"
                    alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
                    class="vana-header__logo"
                    width="32"
                    height="32"
                    loading="eager"
                />
            <?php else : ?>
                <span class="vana-header__logo-placeholder" aria-hidden="true">✦</span>
            <?php endif; ?>
            <span class="vana-header__site-name">Vana Madhuryam Daily</span>
        </div>

        <!-- Direita: botão Agenda -->
        <button
            type="button"
            id="vana-agenda-open-btn"
            class="vana-header__agenda-btn"
            data-drawer="vana-agenda-drawer"
            data-vana-agenda-open
            aria-expanded="false"
            aria-controls="vana-agenda-drawer"
            aria-label="<?php echo esc_attr( $lang === 'en' ? 'Schedule' : 'Agenda' ); ?>"
        >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" aria-hidden="true"
                     style="display:block;stroke:#1e293b;flex-shrink:0;">
                    <rect x="3"  y="4"  width="18" height="17" rx="2" stroke="#1e293b"/>
                    <line x1="8"  y1="2"  x2="8"  y2="6"  stroke="#1e293b"/>
                    <line x1="16" y1="2"  x2="16" y2="6"  stroke="#1e293b"/>
                    <line x1="3"  y1="9"  x2="21" y2="9"  stroke="#1e293b"/>
                </svg>
            <span class="vana-header__agenda-label"><?php echo esc_html( $lang === 'en' ? 'Schedule' : 'Agenda' ); ?></span>
        </button>

    </div>
</header>

<!-- ═══════════════════════════════════════════════════════════
     TOUR DRAWER
     ═══════════════════════════════════════════════════════════ -->
<?php require VANA_MC_PATH . 'templates/visit/parts/tour-drawer.php'; ?>
 
<!-- Nota: a gaveta da agenda é incluída centralmente em visit-template.php -->
<!-- ═══════════════════════════════════════════════════════════
     HERO SECTION
     ═══════════════════════════════════════════════════════════ -->
<section
    class="vana-hero <?php echo $bg_image ? 'vana-hero--has-image' : 'vana-hero--gradient'; ?>"
    <?php if ($bg_image): ?>
        style="--vana-hero-bg: url('<?php echo esc_url($bg_image); ?>')"
    <?php endif; ?>
    aria-label="<?php echo esc_attr( $city ?: $header_label ); ?>"
>
    <div class="vana-hero__overlay" aria-hidden="true"></div>

    <div class="vana-hero__content">
    <?php // ── Supertítulo da Tour (acima do h1 da cidade) ── ?>
    <?php if ( $header_label !== '' ) : ?>
        <p class="vana-hero__supertitle">
            <?php if ( ! empty( $tour_url ) ) : ?>
                <a href="<?php echo esc_url( $tour_url ); ?>"
                   class="vana-hero__supertitle-link">
                    <?php echo esc_html( $header_label ); ?>
                </a>
            <?php else : ?>
                <?php echo esc_html( $header_label ); ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

        <!-- Título + região -->
        <div class="vana-hero__heading">
            <?php
            // Fase 2 fix: $_t foi unset; usa dados atômicos já resolvidos acima
            $display_title = ( $city !== '' )
                ? $city
                : (string) get_the_title( (int) $visit_id );
            // $header_label é a fonte canônica (Vana_Utils::get_tour_identity — Fase 2).
            // $header_tour_label do _bootstrap.php §9g é mantido por compatibilidade,
            // mas não é usado neste template desde a Fase 2.
            // TODO Fase 3: remover §9g do _bootstrap.php após validar que nenhum
            // outro template consome $header_tour_label.
            // Compat guard — garante que $header_tour_label não causa notice em partials legados:
            $header_tour_label = $header_tour_label ?? $header_label;
            $hero_city = ( $display_title !== '' )
                ? $display_title
                : ( isset( $visit_city_ref ) && $visit_city_ref !== '' ? $visit_city_ref : '' );
            if ( $hero_city !== '' ): ?>
                <h1 class="vana-hero__title">
                    <?php echo esc_html( $hero_city ); ?>
                </h1>
            <?php endif; ?>
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
        <?php if ( $counter ): ?>
            <p class="vana-hero__visit-counter">
                <?php echo esc_html( $counter ); ?>
            </p>
        <?php endif; ?>

        <!-- Descrição -->
        <?php if ($desc): ?>
            <p class="vana-hero__desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>

        <!-- Botão de abrir Agenda (adicionado)
        <button
            type="button"
            class="vana-hero__agenda-btn"
            data-vana-agenda-open
            aria-haspopup="dialog"
            aria-controls="vana-agenda-drawer"
            aria-label="<?php echo esc_attr( $lang === 'en' ? 'Open Schedule' : 'Abrir Agenda' ); ?>"
        >
            📅 <?php echo esc_html( $lang === 'en' ? 'Schedule' : 'Agenda' ); ?>
        </button> -->

        <?php
        // The Hero only renders the day strip (presentation). Day selection
        // and navigation are handled by the Agenda. Include the day-strip
        // partial which is presentation-only and delegates navigation to the
        // Agenda via `data-action="open-agenda-day"` buttons.
        // NOTE: do not mutate $tour here.
        require VANA_MC_PATH . 'templates/visit/parts/_hero-day-strip.php';
        ?>

        <!-- Inline fallback: ensure day-strip click handler is attached even if external JS is cached. Remove after deploy. -->
        <script>
        (function(){
            if (window.__vana_day_strip_fallback_attached) return;
            window.__vana_day_strip_fallback_attached = true;
            document.addEventListener('click', function(e){
                const pill = e.target.closest('[data-action="open-agenda-day"]');
                if (!pill) return;
                e.preventDefault();
                const dayKey = pill.dataset.dayKey; if (!dayKey) return;
                // update hero pills
                document.querySelectorAll('.vana-day-pill').forEach(p=>{
                    const active = p.dataset.dayKey === dayKey;
                    p.classList.toggle('is-active', active);
                    p.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                // open agenda
                const drawer = document.getElementById('vana-agenda-drawer');
                const overlay = document.getElementById('vana-agenda-overlay');
                const openBtn = document.getElementById('vana-agenda-open-btn');
                if (!drawer) return;
                drawer.removeAttribute('hidden'); overlay?.removeAttribute('hidden');
                document.body.classList.add('vana-drawer-open'); openBtn?.setAttribute('aria-expanded','true');
                // activate tab/panel
                drawer.querySelectorAll('[role="tab"][data-day-key]').forEach(t=>{t.classList.remove('is-active'); t.setAttribute('aria-selected','false');});
                drawer.querySelectorAll('[data-day-panel]').forEach(p=>{p.classList.remove('is-active'); p.setAttribute('hidden','');});
                const targetTab = drawer.querySelector('[role="tab"][data-day-key="'+CSS.escape(dayKey)+'"]');
                const targetPanel = drawer.querySelector('[data-day-panel="'+CSS.escape(dayKey)+'"]');
                if (targetTab){ targetTab.classList.add('is-active'); targetTab.setAttribute('aria-selected','true'); }
                if (targetPanel){ targetPanel.classList.add('is-active'); targetPanel.removeAttribute('hidden'); }
                drawer.querySelector('.vana-drawer__body')?.scrollTo({top:0,behavior:'smooth'});
            });
        })();
        </script>

        <!-- Prev / Next — delega ao partial dedicado -->
        <?php require VANA_MC_PATH . 'templates/visit/parts/_hero-nav.php'; ?>

    </div>
</section>
