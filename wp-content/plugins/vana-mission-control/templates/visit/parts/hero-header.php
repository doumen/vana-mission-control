<?php
/**
 * Hero Header — Visit Page
 * Template principal do bloco Hero da página de visitas.
 *
 * Variáveis esperadas (injetadas pelo controller):
 *   $tour   (array)   → dados do tour atual
 *   $lang   (string)  → 'pt' | 'en'
 *
 * Hierarquia de partials:
 *   _hero-badges.php      → badges de região, período, live, novo
 *   _hero-day-selector.php → seletor de dias
 *   _hero-nav.php          → navegação entre visitas
 */
if (!defined('ABSPATH')) exit;

// ─── Segurança ────────────────────────────────────────────────────────────────
if (!isset($tour) || !is_array($tour)) {
    echo '<div class="vana-hero vana-hero--empty">'
       . esc_html(Vana_Utils::t('hero.no_tour', $lang))
       . '</div>';
    return;
}

// ─── Dados base ───────────────────────────────────────────────────────────────
$lang  = isset($lang) && $lang === 'en' ? 'en' : 'pt';
$title = Vana_Utils::pick_i18n_key($tour, 'title', $lang);
$desc  = Vana_Utils::pick_i18n_key($tour, 'description', $lang);

// Prefer city as main heading when available (fallback para $title)
$city = '';
if ( isset( $visit_city_ref ) && trim( (string) $visit_city_ref ) !== '' ) {
    $city = trim( (string) $visit_city_ref );
} elseif ( isset( $data ) && is_array( $data ) && ! empty( $data['location_meta']['city_name'] ) ) {
    $city = (string) $data['location_meta']['city_name'];
}

// Country code (abreviado) — exposto pelo bootstrap
$country_code = isset( $country_code ) ? strtoupper( trim( (string) $country_code ) ) : '';

// Visita counter (Visita X de Y) — tenta derivar via sequência cronológica
$visit_counter_label = '';
$current_visit_id = isset( $visit_id ) ? (int) $visit_id : (int) ( $tour['id'] ?? 0 );
if ( function_exists( 'vana_get_chronological_visits' ) ) {
    $seq = vana_get_chronological_visits();
    if ( is_array( $seq ) && ! empty( $seq ) ) {
        $ids = array_column( $seq, 'id' );
        $idx = array_search( $current_visit_id, $ids, true );
        if ( $idx !== false ) {
            $pos = $idx + 1;
            $total = count( $ids );
            if ( $lang === 'en' ) {
                $visit_counter_label = sprintf( 'Visit %d of %d', $pos, $total );
            } else {
                $visit_counter_label = sprintf( 'Visita %d de %d', $pos, $total );
            }
        }
    }
}

// ─── Mídia ────────────────────────────────────────────────────────────────────
$thumb     = isset($tour['thumbnail']) ? Vana_Utils::safe_https_url((string) $tour['thumbnail']) : '';
$video_url = isset($tour['video_url']) ? Vana_Utils::safe_https_url((string) $tour['video_url']) : '';

// Fallback de imagem: primeira URL de YouTube das days, se não tiver thumb.
if ($thumb === '') {
    foreach ($days as $day) {
        $day_yt_url = (string) ($day['hero']['youtube_url'] ?? '');
        if ($day_yt_url !== '' && preg_match('/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $day_yt_url, $m)) {
            $thumb = 'https://i.ytimg.com/vi/' . esc_attr($m[1]) . '/maxresdefault.jpg';
            break;
        }
    }
}

$has_media = ($thumb !== '' || $video_url !== '');

// ─── Estado do tour ───────────────────────────────────────────────────────────
$days        = isset($tour['days']) && is_array($tour['days']) ? $tour['days'] : [];
$has_days    = count($days) > 0;
$has_title   = ($title !== '');
$is_complete = ($has_title && $has_days);
$state_class = $is_complete ? 'vana-hero--full' : 'vana-hero--incomplete';
?>

<section
    class="vana-hero <?php echo esc_attr($state_class); ?>"
    aria-label="<?php echo esc_attr(Vana_Utils::t('aria.close_hero', $lang)); ?>"
    data-tour-id="<?php echo esc_attr((string) ($tour['id'] ?? '')); ?>"
    data-lang="<?php echo esc_attr($lang); ?>"
>

    <?php if (!$is_complete) : ?>
    <!-- Estado incompleto: aviso editorial -->
    <div class="vana-hero__notice">
        <?php echo esc_html(Vana_Utils::t('hero.incomplete', $lang)); ?>
    </div>
    <?php endif; ?>

    <!-- ── Mídia ─────────────────────────────────────────────────────────── -->
    <?php if ($has_media) : ?>
    <div class="vana-hero__media">

        <?php if ($video_url !== '' && Vana_Utils::is_video_url($video_url)) : ?>
            <?php
            /**
             * Tenta gerar um iframe embed.
             * Se a URL não suportar embed → exibe o fallback de link externo.
             */
            $embed_url = Vana_Utils::maybe_embed_url($video_url);
            ?>
            <?php if ($embed_url) : ?>
            <div class="vana-hero__video-wrapper" aria-label="<?php echo esc_attr(Vana_Utils::t('video_label', $lang)); ?>">
                <iframe
                    src="<?php echo esc_url($embed_url); ?>"
                    frameborder="0"
                    allow="autoplay; encrypted-media"
                    allowfullscreen
                    loading="lazy"
                    title="<?php echo esc_attr($title); ?>"
                ></iframe>
            </div>
            <?php else : ?>
            <!-- Fallback: link externo -->
            <div class="vana-hero__embed-fail">
                <p><?php echo esc_html(Vana_Utils::t('embed_fail_title', $lang)); ?></p>
                <p><?php echo esc_html(Vana_Utils::t('embed_fail_hint',  $lang)); ?></p>
                <a
                    href="<?php echo esc_url($video_url); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="vana-btn vana-btn--secondary"
                >
                    <?php echo esc_html(Vana_Utils::t('watch_link', $lang)); ?>
                </a>
            </div>
            <?php endif; ?>

        <?php elseif ($thumb !== '') : ?>
            <img
                src="<?php echo esc_url($thumb); ?>"
                alt="<?php echo esc_attr($title); ?>"
                class="vana-hero__thumbnail"
                loading="lazy"
            />
        <?php endif; ?>

    </div><!-- /.vana-hero__media -->
    <?php endif; ?>

    <!-- ── Conteúdo ──────────────────────────────────────────────────────── -->
    <div class="vana-hero__content">

        <!-- Badges -->
        <?php include __DIR__ . '/partials/_hero-badges.php'; ?>

        <!-- Título: cidade como prioridade + country badge -->
        <?php if ( $city || $has_title ) : ?>
        <div class="vana-hero__heading">
            <h1 class="vana-hero__title">
                <?php echo esc_html( $city ? $city : $title ); ?>
            </h1>
            <?php if ( $country_code !== '' ) : ?>
                <span class="vana-hero__country-badge" aria-hidden="true">
                    <?php echo esc_html( $country_code ); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ( $visit_counter_label ) : ?>
        <div class="vana-hero__visit-counter">
            <?php echo esc_html( $visit_counter_label ); ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Descrição -->
        <?php if ($desc !== '') : ?>
        <p class="vana-hero__desc">
            <?php echo esc_html($desc); ?>
        </p>
        <?php endif; ?>

        <!-- Seletor de dias -->
        <?php if ($has_days) : ?>
            <?php include __DIR__ . '/partials/_hero-day-selector.php'; ?>
        <?php else : ?>
        <p class="vana-hero__no-days">
            <?php echo esc_html(Vana_Utils::t('day.empty', $lang)); ?>
        </p>
        <?php endif; ?>

    </div><!-- /.vana-hero__content -->

    <!-- ── Navegação entre visitas ───────────────────────────────────────── -->
    <?php include __DIR__ . '/partials/_hero-nav.php'; ?>

</section><!-- /.vana-hero -->
