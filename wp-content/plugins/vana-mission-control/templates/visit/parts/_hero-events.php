<?php
/**
 * Partial: Hero Events
 * Lista de eventos de um único dia da visita.
 *
 * Variáveis herdadas do _hero-day-selector.php:
 *   $events  (array)   → lista de eventos do dia ativo
 *   $lang    (string)  → 'pt' | 'en'
 *   $tour    (array)   → dados do tour (para contexto, se necessário)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( empty( $events ) || ! is_array( $events ) ) {
    echo '<p class="vana-day__empty">'
       . esc_html( Vana_Utils::t( 'day.empty', $lang ) )
       . '</p>';
    return;
}
?>

<ol class="vana-events" role="list">

<?php foreach ( $events as $event ) :
    if ( ! is_array( $event ) ) continue;

    $event_key  = (string) ( $event['key']  ?? $event['type'] ?? '' );
    $event_type = (string) ( $event['type'] ?? '' );
    $time_local = (string) ( $event['time_local'] ?? $event['time'] ?? '' );
    $duration   = isset( $event['duration'] ) ? (int) $event['duration'] : 0;
    $tags       = isset( $event['tags'] ) && is_array( $event['tags'] )
                  ? $event['tags'] : [];

    if ( isset( $event['title'] ) && is_array( $event['title'] ) ) {
        $title = Vana_Utils::pick_i18n( $event['title'], $lang );
    } elseif ( isset( $event['title'] ) ) {
        $title = (string) $event['title'];
    } else {
        $title = Vana_Utils::t( 'event.type.' . $event_type, $lang );
        if ( $title === 'event.type.' . $event_type ) {
            $title = ucfirst( $event_type );
        }
    }

    $desc = '';
    if ( isset( $event['desc'] ) && is_array( $event['desc'] ) ) {
        $desc = Vana_Utils::pick_i18n( $event['desc'], $lang );
    } elseif ( isset( $event['desc'] ) ) {
        $desc = (string) $event['desc'];
    }

    $media     = isset( $event['media'] ) && is_array( $event['media'] )
                 ? $event['media'] : [];
    $yt_url    = isset( $media['youtube_url'] )
                 ? Vana_Utils::safe_https_url( (string) $media['youtube_url'] ) : '';
    $img_url   = isset( $media['image_url'] )
                 ? Vana_Utils::safe_https_url( (string) $media['image_url'] ) : '';
    $embed_url = $yt_url !== '' ? Vana_Utils::maybe_embed_url( $yt_url ) : '';

    $is_live     = in_array( 'live',     $tags, true );
    $is_featured = in_array( 'featured', $tags, true );

    $item_classes = implode( ' ', array_filter( [
        'vana-event',
        $event_type !== '' ? 'vana-event--' . sanitize_html_class( $event_type ) : '',
        $is_live            ? 'vana-event--live'     : '',
        $is_featured        ? 'vana-event--featured' : '',
    ] ) );
?>

    <li
        class="<?php echo esc_attr( $item_classes ); ?>"
        data-event-key="<?php echo esc_attr( $event_key ); ?>"
        data-event-type="<?php echo esc_attr( $event_type ); ?>"
        role="listitem"
    >
        <?php if ( $time_local !== '' ) : ?>
        <time
            class="vana-event__time"
            datetime="<?php echo esc_attr( $time_local ); ?>"
            aria-label="<?php echo esc_attr( Vana_Utils::t( 'event.time_aria', $lang ) . ' ' . $time_local ); ?>"
        >
            <?php echo esc_html( $time_local ); ?>
            <?php if ( $duration > 0 ) : ?>
            <span class="vana-event__duration" aria-hidden="true">
                · <?php echo esc_html( $duration . ' min' ); ?>
            </span>
            <?php endif; ?>
        </time>
        <?php endif; ?>

        <div class="vana-event__header">
            <h3 class="vana-event__title"><?php echo esc_html( $title ); ?></h3>
            <?php if ( $is_live || $is_featured ) : ?>
            <div class="vana-event__badges" aria-hidden="true">
                <?php if ( $is_live ) : ?>
                <span class="vana-badge vana-badge--live vana-badge--sm">
                    <span class="vana-badge__dot"></span>
                    <?php echo esc_html( Vana_Utils::t( 'badge.live', $lang ) ); ?>
                </span>
                <?php endif; ?>
                <?php if ( $is_featured ) : ?>
                <span class="vana-badge vana-badge--featured vana-badge--sm">
                    ⭐ <?php echo esc_html( Vana_Utils::t( 'badge.featured', $lang ) ); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( $desc !== '' ) : ?>
        <p class="vana-event__desc"><?php echo esc_html( $desc ); ?></p>
        <?php endif; ?>

        <?php if ( $embed_url !== '' ) : ?>
        <div class="vana-event__video" aria-label="<?php echo esc_attr( $title ); ?>">
            <div class="vana-event__video-wrapper">
                <iframe
                    src="<?php echo esc_url( $embed_url ); ?>"
                    frameborder="0"
                    allow="autoplay; encrypted-media"
                    allowfullscreen
                    loading="lazy"
                    title="<?php echo esc_attr( $title ); ?>"
                ></iframe>
            </div>
        </div>
        <?php elseif ( $img_url !== '' ) : ?>
        <figure class="vana-event__figure">
            <img
                src="<?php echo esc_url( $img_url ); ?>"
                alt="<?php echo esc_attr( $title ); ?>"
                class="vana-event__image"
                loading="lazy"
            />
        </figure>
        <?php endif; ?>

    </li>

<?php endforeach; ?>

</ol>
