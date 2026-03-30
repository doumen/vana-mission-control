<?php
/**
 * Agenda Drawer — Schema 6.1
 * templates/visit/parts/agenda-drawer.php
 *
 * Variáveis consumidas (do _bootstrap.php v3.2):
 *   $days      array  — visit.json["days"]
 *   $index     array  — visit.json["index"]
 *   $lang      string — 'pt' | 'en'
 *   $visit_id  int    — WP post ID da visita
 */
defined( 'ABSPATH' ) || exit;

if ( defined('WP_DEBUG') && WP_DEBUG ) {
    $__ad_days = $tour['days'] ?? $days ?? [];
    error_log('[AGENDA-DEBUG] days count: ' . count($__ad_days));
    error_log('[AGENDA-DEBUG] day[0]: ' . wp_json_encode($__ad_days[0] ?? []));
    error_log('[AGENDA-DEBUG] day[0] day_key: "' . ($__ad_days[0]['day_key'] ?? 'AUSENTE') . '"');
    unset($__ad_days);
}

// ── Guarda entradas ──────────────────────────────────────────────────────────
$agenda_days = is_array( $days ?? null ) ? $days : [];
if ( empty( $agenda_days ) ) return;

$idx_events  = $index['events']  ?? [];
$idx_vods    = $index['vods']    ?? [];
$idx_kathas  = $index['kathas']  ?? [];

$active_day_key = sanitize_text_field(
    $_GET['v_day'] ?? $_GET['day'] ?? ''
);
if ( ! $active_day_key ) {
    $active_day_key = $agenda_days[0]['day_key']
                   ?? $agenda_days[0]['date_local']
                   ?? $agenda_days[0]['date']
                   ?? '';
}

// ── Helpers inline ───────────────────────────────────────────────────────────

/**
 * Label do dia: label_pt/en → fallback formata day_key.
 */
function _vana_agd_day_label( array $day, string $lang ): string {
    $v = $day[ 'label_' . $lang ] ?? $day['label_pt'] ?? '';
    if ( $v !== '' ) return $v;
    $_dk_raw = $day['day_key'] ?? $day['date_local'] ?? $day['date'] ?? '';
    $ts = $_dk_raw ? strtotime( $_dk_raw . ' 12:00:00' ) : 0;
    return $ts ? wp_date( 'd/m', $ts ) : ( $day['day_key'] ?? '—' );
}

/**
 * Badge HTML de status.
 * Schema 6.1: past | active | future | live
 */
function _vana_agd_badge( string $status, string $lang ): string {
    static $map = [
        'live'   => [ 'live',   '🔴', 'Ao Vivo',  'Live'     ],
        'active' => [ 'active', '▶',  'Assistir', 'Watch'    ],
        'past'   => [ 'past',   '✅', 'Assistir', 'Watch'    ],
        'future' => [ 'future', '📅', 'Em breve', 'Upcoming' ],
    ];
    [ $cls, $icon, $lpt, $len ] = $map[ $status ] ?? $map['future'];
    $label = $lang === 'en' ? $len : $lpt;
    return sprintf(
        '<span class="vana-agenda__badge vana-agenda__badge--%s">%s %s</span>',
        esc_attr( $cls ), $icon, esc_html( $label )
    );
}
?>

<!-- ╔══════════════════════════════════════════════════════════════
     AGENDA DRAWER — Schema 6.1
     ╚══════════════════════════════════════════════════════════════ -->
<aside
    id="vana-agenda-drawer"
    class="vana-drawer vana-drawer--agenda"
    data-vana-agenda-drawer
    role="dialog"
    aria-modal="true"
    aria-label="<?php echo esc_attr( $lang === 'en' ? 'Schedule' : 'Agenda' ); ?>"
    hidden
>

    <!-- ── Header ──────────────────────────────────────────── -->
    <div class="vana-drawer__header">
        <span class="vana-drawer__title">
            📅 <?php echo esc_html( $lang === 'en' ? 'Schedule' : 'Agenda' ); ?>
        </span>
        <button
            type="button"
            class="vana-drawer__close"
            data-vana-agenda-close
            aria-label="<?php echo esc_attr( $lang === 'en' ? 'Close' : 'Fechar' ); ?>"
        >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M1 1L13 13M13 1L1 13"
                      stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div class="vana-drawer__body">

        <!-- ── Day Tabs (só se > 1 dia) ───────────────────── -->
        <?php if ( count( $agenda_days ) > 1 ) : ?>
        <nav
            class="vana-agenda__day-nav"
            role="tablist"
            aria-label="<?php echo esc_attr( $lang === 'en' ? 'Days' : 'Dias' ); ?>"
        >
            <?php foreach ( $agenda_days as $i => $day ) :
                $dk = $day['day_key']
                    ?? $day['date_local']
                    ?? $day['date']
                    ?? '';
                $is_act  = ( $dk === $active_day_key );

                // Dot vermelho se algum evento do dia está live
                $has_live = false;
                foreach ( (array)( $day['events'] ?? [] ) as $_ev ) {
                    if ( ( $_ev['status'] ?? '' ) === 'live' ) { $has_live = true; break; }
                }
            ?>
            <button
                type="button"
                role="tab"
                class="vana-agenda__day-tab<?php echo $is_act ? ' is-active' : ''; ?>"
                data-vana-agenda-day="<?php echo esc_attr( $dk ); ?>"
                aria-selected="<?php echo $is_act ? 'true' : 'false'; ?>"
                aria-controls="vana-agenda-panel-<?php echo esc_attr( $i ); ?>"
            >
                <?php echo esc_html( _vana_agd_day_label( $day, $lang ) ); ?>
                <?php if ( $has_live ) : ?>
                    <span class="vana-agenda__day-tab-live" aria-hidden="true"></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <!-- ── Painéis por dia ────────────────────────────── -->
        <?php foreach ( $agenda_days as $i => $day ) :
            $dk      = $day['day_key'] ?? '';
            $is_act  = ( $dk === $active_day_key );
            $events  = is_array( $day['events'] ?? null ) ? $day['events'] : [];

            // Tithi
            $tithi_label = $day[ 'tithi_name_' . $lang ]
                        ?? $day['tithi_name_pt']
                        ?? $day['tithi']
                        ?? '';
        ?>
        <div
            id="vana-agenda-panel-<?php echo esc_attr( $i ); ?>"
            class="vana-agenda__panel<?php echo $is_act ? ' is-active' : ''; ?>"
            role="tabpanel"
            data-vana-agenda-panel="<?php echo esc_attr( $dk ); ?>"
            <?php echo ! $is_act ? 'hidden' : ''; ?>
        >

            <?php if ( $tithi_label ) : ?>
            <p class="vana-agenda__tithi">🌙 <?php echo esc_html( $tithi_label ); ?></p>
            <?php endif; ?>

            <?php if ( empty( $events ) ) : ?>
            <p class="vana-agenda__empty">
                <?php echo esc_html(
                    $lang === 'en' ? 'No events for this day.' : 'Sem eventos para este dia.'
                ); ?>
            </p>

            <?php else : ?>
            <ul class="vana-agenda__event-list" role="list">

                <?php foreach ( $events as $evt ) :
                    if ( ! is_array( $evt ) ) continue;

                    $ev_key    = (string) ( $evt['event_key'] ?? '' );
                    $ev_title  = (string) ( $lang === 'en'
                        ? ( $evt['title_en'] ?? $evt['title_pt'] ?? '' )
                        : ( $evt['title_pt'] ?? $evt['title_en'] ?? '' )
                    );
                    $ev_time   = (string) ( $evt['time']   ?? '' );
                    $ev_status = (string) ( $evt['status'] ?? 'future' );

                    // ── Lookup no index{} ─────────────────────────────
                    $ev_idx     = $idx_events[ $ev_key ] ?? [];
                    $vod_keys   = (array) ( $ev_idx['vods']   ?? [] );
                    $katha_ids  = (array) ( $ev_idx['kathas'] ?? [] );
                    $photo_keys = (array) ( $ev_idx['photos'] ?? [] );

                    $has_vod    = ! empty( $vod_keys );
                    $has_katha  = ! empty( $katha_ids );
                    $has_photos = ! empty( $photo_keys );

                    // ── media_items para data-attr do JS ─────────────────
                    $media_items = [];
                    foreach ( $vod_keys as $vk ) {
                        $vd = $idx_vods[ $vk ] ?? [];
                        if ( empty( $vd ) ) continue;

                        // Título: busca no array inline do evento
                        $v_title = '';
                        foreach ( (array) ( $evt['vods'] ?? [] ) as $_v ) {
                            if ( ( $_v['vod_key'] ?? '' ) === $vk ) {
                                $v_title = $_v[ 'title_' . $lang ]
                                        ?? $_v['title_pt']
                                        ?? '';
                                break;
                            }
                        }

                        $media_items[] = [
                            'vod_key'  => $vk,
                            'video_id' => $vd['video_id']  ?? '',
                            'provider' => $vd['provider']  ?? 'youtube',
                            'vod_part' => (int) ( $vd['vod_part'] ?? 1 ),
                            'title'    => $v_title ?: $ev_title,
                            'thumb'    => $vd['thumb_url'] ?? '',
                        ];
                    }

                    // ── passage_count agregado de todas as kathas ─────
                    $total_passages = 0;
                    foreach ( $katha_ids as $kid ) {
                        $total_passages += (int) (
                            $idx_kathas[ (string) $kid ]['passage_count'] ?? 0
                        );
                    }

                    $is_future = ( $ev_status === 'future' );
                    $interactive = $has_vod || $ev_status === 'live';

                    $li_class = 'vana-agenda__event'
                              . ' vana-agenda__event--' . $ev_status
                              . ( $interactive ? ' is-interactive' : '' );
                ?>

                <li
                    class="<?php echo esc_attr( $li_class ); ?>"
                    data-vana-event-key="<?php echo esc_attr( $ev_key ); ?>"
                    data-vana-day-key="<?php echo esc_attr( $dk ); ?>"
                    data-vana-status="<?php echo esc_attr( $ev_status ); ?>"
                    data-vana-media='<?php echo esc_attr( wp_json_encode( $media_items ) ); ?>'
                    data-vana-visit-id="<?php echo esc_attr( $visit_id ); ?>"
                    role="listitem"
                >

                    <!-- Linha principal -->
                    <div class="vana-agenda__event-header">
                        <span class="vana-agenda__event-time">
                            <?php echo esc_html( $ev_time ?: '—' ); ?>
                        </span>
                        <span class="vana-agenda__event-title">
                            <?php echo esc_html( $ev_title ); ?>
                        </span>
                        <?php echo _vana_agd_badge( $ev_status, $lang ); ?>
                    </div>

                    <!-- VODs -->
                    <?php if ( $has_vod ) : ?>
                    <ul class="vana-agenda__media-list" role="list">
                        <?php foreach ( $media_items as $mi ) :
                            $mi_label = $mi['title'] ?: ( $lang === 'en' ? 'Watch' : 'Assistir' );
                        ?>
                        <li class="vana-agenda__media-item">
                            <button
                                type="button"
                                class="vana-agenda__media-btn"
                                data-vana-play-vod="<?php echo esc_attr( $mi['vod_key'] ); ?>"
                                data-vana-video-id="<?php echo esc_attr( $mi['video_id'] ); ?>"
                                data-vana-provider="<?php echo esc_attr( $mi['provider'] ); ?>"
                                data-vana-event-key="<?php echo esc_attr( $ev_key ); ?>"
                                data-vana-event-title="<?php echo esc_attr( $ev_title ); ?>"
                                data-vana-event-time="<?php echo esc_attr( $ev_time ); ?>"
                                data-vana-day-key="<?php echo esc_attr( $dk ); ?>"
                                aria-label="<?php echo esc_attr( $mi_label ); ?>"
                            >
                                <span class="vana-agenda__media-icon" aria-hidden="true">
                                    <?php echo $mi['provider'] === 'facebook' ? '📘' : '▶'; ?>
                                </span>
                                <span class="vana-agenda__media-label">
                                    <?php echo esc_html( $mi_label ); ?>
                                </span>
                                <?php if ( $mi['vod_part'] > 1 || count( $media_items ) > 1 ) : ?>
                                <span class="vana-agenda__media-part">
                                    pt. <?php echo esc_html( $mi['vod_part'] ); ?>
                                </span>
                                <?php endif; ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <!-- Ações secundárias -->
                    <?php if ( $has_katha || $has_photos || $is_future ) : ?>
                    <div class="vana-agenda__event-actions">

                        <?php if ( $has_katha ) : ?>
                        <button
                            type="button"
                            class="vana-agenda__action vana-agenda__action--hk"
                            data-vana-open-hk="<?php echo esc_attr( $ev_key ); ?>"
                            data-vana-katha-ids="<?php echo esc_attr( implode( ',', $katha_ids ) ); ?>"
                            aria-label="<?php echo esc_attr(
                                $lang === 'en'
                                    ? "Hari-katha ({$total_passages} passages)"
                                    : "Hari-kathā ({$total_passages} trechos)"
                            ); ?>"
                        >
                            📖
                            <?php echo esc_html( $lang === 'en' ? 'Hari-katha' : 'Hari-kathā' ); ?>
                            <?php if ( $total_passages > 0 ) : ?>
                                <span class="vana-agenda__action-count">
                                    <?php echo esc_html( $total_passages ); ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <?php endif; ?>

                        <?php if ( $has_photos ) : ?>
                        <button
                            type="button"
                            class="vana-agenda__action vana-agenda__action--gallery"
                            data-vana-open-gallery="<?php echo esc_attr( $ev_key ); ?>"
                            aria-label="<?php echo esc_attr(
                                $lang === 'en'
                                    ? count( $photo_keys ) . ' photos'
                                    : count( $photo_keys ) . ' fotos'
                            ); ?>"
                        >
                            🖼
                            <span class="vana-agenda__action-count">
                                <?php echo count( $photo_keys ); ?>
                            </span>
                        </button>
                        <?php endif; ?>

                        <?php if ( $is_future ) : ?>
                        <button
                            type="button"
                            class="vana-agenda__action vana-agenda__action--notify"
                            data-vana-notify-event="<?php echo esc_attr( $ev_key ); ?>"
                            aria-label="<?php echo esc_attr(
                                $lang === 'en' ? 'Remind me' : 'Lembrar'
                            ); ?>"
                        >
                            🔔 <?php echo esc_html( $lang === 'en' ? 'Remind me' : 'Lembrar' ); ?>
                        </button>
                        <?php endif; ?>

                    </div>
                    <?php endif; ?>

                </li>

                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

        </div><!-- /panel -->
        <?php endforeach; ?>

        <!-- ── Footer: language switcher ────────────────────────── -->
        <footer class="vana-agenda__footer">
            <div class="vana-agenda__lang" role="group" aria-label="Idioma / Language">
                <button
                    type="button"
                    class="vana-agenda__lang-btn<?php echo $lang === 'pt' ? ' is-active' : ''; ?>"
                    data-vana-lang-switch="pt"
                    aria-pressed="<?php echo $lang === 'pt' ? 'true' : 'false'; ?>"
                >🌐 PT</button>
                <button
                    type="button"
                    class="vana-agenda__lang-btn<?php echo $lang === 'en' ? ' is-active' : ''; ?>"
                    data-vana-lang-switch="en"
                    aria-pressed="<?php echo $lang === 'en' ? 'true' : 'false'; ?>"
                >EN</button>
            </div>
        </footer>

    </div><!-- /body -->
</aside>

<!-- Overlay -->
<div
    class="vana-drawer__overlay"
    id="vana-agenda-overlay"
    data-vana-agenda-overlay
    hidden
></div>
