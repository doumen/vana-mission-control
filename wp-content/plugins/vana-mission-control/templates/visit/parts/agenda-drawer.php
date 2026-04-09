<?php
/**
 * Gaveta Direita — Agenda de Eventos
 * Entrega 4 · Schema 6.1 — patch PT/EN + anti-Astra
 */
defined( 'ABSPATH' ) || exit;

// Guard contra dupla inclusão do partial — evita redeclare fatal
if ( defined( 'VANA_AGENDA_DRAWER_LOADED' ) ) {
    return;
}
define( 'VANA_AGENDA_DRAWER_LOADED', true );

$is_en = ( $lang === 'en' );

if ( ! function_exists( 'vana_fmt_ts' ) ) :
function vana_fmt_ts( int $ts ): string {
    return sprintf( '%02d:%02d:%02d',
        intdiv( $ts, 3600 ),
        intdiv( $ts % 3600, 60 ),
        $ts % 60
    );
}
endif;

if ( ! function_exists( 'vana_segment_icon' ) ) :
function vana_segment_icon( string $type ): string {
    $map = [
        'kirtan'       => '🎵',
        'harikatha'    => '📖',
        'pushpanjali'  => '🌸',
        'arati'        => '🪔',
        'dance'        => '💃',
        'drama'        => '🎭',
        'darshan'      => '🙏',
        'interval'     => '☕',
        'announcement' => '📢',
        'noise'        => '🔇',
    ];
    return $map[ $type ] ?? '▸';
}
endif;

if ( ! function_exists( 'vana_event_type_label' ) ) :
function vana_event_type_label( string $type, bool $is_en ): string {
    $map = [
        'mangala'  => [ 'pt' => 'Maṅgala-ārati', 'en' => 'Maṅgala-ārati' ],
        'programa' => [ 'pt' => 'Programa',        'en' => 'Program'        ],
        'hk'       => [ 'pt' => 'Hari-Kathā',      'en' => 'Hari-Kathā'     ],
        'arati'    => [ 'pt' => 'Ārati',            'en' => 'Ārati'          ],
        'passeio'  => [ 'pt' => 'Passeio',          'en' => 'Tour'           ],
    ];
    $entry = $map[ $type ] ?? [ 'pt' => ucfirst( $type ), 'en' => ucfirst( $type ) ];
    return $is_en ? $entry['en'] : $entry['pt'];
}
endif;

// URL para troca de idioma
$lang_alt = $is_en ? 'pt' : 'en';
$lang_url  = add_query_arg( 'lang', $lang_alt );
?>

<div id="vana-agenda-drawer"
     class="vana-drawer vana-drawer--agenda"
     role="dialog"
     aria-modal="true"
     aria-label="<?php echo esc_attr( $is_en ? 'Schedule' : 'Agenda' ); ?>"
     hidden>

    <!-- ── HEADER ──────────────────────────────────────────────── -->
    <div class="vana-drawer__header">

        <span class="vana-drawer__title">
            📅 <?php echo $is_en ? 'Schedule' : 'Agenda'; ?>
        </span>

        <!-- ── PT / EN toggle ────────────────────────────────── -->
        <div class="vana-drawer__lang" role="group" aria-label="Idioma / Language">
            <button
                type="button"
                class="vana-lang-btn <?php echo ! $is_en ? 'is-active' : ''; ?>"
                data-vana-lang="pt"
                aria-pressed="<?php echo ! $is_en ? 'true' : 'false'; ?>"
            >PT</button>
            <button
                type="button"
                class="vana-lang-btn <?php echo $is_en ? 'is-active' : ''; ?>"
                data-vana-lang="en"
                aria-pressed="<?php echo $is_en ? 'true' : 'false'; ?>"
            >EN</button>
        </div>

        <button type="button"
                class="vana-drawer__close"
                data-vana-agenda-close
                aria-label="<?php echo esc_attr( $is_en ? 'Close' : 'Fechar' ); ?>">
            ✕
        </button>
    </div>

    <!-- ── DAY TABS ────────────────────────────────────────────── -->
    <div class="vana-agenda-day-tabs" role="tablist">
        <?php foreach ( $days as $i => $day ) :
            $day_key = $day['day_key'] ?? '';
            $label   = $is_en
                ? ( $day['label_en'] ?? $day_key )
                : ( $day['label_pt'] ?? $day_key );
        ?>
        <button class="vana-day-tab <?php echo $i === 0 ? 'is-active' : ''; ?>"
                role="tab"
                data-day-key="<?php echo esc_attr( $day_key ); ?>"
                aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>">
            <?php echo esc_html( $label ); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ── BODY ────────────────────────────────────────────────── -->
    <div class="vana-drawer__body">

        <?php foreach ( $days as $i => $day ) :
            $day_key    = $day['day_key'] ?? '';
            $tithi_name = $is_en
                ? ( $day['tithi_name_en'] ?? '' )
                : ( $day['tithi_name_pt'] ?? '' );
            $events     = $day['events'] ?? [];
        ?>

        <div class="vana-day-panel <?php echo $i === 0 ? 'is-active' : ''; ?>"
             data-day-panel="<?php echo esc_attr( $day_key ); ?>"
             role="tabpanel"
             <?php echo $i !== 0 ? 'hidden' : ''; ?>>

            <!-- Tithi -->
            <?php if ( $tithi_name ) : ?>
            <div class="vana-tithi">
                🌙 <?php echo esc_html( $tithi_name ); ?>
            </div>
            <?php endif; ?>

            <!-- Lista de eventos -->
            <ol class="vana-event-list" role="list">

                <?php foreach ( $events as $ei => $event ) :
                    $event_key   = $event['event_key'] ?? '';
                    $event_type  = $event['type']      ?? 'programa';
                    $event_time  = $event['time']      ?? '';
                    $event_title = $is_en
                        ? ( $event['title_en'] ?? $event['title_pt'] ?? '' )
                        : ( $event['title_pt'] ?? '' );
                        $status = $event['status'] ?? 'past';
                        $vods        = $event['vods']   ?? [];

                        // Schema 6.2: Hari-Kathā is referenced via vod->segments
                        $hk_segment  = null;
                        $hk_katha_id = null;
                        foreach ( $vods as $vod ) {
                            foreach ( $vod['segments'] ?? [] as $seg ) {
                                if ( ( $seg['type'] ?? '' ) === 'harikatha' && ! empty( $seg['katha_id'] ) ) {
                                    $hk_segment  = $seg;
                                    $hk_katha_id = (int) $seg['katha_id'];
                                    $hk_segment['_vod_key']  = $vod['vod_key']  ?? '';
                                    $hk_segment['_video_id'] = $vod['video_id'] ?? '';
                                    $hk_segment['_provider'] = $vod['provider'] ?? 'youtube';
                                    break 2;
                                }
                            }
                        }

                        $photos      = $event['photos'] ?? [];
                    $sangha      = $event['sangha'] ?? [];

                    $status_icon = match( $status ) {
                        'live'  => '<span class="vana-badge vana-badge--live">🔴 ' . ( $is_en ? 'LIVE' : 'AO VIVO' ) . '</span>',
                        'soon'  => '<span class="vana-badge vana-badge--soon">🕐 ' . ( $is_en ? 'Soon' : 'Em breve' ) . '</span>',
                        default => '',
                    };

                    $event_id = 'vana-event-' . sanitize_html_class( $event_key );
                    $is_first = ( $ei === 0 && $i === 0 );
                ?>

                <li class="vana-event-item <?php echo $is_first ? 'is-expanded' : ''; ?>"
                    data-event-key="<?php echo esc_attr( $event_key ); ?>"
                    data-event-type="<?php echo esc_attr( $event_type ); ?>">

                    <!-- Cabeçalho do evento (toggle) -->
                    <button class="vana-event-toggle"
                            aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo esc_attr( $event_id ); ?>">

                        <span class="vana-event-toggle__time">
                            🕐 <?php echo esc_html( $event_time ); ?>
                        </span>

                        <span class="vana-event-toggle__title">
                            <?php echo esc_html( $event_title ); ?>
                            <?php echo $status_icon; ?>
                        </span>

                        <span class="vana-event-toggle__icon" aria-hidden="true">
                            <?php echo $is_first ? '−' : '+'; ?>
                        </span>
                    </button>

                    <!-- Corpo do evento (expandível) -->
                    <div id="<?php echo esc_attr( $event_id ); ?>"
                         class="vana-event-body"
                         <?php echo $is_first ? '' : 'hidden'; ?>>

                        <!-- VODs -->
                        <?php if ( ! empty( $vods ) ) : ?>
                        <div class="vana-event-section vana-event-section--vods">
                            <div class="vana-event-section__label">
                                🎬 <?php echo $is_en ? 'Videos' : 'Vídeos'; ?>
                            </div>
                            <div class="vana-vod-chips">
                                <?php foreach ( $vods as $vod ) :
                                    $vod_key   = $vod['vod_key']  ?? '';
                                    $vod_part  = (int) ( $vod['vod_part'] ?? 1 );
                                    $video_id  = $vod['video_id'] ?? '';
                                    $provider  = $vod['provider'] ?? 'youtube';
                                    $vod_title = $is_en
                                        ? ( $vod['title_en'] ?? $vod['title_pt'] ?? '' )
                                        : ( $vod['title_pt'] ?? '' );
                                    $duration  = (int) ( $vod['duration_s'] ?? 0 );
                                    $dur_label = $duration ? vana_fmt_ts( $duration ) : '';
                                ?>
                                <button class="vana-vod-chip"
                                        data-action="load-vod"
                                        data-vod-key="<?php echo esc_attr( $vod_key ); ?>"
                                        data-video-id="<?php echo esc_attr( $video_id ); ?>"
                                        data-provider="<?php echo esc_attr( $provider ); ?>"
                                        data-timestamp="0"
                                        title="<?php echo esc_attr( $vod_title ); ?>">
                                    ▶ Pt.<?php echo $vod_part; ?>
                                    <?php if ( $dur_label ) : ?>
                                    <span class="vana-vod-chip__dur"><?php echo esc_html( $dur_label ); ?></span>
                                    <?php endif; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- KATHAS — Schema 6.2: derived from vod->segments.harikatha -->
                        <?php if ( $hk_segment && $hk_katha_id ) :

                            $hk_title    = $is_en
                                ? ( $hk_segment['title_en'] ?? $hk_segment['title_pt'] ?? '' )
                                : ( $hk_segment['title_pt'] ?? '' );

                            $katha_index = $index['kathas'][ $hk_katha_id ] ?? [];
                            $scripture   = $katha_index['scripture'] ?? '';
                            $passages    = $katha_index['passages'] ?? [];
                        ?>

                        <div class="vana-event-section vana-event-section--hk"
                             data-katha-id="<?php echo esc_attr( $hk_katha_id ); ?>"
                             data-katha-loaded="<?php echo empty( $passages ) ? 'false' : 'true'; ?>">

                            <div class="vana-event-section__label">
                                📖 <?php echo esc_html( $hk_title ?: ( $is_en ? 'Hari-Kathā' : 'Hari-Kathā' ) ); ?>
                                <?php if ( $scripture ) : ?>
                                    <span class="vana-hk-scripture">· <?php echo esc_html( $scripture ); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ( ! empty( $passages ) ) : ?>
                                <ol class="vana-passage-list" role="list">
                                    <?php foreach ( $passages as $pi => $passage ) :
                                        $pid       = $passage['passage_id'] ?? $passage['passage_key'] ?? '';
                                        $p_title   = $is_en
                                            ? ( $passage['title_en'] ?? $passage['title_pt'] ?? $pid )
                                            : ( $passage['title_pt'] ?? $pid );
                                        $hook      = $passage['hook'] ?? '';
                                        $teaching  = $is_en
                                            ? ( $passage['teaching_en'] ?? $passage['teaching_pt'] ?? '' )
                                            : ( $passage['teaching_pt'] ?? '' );
                                        $key_quote = $passage['key_quote'] ?? '';

                                        $p_vod_key  = $passage['source_ref']['vod_key'] ?? $hk_segment['_vod_key'] ?? '';
                                        $p_ts_start = (int)( $passage['source_ref']['timestamp_start'] ?? $hk_segment['timestamp_start'] ?? 0 );
                                        $p_video_id = $index['vods'][$p_vod_key]['video_id'] ?? $hk_segment['_video_id'] ?? '';
                                        $p_provider = $index['vods'][$p_vod_key]['provider'] ?? $hk_segment['_provider'] ?? 'youtube';
                                        $p_ts_label = $p_ts_start ? vana_fmt_ts( $p_ts_start ) : '';
                                        $p_item_id  = 'vana-p-' . sanitize_html_class( $pid );
                                    ?>
                                    <li class="vana-passage-item" data-passage-id="<?php echo esc_attr( $pid ); ?>">

                                        <button class="vana-passage-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $p_item_id ); ?>">
                                            <span class="vana-passage-toggle__num"><?php echo $pi + 1; ?></span>
                                            <span class="vana-passage-toggle__title">
                                                <?php if ( $hook ) : ?>
                                                    <em class="vana-passage-hook"><?php echo esc_html( $hook ); ?></em>
                                                <?php endif; ?>
                                                <?php echo esc_html( $p_title ); ?>
                                            </span>
                                            <span class="vana-passage-toggle__icon" aria-hidden="true">+</span>
                                        </button>

                                        <div id="<?php echo esc_attr( $p_item_id ); ?>" class="vana-passage-body" hidden>
                                            <?php if ( $key_quote ) : ?>
                                                <blockquote class="vana-passage-quote"><?php echo esc_html( $key_quote ); ?></blockquote>
                                            <?php endif; ?>
                                            <?php if ( $teaching ) : ?>
                                                <p class="vana-passage-teaching"><?php echo esc_html( $teaching ); ?></p>
                                            <?php endif; ?>
                                            <?php if ( $p_vod_key && $p_ts_label ) : ?>
                                                <button class="vana-passage-seek"
                                                        data-action="seek-passage"
                                                        data-vod-key="<?php echo esc_attr( $p_vod_key ); ?>"
                                                        data-video-id="<?php echo esc_attr( $p_video_id ); ?>"
                                                        data-provider="<?php echo esc_attr( $p_provider ); ?>"
                                                        data-timestamp="<?php echo esc_attr( $p_ts_start ); ?>"
                                                        aria-label="<?php echo esc_attr(
                                                            ( $is_en ? 'Watch from ' : 'Assistir a partir de ' ) . $p_ts_label
                                                        ); ?>">
                                                    ▶ <?php echo esc_html( $p_ts_label ); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php else : ?>
                                <div class="vana-hk-lazy" data-katha-id="<?php echo esc_attr( $hk_katha_id ); ?>" data-lang="<?php echo esc_attr( $is_en ? 'en' : 'pt' ); ?>">
                                    <p class="vana-hk-empty"><?php echo $is_en ? 'Loading passages...' : 'Carregando trechos...'; ?></p>
                                </div>
                            <?php endif; ?>

                            <?php
                            $hk_ts_start = (int)( $hk_segment['timestamp_start'] ?? 0 );
                            $hk_ts_label = $hk_ts_start ? vana_fmt_ts( $hk_ts_start ) : '';
                            if ( $hk_segment['_vod_key'] && $hk_ts_label ) :
                            ?>
                            <button class="vana-hk-seek-main"
                                    data-action="seek-passage"
                                    data-vod-key="<?php echo esc_attr( $hk_segment['_vod_key'] ); ?>"
                                    data-video-id="<?php echo esc_attr( $hk_segment['_video_id'] ); ?>"
                                    data-provider="<?php echo esc_attr( $hk_segment['_provider'] ); ?>"
                                    data-timestamp="<?php echo esc_attr( $hk_ts_start ); ?>">
                                ▶ <?php echo $is_en ? 'Go to Hari-Kathā' : 'Ir para a Hari-Kathā'; ?>
                                <span class="vana-hk-seek-main__ts"><?php echo esc_html( $hk_ts_label ); ?></span>
                            </button>
                            <?php endif; ?>

                        </div>
                        <?php endif; ?>

                        <!-- FOTOS -->
                        <?php if ( ! empty( $photos ) ) : ?>
                        <div class="vana-event-section vana-event-section--photos">
                            <button class="vana-media-chip"
                                    data-action="open-photos"
                                    data-event-key="<?php echo esc_attr( $event_key ); ?>">
                                📸 <?php echo count( $photos ); ?>
                                <?php echo $is_en ? 'photos' : 'fotos'; ?>
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- SANGHA -->
                        <?php if ( ! empty( $sangha ) ) : ?>
                        <div class="vana-event-section vana-event-section--sangha">
                            <button class="vana-media-chip"
                                    data-action="open-sangha"
                                    data-event-key="<?php echo esc_attr( $event_key ); ?>">
                                💬 <?php echo count( $sangha ); ?>
                                <?php echo $is_en ? 'messages' : 'mensagens'; ?>
                            </button>
                        </div>
                        <?php endif; ?>

                        <?php if ( empty( $vods ) && ! $hk_segment && empty( $photos ) && empty( $sangha ) ) : ?>
                        <p class="vana-event-empty">
                            <?php echo $is_en ? 'Content being prepared.' : 'Conteúdo em preparação.'; ?>
                        </p>
                        <?php endif; ?>

                    </div><!-- /event-body -->
                </li><!-- /event-item -->

                <?php endforeach; ?>
            </ol><!-- /event-list -->

        </div><!-- /day-panel -->
        <?php endforeach; ?>

    </div><!-- /drawer-body -->
</div><!-- /agenda-drawer -->

<!-- Overlay -->
<div id="vana-agenda-overlay"
     class="vana-drawer__overlay"
     data-vana-agenda-overlay
     hidden></div>
