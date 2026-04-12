<?php
/**
 * Gaveta Direita — Agenda de Eventos
 * v2.0 · Schema 6.2 · Compatível com katha fragmentado
 */
defined( 'ABSPATH' ) || exit;

if ( defined( 'VANA_AGENDA_DRAWER_LOADED' ) ) return;
define( 'VANA_AGENDA_DRAWER_LOADED', true );

$is_en = ( $lang === 'en' );

// ── Helpers ──────────────────────────────────────────────────────────────────

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
    return [
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
    ][ $type ] ?? '▸';
}
endif;

/**
 * Coleta todos os segments type=harikatha de um evento.
 * Suporta katha fragmentado em múltiplos vods (R-HK-6).
 *
 * @param  array $vods    $event['vods']
 * @return array{
 *   katha_id: int|null,
 *   segments: array      cada item tem _vod_key, _video_id, _provider, _vod_part
 * }
 */
if ( ! function_exists( 'vana_collect_hk' ) ) :
function vana_collect_hk( array $vods ): array {
    $katha_id = null;
    $segments = [];

            foreach ( $vods as $vod ) {
        foreach ( $vod['segments'] ?? [] as $seg ) {
            if ( ( $seg['type'] ?? '' ) !== 'harikatha' ) continue;
            if ( empty( $seg['katha_id'] ) ) continue;

            $kid = (int) $seg['katha_id'];

            // R-HK-2: 1 katha_id único por evento
            if ( $katha_id === null ) {
                $katha_id = $kid;
            } elseif ( $katha_id !== $kid ) {
                // Katha diferente = evento diferente (R-HK-3) — ignora
                continue;
            }

            // Use + operator to preserve numeric keys in $seg (avoid reindexing)
            $segments[] = $seg + [
                '_vod_key'  => $vod['vod_key']  ?? '',
                '_video_id' => $vod['video_id'] ?? '',
                '_provider' => $vod['provider'] ?? 'youtube',
                '_vod_part' => (int)( $vod['vod_part'] ?? 1 ),
            ];
        }
    }

    return [ 'katha_id' => $katha_id, 'segments' => $segments ];
}
endif;

/**
 * Determina o day_key ativo:
 * 1. Hoje, se existir na visita
 * 2. Dia com status live
 * 3. Primeiro dia
 */
if ( ! function_exists( 'vana_active_day_key' ) ) :
function vana_active_day_key( array $days, array $index ): string {
    $today = current_time( 'Y-m-d' );

    // Prioridade 1 — live
    foreach ( $days as $day ) {
        $dk = $day['day_key'] ?? '';
        foreach ( $day['events'] ?? [] as $event ) {
            $ek     = $event['event_key'] ?? '';
            $status = $index['events'][ $ek ]['status'] ?? $event['status'] ?? '';
            if ( $status === 'live' ) return $dk;
        }
    }

    // Prioridade 2 — hoje
    foreach ( $days as $day ) {
        if ( ( $day['day_key'] ?? '' ) === $today ) return $today;
    }

    // Prioridade 3 — primeiro dia
    return $days[0]['day_key'] ?? '';
}
endif;

// ── Dados globais ─────────────────────────────────────────────────────────────

// Ensure $visit_tz is available (injected by parent template). Fallback to Asia/Kolkata.
if ( ! isset( $visit_tz ) || ! ( $visit_tz instanceof \DateTimeZone ) ) {
    try {
        $visit_tz = new \DateTimeZone( 'Asia/Kolkata' );
    } catch ( Exception $e ) {
        // Fallback name only — JS will handle missing names safely
        $visit_tz = null;
    }
}

if ( ! function_exists( 'vana_event_unix_ts' ) ) :
function vana_event_unix_ts( string $day_key, string $time_str, $tz ): int {
    if ( ! $day_key || ! $time_str || ! $tz ) return 0;
    try {
        $dt = new \DateTime( $day_key . ' ' . $time_str . ':00', $tz );
        return (int) $dt->getTimestamp();
    } catch ( Exception $e ) {
        return 0;
    }
}
endif;

$active_day_key = vana_active_day_key( $days, $index );

// JS needs a serializable TZ name (may be null)
$visit_tz_name = $visit_tz instanceof \DateTimeZone ? $visit_tz->getName() : '';
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     GAVETA AGENDA
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="vana-agenda-drawer"
     class="vana-drawer vana-drawer--agenda"
     role="dialog"
     aria-modal="true"
     aria-label="<?php echo esc_attr( $is_en ? 'Schedule' : 'Agenda' ); ?>"
     hidden>

    <!-- ── HEADER ──────────────────────────────────────────────────────────── -->
    <div class="vana-drawer__header">

        <span class="vana-drawer__title">
            📅 <?php echo $is_en ? 'Schedule' : 'Agenda'; ?>
        </span>

        <div class="vana-drawer__lang" role="group"
             aria-label="<?php echo esc_attr( $is_en ? 'Language' : 'Idioma' ); ?>">
            <button type="button"
                    class="vana-lang-btn <?php echo ! $is_en ? 'is-active' : ''; ?>"
                    data-vana-lang="pt"
                    aria-pressed="<?php echo ! $is_en ? 'true' : 'false'; ?>">PT</button>
            <button type="button"
                    class="vana-lang-btn <?php echo $is_en ? 'is-active' : ''; ?>"
                    data-vana-lang="en"
                    aria-pressed="<?php echo $is_en ? 'true' : 'false'; ?>">EN</button>
        </div>

        <button type="button"
                class="vana-drawer__close"
                data-vana-agenda-close
                aria-label="<?php echo esc_attr( $is_en ? 'Close' : 'Fechar' ); ?>">✕</button>
    </div>

    <!-- ── DAY TABS ────────────────────────────────────────────────────────── -->
    <div class="vana-agenda-day-tabs" role="tablist"
         aria-label="<?php echo esc_attr( $is_en ? 'Days' : 'Dias' ); ?>">

        <?php foreach ( $days as $day ) :
            $dk       = $day['day_key'] ?? '';
            $label    = $is_en
                ? ( $day['label_en'] ?? $dk )
                : ( $day['label_pt'] ?? $dk );
            $is_active = ( $dk === $active_day_key );

            // Indica se há live no dia (badge vermelho na tab)
            $day_has_live = false;
            foreach ( $day['events'] ?? [] as $ev ) {
                $ek = $ev['event_key'] ?? '';
                if ( ( $index['events'][ $ek ]['status'] ?? $ev['status'] ?? '' ) === 'live' ) {
                    $day_has_live = true;
                    break;
                }
            }
        ?>
        <button class="vana-day-tab <?php echo $is_active ? 'is-active' : ''; ?>"
                role="tab"
                id="vana-tab-<?php echo esc_attr( $dk ); ?>"
                data-day-key="<?php echo esc_attr( $dk ); ?>"
                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                aria-controls="vana-panel-<?php echo esc_attr( $dk ); ?>">
            <?php echo esc_html( $label ); ?>
            <?php if ( $day_has_live ) : ?>
                <span class="vana-day-tab__live" aria-label="ao vivo">🔴</span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ── BODY ────────────────────────────────────────────────────────────── -->
    <div class="vana-drawer__body">

        <?php foreach ( $days as $day ) :
            $dk        = $day['day_key'] ?? '';
            $is_active = ( $dk === $active_day_key );
            $tithi     = $is_en
                ? ( $day['tithi_name_en'] ?? '' )
                : ( $day['tithi_name_pt'] ?? '' );
            $events    = $day['events'] ?? [];
        ?>

        <div id="vana-panel-<?php echo esc_attr( $dk ); ?>"
             class="vana-day-panel <?php echo $is_active ? 'is-active' : ''; ?>"
             data-day-panel="<?php echo esc_attr( $dk ); ?>"
             role="tabpanel"
             aria-labelledby="vana-tab-<?php echo esc_attr( $dk ); ?>"
             <?php echo ! $is_active ? 'hidden' : ''; ?>>

            <?php if ( $tithi ) : ?>
            <div class="vana-tithi">🌙 <?php echo esc_html( $tithi ); ?></div>
            <?php endif; ?>

            <!-- Hint de fuso (por dia) — preenchido/mostrado via JS se visitante em fuso diferente -->
            <div class="vana-tz-hint"
                 id="vana-tz-hint-<?php echo esc_attr( $dk ); ?>"
                 data-visit-tz="<?php echo esc_attr( $visit_tz_name ); ?>"
                 hidden>
                <span class="vana-tz-hint__icon" aria-hidden="true">🌐</span>
                <span class="vana-tz-hint__text">
                    <?php
                    $tz_city = $visit_tz_name ? explode( '/', $visit_tz_name ) : [];
                    $city    = $tz_city ? str_replace( '_', ' ', end( $tz_city ) ) : '';
                    echo $is_en
                        ? esc_html( $city ? "Times shown in $city. Your local time in parentheses." : 'Times shown in visit timezone. Your local time in parentheses.' )
                        : esc_html( $city ? "Horários em $city. Seu horário entre parênteses." : 'Horários no fuso da visita. Seu horário entre parênteses.' );
                    ?>
                </span>
            </div>

            <?php if ( empty( $events ) ) : ?>
            <p class="vana-day-empty">
                <?php echo $is_en
                    ? 'No events registered for this day.'
                    : 'Nenhum evento registrado para este dia.'; ?>
            </p>
            <?php else : ?>

            <ol class="vana-event-list" role="list">
            <?php foreach ( $events as $ei => $event ) :

                $event_key  = $event['event_key']  ?? '';
                $event_type = $event['type']        ?? 'programa';
                $event_time = $event['time']        ?? '';
                $event_loc  = $event['location']['name'] ?? '';
                $event_title = $is_en
                    ? ( $event['title_en'] ?? $event['title_pt'] ?? '' )
                    : ( $event['title_pt'] ?? '' );

                // Status canônico — index tem prioridade (R-IDX)
                $status = $index['events'][ $event_key ]['status']
                       ?? $event['status']
                       ?? 'past';

                $vods   = $event['vods']   ?? [];
                $photos = $event['photos'] ?? [];
                $sangha = $event['sangha'] ?? [];

                // ── Coleta HK (suporta katha fragmentado) ──────────────────
                $hk        = vana_collect_hk( $vods );
                $hk_id     = $hk['katha_id'];
                $hk_segs   = $hk['segments'];
                $has_hk    = ! empty( $hk_segs ) && $hk_id !== null;

                // Metadados do katha no index
                $katha_meta = $has_hk
                    ? ( $index['kathas'][ $hk_id ] ?? [] )
                    : [];
                $katha_title = $is_en
                    ? ( $katha_meta['title_en'] ?? $katha_meta['title_pt'] ?? '' )
                    : ( $katha_meta['title_pt'] ?? '' );
                $scripture = $katha_meta['scripture'] ?? '';

                // ── Status badge ───────────────────────────────────────────
                $status_badge = match( $status ) {
                    'live' => '<span class="vana-badge vana-badge--live" aria-label="ao vivo">🔴 '
                              . ( $is_en ? 'LIVE' : 'AO VIVO' ) . '</span>',
                    'soon' => '<span class="vana-badge vana-badge--soon">🕐 '
                              . ( $is_en ? 'Soon' : 'Em breve' ) . '</span>',
                    default => '',
                };

                // Primeiro evento do dia ativo abre expandido
                $is_primary = ( $event_key === ( $day['primary_event_key'] ?? '' ) )
                           && $is_active;

                // Ensure unique DOM id even when event_key is empty
                $event_dom_id = 'vana-event-'
                    . ( $event_key ? sanitize_html_class( $event_key ) : 'unk-' . sanitize_html_class( $dk ) . '-' . (int) $ei );
            ?>

            <li class="vana-event-item <?php echo $is_primary ? 'is-expanded' : ''; ?>"
                data-event-key="<?php echo esc_attr( $event_key ); ?>"
                data-event-type="<?php echo esc_attr( $event_type ); ?>"
                data-status="<?php echo esc_attr( $status ); ?>">

                <!-- ── Toggle do evento ────────────────────────────────── -->
                <button class="vana-event-toggle"
                        aria-expanded="<?php echo $is_primary ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr( $event_dom_id ); ?>">

                    <?php
                    // Compute unix ts for this event (visit local tz)
                    $ts_unix  = 0;
                    if ( $event_time ) {
                        $ts_unix = vana_event_unix_ts( $dk, $event_time, $visit_tz );
                    }
                    $ltime_id = 'vana-ltime-' . sanitize_html_class( $event_key );
                    ?>

                    <span class="vana-event-toggle__time">
                        <!-- Hora original (fuso da visita) -->
                        <span class="vana-event-time__origin"
                              aria-label="<?php echo esc_attr( $is_en ? 'Local time' : 'Horário local' ); ?>">
                            🕐 <?php echo esc_html( $event_time ?: '—' ); ?>
                        </span>

                        <?php if ( $ts_unix > 0 ) : ?>
                        <!-- Hora traduzida (fuso do visitante) -->
                        <span class="vana-event-time__visitor"
                              id="<?php echo esc_attr( $ltime_id ); ?>"
                              data-ts="<?php echo (int) $ts_unix; ?>"
                              aria-label="<?php echo esc_attr( $is_en ? 'Your local time' : 'Seu horário' ); ?>"
                              hidden>
                            <!-- JS will fill this -->
                        </span>
                        <?php endif; ?>
                    </span>

                    <span class="vana-event-toggle__title">
                        <?php echo esc_html( $event_title ); ?>
                        <?php echo $status_badge; ?>
                    </span>

                    <!-- Indicadores de conteúdo disponível -->
                    <span class="vana-event-toggle__hints" aria-hidden="true">
                        <?php if ( ! empty( $vods ) )   echo '<span title="Vídeo">🎬</span>'; ?>
                        <?php if ( $has_hk )             echo '<span title="Hari-Kathā">📖</span>'; ?>
                        <?php if ( ! empty( $photos ) )  echo '<span title="Fotos">📸</span>'; ?>
                        <?php if ( ! empty( $sangha ) )  echo '<span title="Sangha">💬</span>'; ?>
                    </span>

                    <span class="vana-event-toggle__chevron" aria-hidden="true">
                        <?php echo $is_primary ? '−' : '+'; ?>
                    </span>
                </button>

                <!-- ── Corpo do evento ─────────────────────────────────── -->
                <div id="<?php echo esc_attr( $event_dom_id ); ?>"
                     class="vana-event-body"
                     <?php echo ! $is_primary ? 'hidden' : ''; ?>>

                    <!-- Localização -->
                    <?php if ( $event_loc ) : ?>
                    <div class="vana-event-location">
                        📍 <span><?php echo esc_html( $event_loc ); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- ── VODs ────────────────────────────────────────── -->
                    <?php if ( ! empty( $vods ) ) : ?>
                    <div class="vana-event-section vana-event-section--vods">
                        <div class="vana-event-section__label">
                            🎬 <?php echo $is_en ? 'Videos' : 'Vídeos'; ?>
                        </div>
                        <div class="vana-vod-chips">
                            <?php foreach ( $vods as $vod ) :
                                $vod_key   = $vod['vod_key']  ?? '';
                                $vod_part  = (int)( $vod['vod_part'] ?? 1 );
                                $video_id  = $vod['video_id'] ?? '';
                                $provider  = $vod['provider'] ?? 'youtube';
                                $duration  = (int)( $vod['duration_s'] ?? 0 );
                                $dur_label = $duration ? vana_fmt_ts( $duration ) : '';
                                $vod_title = $is_en
                                    ? ( $vod['title_en'] ?? $vod['title_pt'] ?? '' )
                                    : ( $vod['title_pt'] ?? '' );

                                // Marca vods que contêm HK
                                $vod_has_hk = (bool)( $index['vods'][ $vod_key ]['has_katha'] ?? false );
                            ?>
                            <button class="vana-vod-chip <?php echo $vod_has_hk ? 'vana-vod-chip--has-hk' : ''; ?>"
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
                                <?php if ( $vod_has_hk ) : ?>
                                <span class="vana-vod-chip__hk-dot" aria-hidden="true" title="Hari-Kathā">📖</span>
                                <?php endif; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ── HARI-KATHĀ ───────────────────────────────────── -->
                    <?php if ( $has_hk ) : ?>
                    <div class="vana-event-section vana-event-section--hk"
                         data-katha-id="<?php echo esc_attr( $hk_id ); ?>">

                        <div class="vana-event-section__label">
                            📖 <?php echo esc_html( $katha_title ?: 'Hari-Kathā' ); ?>
                            <?php if ( $scripture ) : ?>
                            <span class="vana-hk-scripture">· <?php echo esc_html( $scripture ); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Seek por parte (suporte a katha fragmentado) -->
                        <div class="vana-hk-seek-group"
                             aria-label="<?php echo esc_attr( $is_en ? 'Watch Hari-Kathā' : 'Assistir Hari-Kathā' ); ?>">
                            <?php foreach ( $hk_segs as $seg ) :
                                $seg_ts    = (int)( $seg['timestamp_start'] ?? 0 );
                                $seg_label = vana_fmt_ts( $seg_ts );
                                $part_num  = $seg['_vod_part'];
                                $multi     = count( $hk_segs ) > 1;
                            ?>
                            <button class="vana-hk-seek-btn"
                                    data-action="seek-passage"
                                    data-vod-key="<?php echo esc_attr( $seg['_vod_key'] ); ?>"
                                    data-video-id="<?php echo esc_attr( $seg['_video_id'] ); ?>"
                                    data-provider="<?php echo esc_attr( $seg['_provider'] ); ?>"
                                    data-timestamp="<?php echo esc_attr( $seg_ts ); ?>"
                                    aria-label="<?php echo esc_attr(
                                        ( $is_en ? 'Watch Hari-Kathā' : 'Assistir Hari-Kathā' )
                                        . ( $multi ? ' Pt.' . $part_num : '' )
                                        . ' ' . $seg_label
                                    ); ?>">
                                ▶ <?php echo $is_en ? 'Hari-Kathā' : 'Hari-Kathā'; ?>
                                <?php if ( $multi ) : ?>
                                    <span class="vana-hk-part">Pt.<?php echo $part_num; ?></span>
                                <?php endif; ?>
                                <span class="vana-hk-seek-btn__ts"><?php echo esc_html( $seg_label ); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Passages via REST (lazy) — nunca SSR no Schema 6.2 -->
                            <div class="vana-hk-lazy"
                                data-katha-id="<?php echo esc_attr( $hk_id ); ?>"
                                data-lang="<?php echo esc_attr( $is_en ? 'en' : 'pt' ); ?>"
                                data-visit-id="<?php echo esc_attr( $index['visit_id'] ?? '' ); ?>"
                                data-day="<?php echo esc_attr( $dk ); ?>"
                                data-loaded="false"
                                aria-live="polite">
                            <!-- JS injeta os passages via GET /vana/v1/katha/{id} -->
                        </div>

                    </div>
                    <?php endif; ?>

                    <!-- ── FOTOS ────────────────────────────────────────── -->
                    <?php if ( ! empty( $photos ) ) : ?>
                    <div class="vana-event-section vana-event-section--photos">
                        <button class="vana-media-chip"
                                data-action="open-photos"
                                data-event-key="<?php echo esc_attr( $event_key ); ?>"
                                aria-label="<?php echo esc_attr(
                                    count( $photos ) . ' ' . ( $is_en ? 'photos' : 'fotos' )
                                ); ?>">
                            📸 <?php echo count( $photos ); ?>
                            <?php echo $is_en ? 'photos' : 'fotos'; ?>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- ── SANGHA ───────────────────────────────────────── -->
                    <?php if ( ! empty( $sangha ) ) : ?>
                    <div class="vana-event-section vana-event-section--sangha">
                        <button class="vana-media-chip"
                                data-action="open-sangha"
                                data-event-key="<?php echo esc_attr( $event_key ); ?>"
                                aria-label="<?php echo esc_attr(
                                    count( $sangha ) . ' ' . ( $is_en ? 'messages' : 'mensagens' )
                                ); ?>">
                            💬 <?php echo count( $sangha ); ?>
                            <?php echo $is_en ? 'messages' : 'mensagens'; ?>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Estado vazio -->
                    <?php if ( empty( $vods ) && ! $has_hk && empty( $photos ) && empty( $sangha ) ) : ?>
                    <p class="vana-event-empty">
                        <?php echo $status === 'soon'
                            ? ( $is_en ? 'Content coming soon.' : 'Conteúdo em breve.' )
                            : ( $is_en ? 'Content being prepared.' : 'Conteúdo em preparação.' ); ?>
                    </p>
                    <?php endif; ?>

                </div><!-- /event-body -->
            </li><!-- /event-item -->

            <?php endforeach; ?>
            </ol>

            <?php endif; // events não vazio ?>

        </div><!-- /day-panel -->
        <?php endforeach; ?>

    </div><!-- /drawer-body -->
</div><!-- /agenda-drawer -->

<div id="vana-agenda-overlay"
     class="vana-drawer__overlay"
     data-vana-agenda-overlay
     hidden></div>

<?php // ── Script de dual-timezone — final do partial ───────────────────────────────── ?>
<script>
(function () {
    'use strict';

    var VISIT_TZ = '<?php echo esc_js( $visit_tz_name ); ?>';

    var visitorTz = '';
    try {
        visitorTz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (_) {}

    var hasDiff = visitorTz && visitorTz !== VISIT_TZ;

    var locale = '<?php echo esc_js( $is_en ? "en-GB" : "pt-BR" ); ?>';
    function fmtVisitor(ts) {
        try {
            return new Intl.DateTimeFormat(locale, {
                hour: '2-digit', minute: '2-digit', timeZone: visitorTz, hour12: false
            }).format(new Date(ts * 1000));
        } catch (_) { return ''; }
    }

    function fillTimes() {
        if (!hasDiff) return;
        document.querySelectorAll('#vana-agenda-drawer [data-ts]').forEach(function (el) {
            var ts = parseInt(el.dataset.ts, 10);
            if (!ts) return;
            var fmt = fmtVisitor(ts);
            if (!fmt) return;
            el.textContent = '(' + fmt + ')';
            el.removeAttribute('hidden');
        });
    }

    function showTzHints() {
        if (!hasDiff) return;
        document.querySelectorAll('.vana-tz-hint').forEach(function (el) {
            el.removeAttribute('hidden');
        });
    }

    fillTimes();
    showTzHints();

    document.addEventListener('vana:day-activated', function (e) {
        fillTimes();
        showTzHints();
    });

})();
</script>

<?php // Fallback loader: tenta /katha/{id} e, em 404, /kathas?visit_id=..&day=.. ?>
<script>
(function () {
    'use strict';

    var REST_BASE = '<?php echo esc_js( trailingslashit( rest_url( 'vana/v1' ) ) ); ?>';
    var LANG_CODE = '<?php echo esc_js( $is_en ? 'en' : 'pt' ); ?>';

    async function tryLoadKatha(el) {
        if (!el || el.dataset.loaded === 'true') return;
        var kathaId = el.dataset.kathaId;
        if (!kathaId) return;

        // 1) Try canonical endpoint /katha/{katha_ref}
        try {
            var url = REST_BASE + 'katha/' + encodeURIComponent(kathaId) + '?lang=' + LANG_CODE;
            var r = await fetch(url, { credentials: 'same-origin' });
            if (r.ok) {
                // successful — show simple open button (detailed rendering handled elsewhere)
                el.innerHTML = '<button type="button" class="vana-hk-open-stage">' + (LANG_CODE === 'en' ? 'Open Hari‑Kathā' : 'Abrir Hari‑Kathā') + '</button>';
                el.querySelector('.vana-hk-open-stage').addEventListener('click', function () {
                    if (window.VanaStage && typeof window.VanaStage.loadKatha === 'function') {
                        window.VanaStage.loadKatha(kathaId);
                    } else {
                        // fallback to permalink if provided in response
                        r.json().then(function (j) {
                            var p = j?.data?.katha?.permalink || '#';
                            window.location.href = p;
                        }).catch(function () { /* noop */ });
                    }
                });
                el.dataset.loaded = 'true';
                return;
            }
        } catch (e) {
            // ignore and try fallback
        }

        // 2) Fallback: list kathas for visit/day and try to find a match
        var visitId = el.dataset.visitId;
        var day = el.dataset.day;
        if (visitId && day) {
            try {
                var url2 = REST_BASE + 'kathas?visit_id=' + encodeURIComponent(visitId) + '&day=' + encodeURIComponent(day);
                var r2 = await fetch(url2, { credentials: 'same-origin' });
                if (r2.ok) {
                    var j2 = await r2.json();
                    var items = (j2 && j2.data && j2.data.items) || [];
                    if (items.length) {
                        var found = items.find(function (it) { return String(it.id) === String(kathaId); });
                        if (found) {
                            el.innerHTML = '<button type="button" class="vana-hk-open-stage">' + (LANG_CODE === 'en' ? 'Open Hari‑Kathā' : 'Abrir Hari‑Kathā') + '</button>';
                            el.querySelector('.vana-hk-open-stage').addEventListener('click', function () {
                                if (window.VanaStage && typeof window.VanaStage.loadKatha === 'function') {
                                    window.VanaStage.loadKatha(found.id);
                                } else if (found.permalink) {
                                    window.location.href = found.permalink;
                                }
                            });
                            el.dataset.loaded = 'true';
                            return;
                        }

                        // if not found, offer first as sensible fallback
                        var first = items[0];
                        el.innerHTML = '<div class="vana-hk-fallback">' +
                            (first.permalink ? '<a href="' + first.permalink + '">' : '') +
                            (first.title_pt || first.title_en || 'Hari‑Kathā') +
                            (first.permalink ? '</a>' : '') +
                            '</div>';
                        el.dataset.loaded = 'partial';
                        return;
                    }
                }
            } catch (e) {
                // noop
            }
        }

        el.innerHTML = '<p class="vana-hk__error">' + (LANG_CODE === 'en' ? 'Hari‑Kathā not available.' : 'Hari‑Kathā não disponível.') + '</p>';
        el.dataset.loaded = 'true';
    }

    function loadAllLazy() {
        document.querySelectorAll('.vana-hk-lazy').forEach(function (el) {
            tryLoadKatha(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAllLazy);
    } else {
        loadAllLazy();
    }

    // Also attempt when drawer opens (supports dynamic activation)
    document.addEventListener('click', function (e) {
        if (e.target.closest('#vana-agenda-drawer') || e.target.closest('[data-vana-agenda-open]')) {
            loadAllLazy();
        }
    }, true);

})();
</script>
