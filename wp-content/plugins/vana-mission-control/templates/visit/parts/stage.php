<?php
/**
 * Partial: Stage (Palco Principal) — schema 6.1 COMPATÍVEL
 * Arquivo: templates/visit/parts/stage.php
 *
 * @since 5.1.3
 * @updated 6.0.0 — Remove katha zone duplicada; adiciona zona mutável.
 */
defined( 'ABSPATH' ) || exit;

// ── 1. Normaliza evento ativo ─────────────────────────────────────────────
$_evt  = is_array( $active_event ) ? $active_event : [];

// Schema 6.1: event.vods[] canônico
// Fallback legacy: event.media.vods[]
$_vods = is_array( $_evt['vods'] ?? null )
     ? $_evt['vods']
     : ( is_array( $_evt['media']['vods'] ?? null )
       ? $_evt['media']['vods']
       : [] );

// Schema 6.1: event.photos[] + event.sangha[]
// Fallback legacy: event.media.gallery[] + event.media.sangha_moments[]
$_gallery = is_array( $_evt['photos']                ?? null ) ? $_evt['photos']
      : ( is_array( $_evt['media']['gallery']    ?? null ) ? $_evt['media']['gallery']
      : ( $active_day['gallery']                 ?? [] ) );

$_sangha  = is_array( $_evt['sangha']                        ?? null ) ? $_evt['sangha']
      : ( is_array( $_evt['media']['sangha_moments']      ?? null ) ? $_evt['media']['sangha_moments']
      : ( $active_day['sangha_moments']                   ?? [] ) );

$_vod_first = $_vods[0] ?? [];

$current_event = vana_normalize_event( [
  'active_vod'   => $_vod_first,
  'vod_list'     => array_slice( $_vods, 1 ),
  'hero'         => $active_day['hero'] ?? [],
  'gallery'      => $_gallery,
  'sangha'       => $_sangha,
  'event_key'    => $_evt['event_key']  ?? '',
  'title_pt'     => Vana_Utils::pick_i18n_key( $_vod_first, 'title', 'pt' ),
  'title_en'     => Vana_Utils::pick_i18n_key( $_vod_first, 'title', 'en' ),
  'time_start'   => $_evt['time']       ?? ( $_evt['time_start'] ?? ( $active_day['date_local'] ?? '' ) ),
  'status'       => $_evt['status']     ?? ( $visit_status ?? '' ),
] );

// ── 2. Resolve conteúdo via hierarquia ────────────────────────────────────
$stage = vana_get_stage_content( $current_event );

// ── 3. Metadados de apoio ─────────────────────────────────────────────────
$stage_title = Vana_Utils::pick_i18n_key(
  $stage['data'] ?? $current_event,
  'title',
  $lang
);
$stage_desc = Vana_Utils::pick_i18n_key(
  $stage['data'] ?? $current_event,
  'description',
  $lang
);

// ── 3a. Segments — Schema 6.1: vod.segments[] com timestamp_start (int) ──
$stage_segments = [];
if ( ! empty( $_vod_first['segments'] ) && is_array( $_vod_first['segments'] ) ) {
  $stage_segments = $_vod_first['segments'];
} elseif ( is_array( ( $stage['data'] ?? [] )['segments'] ?? null ) ) {
  $stage_segments = $stage['data']['segments'];
}

// ── 3b. Localização — Schema 6.1: event.location{name, lat, lng} ──────────
$stage_loc = is_array( $_evt['location'] ?? null )
       ? $_evt['location']
       : ( is_array( ( $stage['data'] ?? [] )['location'] ?? null )
         ? $stage['data']['location']
         : ( is_array( $active_day['hero']['location'] ?? null )
           ? $active_day['hero']['location']
           : [] ) );

// ── 4. Mapa ───────────────────────────────────────────────────────────────
$lat        = is_numeric( $stage_loc['lat'] ?? '' ) ? (string) $stage_loc['lat'] : '';
$lng        = is_numeric( $stage_loc['lng'] ?? '' ) ? (string) $stage_loc['lng'] : '';
$has_coords = ( $lat !== '' && $lng !== '' );
$maps_embed = $has_coords
  ? 'https://maps.google.com/maps?q=' . rawurlencode( "$lat,$lng" )
    . '&hl=' . ( $lang === 'en' ? 'en' : 'pt' ) . '&z=15&output=embed'
  : '';

$map_btn_id    = 'vanaLoadMapBtn_'  . esc_attr( $active_day_date );
$map_wrap_id   = 'vanaMapWrap_'     . esc_attr( $active_day_date );
$map_iframe_id = 'vanaMapIframe_'   . esc_attr( $active_day_date );

// ── 5. Live badge ─────────────────────────────────────────────────────────
$has_live = ( ( $_evt['status'] ?? '' ) === 'live' );
if ( ! $has_live ) {
  foreach ( is_array( $active_day['events'] ?? null ) ? $active_day['events'] : [] as $_ev ) {
    if ( is_array( $_ev ) && ( $_ev['status'] ?? '' ) === 'live' ) {
      $has_live = true;
      break;
    }
  }
}

// ── 6. Modo ───────────────────────────────────────────────────────────────
$is_neutral_mode  = empty( $current_event['event_key'] ) || empty( $stage['type'] );
$is_transitioning = false;

// ── 7. Katha ID ───────────────────────────────────────────────────────────
$_kathas         = is_array( $_evt['kathas'] ?? null ) ? $_evt['kathas'] : [];
$_katha_first    = $_kathas[0] ?? [];

$stage_katha_ref = (string) ( $_katha_first['katha_key'] ?? '' );
$stage_katha_id  = (string) ( $_katha_first['katha_id']  ?? '' );
$stage_katha_dom_ref = $stage_katha_ref ?: $stage_katha_id;

// ── 7a. Múltiplas kathas ──────────────────────────────────────────────────
$stage_has_multiple_kathas = count( $_kathas ) > 1;

// ── 7b. Sources do primeiro vod da katha ─────────────────────────────────
$_katha_sources     = is_array( $_katha_first['sources'] ?? null ) ? $_katha_first['sources'] : [];
$_katha_sources_json = wp_json_encode( array_map( fn( $s ) => [
  'vod_key'         => $s['vod_key']    ?? '',
  'segment_id'      => $s['segment_id'] ?? '',
  'vod_part'        => $s['vod_part']   ?? 1,
  'timestamp_start' => $s['timestamp_start'] ?? 0,
  'timestamp_end'   => $s['timestamp_end']   ?? 0,
], $_katha_sources ) );

// ── 8. Stage mode ─────────────────────────────────────────────────────────
$stage_mode = isset( $stage_mode ) ? (string) $stage_mode : 'default';
if ( $stage_mode === 'default' ) {
  $stage_mode = match ( $stage['type'] ?? '' ) {
    'vod'     => 'katha',
    'gallery' => 'gallery',
    'sangha'  => 'sangha',
    default   => 'neutral',
  };
}

// ── 9. Iframe src ─────────────────────────────────────────────────────────
$resolved_media = vana_stage_resolve_media( $stage['data'] ?? [] );
$iframe_src     = '';
$iframe_vod_key = (string) ( $_vod_first['vod_key'] ?? '' );

if ( ( $resolved_media['provider'] ?? '' ) === 'youtube' && ! empty( $resolved_media['video_id'] ) ) {
  $iframe_src = 'https://www.youtube-nocookie.com/embed/'
          . esc_attr( $resolved_media['video_id'] )
          . '?rel=0&autoplay=1&enablejsapi=1&origin=' . rawurlencode( home_url() );
} elseif ( ( $resolved_media['provider'] ?? '' ) === 'drive' && ! empty( $resolved_media['url'] ) ) {
  $fid = vana_drive_file_id( $resolved_media['url'] );
  if ( $fid ) {
    $iframe_src = 'https://drive.google.com/file/d/' . esc_attr( $fid ) . '/preview';
  }
} elseif ( ( $resolved_media['provider'] ?? '' ) === 'facebook' && ! empty( $resolved_media['url'] ) ) {
  $iframe_src = 'https://www.facebook.com/plugins/video.php?href='
          . rawurlencode( esc_url_raw( $resolved_media['url'] ) )
          . '&show_text=0&width=1200';
}

$seg_provider = $resolved_media['provider'] ?? '';

// ── 10. Zona Mutável — estado SSR inicial ─────────────────────────────────
// O VanaStateRouter.js assume o controle client-side.
// Estado SSR: se há katha, começa com hint "katha"; senão "neutral".
$mutable_zone_state = $stage_katha_dom_ref ? 'katha' : 'neutral';
?>
<section
    class="vana-stage <?php echo $is_neutral_mode ? 'vana-stage--neutral' : ''; ?>"
    id="vana-stage"
    data-event-key="<?php echo esc_attr( $current_event['event_key'] ); ?>"
    data-stage-mode="<?php echo esc_attr( $stage_mode ); ?>"

    data-katha-ref="<?php echo esc_attr( $stage_katha_dom_ref ); ?>"
    data-katha-id="<?php echo esc_attr( $stage_katha_id ); ?>"
    data-katha-sources="<?php echo esc_attr( $_katha_sources_json ); ?>"
    data-vod-key="<?php echo esc_attr( $iframe_vod_key ); ?>"

    aria-label="<?php echo esc_attr( vana_t( 'stage.aria', $lang ) ); ?>"
    data-is-neutral="<?php echo $is_neutral_mode ? '1' : '0'; ?>"
    data-transitioning="<?php echo $is_transitioning ? '1' : '0'; ?>"
>

  <!-- PLAYER ════════════════════════════════════════════════ -->
  <div
      class="vana-stage-video"
      id="vana-stage-video-wrap"
      data-iframe-src="<?php echo esc_attr( $iframe_src ); ?>"
      data-iframe-title="<?php echo esc_attr( $stage_title ?: vana_t( 'stage.class', $lang ) ); ?>"
      data-provider="<?php echo esc_attr( $seg_provider ); ?>"
      data-vod-key="<?php echo esc_attr( $iframe_vod_key ); ?>"
  >

    <?php if ( $stage['type'] === 'vod' && $iframe_src ) : ?>

      <iframe
          id="vana-stage-iframe"
          src="<?php echo esc_url( $iframe_src ); ?>"
          title="<?php echo esc_attr( $stage_title ?: vana_t( 'stage.class', $lang ) ); ?>"
          style="position:absolute;inset:0;width:100%;height:100%;border:0;"
          allowfullscreen
          allow="autoplay"
          loading="lazy"
      ></iframe>

      <?php if ( $has_live ) : ?>
        <span class="stage__badge--live">🔴 Ao vivo</span>
      <?php endif; ?>

    <?php elseif ( $stage['type'] === 'gallery' ) : ?>

      <div class="stage__gallery-preview"
           style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
                  gap:8px;padding:16px;height:100%;box-sizing:border-box;">
        <?php foreach ( array_slice( $stage['data'], 0, 6 ) as $photo ) :
              if ( ! is_array( $photo ) ) continue; ?>
          <img
            src="<?php echo esc_url( $photo['url'] ?? $photo['thumb_url'] ?? '' ); ?>"
            alt="<?php echo esc_attr( $photo['caption_pt'] ?? $photo['caption'] ?? '' ); ?>"
            style="width:100%;height:100px;object-fit:cover;border-radius:8px;"
            loading="lazy"
          />
        <?php endforeach; ?>
      </div>

    <?php elseif ( $stage['type'] === 'sangha' ) : ?>

      <div class="vana-stage-placeholder" style="padding:40px;text-align:center;">
        <blockquote style="font-size:1.2rem;line-height:1.7;color:var(--vana-text);
                           margin:0 0 16px;font-style:italic;">
          <?php
          $sangha_text = Vana_Utils::pick_i18n_key( $stage['data'], 'caption', $lang )
                      ?: ( $stage['data']['text'] ?? '' );
          echo nl2br( esc_html( $sangha_text ) );
          ?>
        </blockquote>
        <cite style="color:var(--vana-muted);font-size:.95rem;">
          — <?php echo esc_html( $stage['data']['author'] ?? '' ); ?>
        </cite>
      </div>

    <?php else : ?>

      <div class="vana-stage-placeholder"
           role="status" aria-live="polite"
           style="color:var(--vana-muted);font-size:1.2rem;text-align:center;padding:40px;">
        <?php if ( $has_live ) : ?>
          <span class="dashicons dashicons-video-alt3"
                style="font-size:2.5rem;display:block;margin-bottom:12px;
                       color:var(--vana-pink);" aria-hidden="true"></span>
          <?php echo esc_html( vana_t( 'stage.live_soon', $lang ) ); ?>
        <?php else : ?>
          <span class="dashicons dashicons-format-video"
                style="font-size:2.5rem;display:block;margin-bottom:12px;
                       color:var(--vana-line);" aria-hidden="true"></span>
          <?php echo esc_html( vana_t( 'stage.empty', $lang ) ); ?>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div><!-- /vana-stage-video-wrap -->


  <!-- INFO ══════════════════════════════════════════════════ -->
  <div class="vana-stage-info">

    <div style="display:flex;align-items:center;gap:15px;
                margin-bottom:<?php echo $stage_desc ? '15px' : '0'; ?>;">
      <span class="vana-stage-info-badge">
        <?php echo esc_html( vana_t( 'stage.class', $lang ) ); ?>
      </span>
      <h2 id="vanaStageTitle"
          style="margin:0;font-family:'Syne',sans-serif;font-size:1.3rem;">
        <?php echo esc_html( $stage_title ?: vana_t( 'stage.recording', $lang ) ); ?>
      </h2>
    </div>

    <?php if ( ! $is_neutral_mode ) : ?>
      <div class="vana-stage-actions" style="display:flex;gap:10px;margin:12px 0;flex-wrap:wrap;">

        <button
          type="button"
          class="vana-stage-action-btn vana-stage-action-btn--share"
          id="vana-stage-share-btn"
          aria-label="<?php echo esc_attr( vana_t( 'stage.share', $lang ) ?: 'Compartilhar' ); ?>"
        >
          <span aria-hidden="true">📤</span>
          <span><?php echo esc_html( vana_t( 'stage.share', $lang ) ?: 'Compartilhar' ); ?></span>
        </button>

        <?php if ( $stage_katha_dom_ref ) : ?>
          <button
            type="button"
            class="vana-stage-action-btn vana-stage-action-btn--hk"
            id="vana-stage-hk-btn"
            data-katha-ref="<?php echo esc_attr( $stage_katha_dom_ref ); ?>"
            data-katha-id="<?php echo esc_attr( $stage_katha_id ); ?>"
            data-action="vana:stage:katha"
            aria-label="<?php echo esc_attr( vana_t( 'stage.open_hk', $lang ) ?: 'Abrir Hari-Katha' ); ?>"
          >
            <span aria-hidden="true">🙏</span>
            <span><?php echo esc_html( vana_t( 'stage.open_hk', $lang ) ?: 'Hari-Katha' ); ?></span>
          </button>

          <?php if ( $stage_has_multiple_kathas ) : ?>
            <span class="vana-stage-katha-count" aria-label="<?php echo count( $_kathas ); ?> aulas">
              +<?php echo count( $_kathas ) - 1; ?>
            </span>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    <?php endif; ?>

    <?php if ( $stage_desc ) : ?>
      <div class="vana-stage-desc"
           style="color:var(--vana-muted);line-height:1.6;font-size:1.05rem;">
        <?php echo nl2br( esc_html( $stage_desc ) ); ?>
      </div>
    <?php endif; ?>

    <!-- Localização + Mapa lazy ─────────────────────────── -->
    <?php
    $loc_name = (string) ( $stage_loc['name'] ?? '' );
    if ( $loc_name || $has_coords ) :
    ?>
      <div class="vana-stage-loc" style="margin-top:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;
                    gap:12px;flex-wrap:wrap;padding:10px 14px;border-radius:12px;
                    border:1px solid var(--vana-line);background:var(--vana-bg-soft);">
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-location-alt"
                  aria-hidden="true" style="color:var(--vana-pink);"></span>
            <strong style="color:var(--vana-text);">
              <?php echo esc_html( $loc_name ?: $visit_city_ref ); ?>
            </strong>
          </div>
          <?php if ( $has_coords ) : ?>
            <button
              type="button"
              id="<?php echo esc_attr( $map_btn_id ); ?>"
              style="padding:8px 12px;background:rgba(255,255,255,0.06);
                     border:1px solid var(--vana-line);border-radius:8px;
                     cursor:pointer;font-weight:700;font-size:.9rem;"
              aria-expanded="false"
              aria-controls="<?php echo esc_attr( $map_wrap_id ); ?>"
            >
              <?php echo esc_html( vana_t( 'stage.map_load', $lang ) ); ?>
            </button>
          <?php endif; ?>
        </div>

        <?php if ( $has_coords ) : ?>
          <div id="<?php echo esc_attr( $map_wrap_id ); ?>"
               style="display:none;margin-top:12px;border-radius:12px;
                      overflow:hidden;border:1px solid var(--vana-line);
                      height:200px;background:#e2e8f0;"
               aria-hidden="true">
            <iframe
              id="<?php echo esc_attr( $map_iframe_id ); ?>"
              width="100%" height="100%"
              style="border:0;" loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen
              title="<?php echo esc_attr( vana_t( 'stage.map_aria', $lang ) ); ?>"
              data-src="<?php echo esc_url( $maps_embed ); ?>"
            ></iframe>
          </div>
          <script>
          (function () {
            var btn    = document.getElementById(<?php echo wp_json_encode( $map_btn_id ); ?>);
            var wrap   = document.getElementById(<?php echo wp_json_encode( $map_wrap_id ); ?>);
            var iframe = document.getElementById(<?php echo wp_json_encode( $map_iframe_id ); ?>);
            if (!btn || !wrap || !iframe) return;
            btn.addEventListener('click', function () {
              if (!iframe.getAttribute('src')) {
                iframe.setAttribute('src', iframe.getAttribute('data-src') || '');
              }
              wrap.style.display = 'block';
              wrap.setAttribute('aria-hidden', 'false');
              btn.setAttribute('aria-expanded', 'true');
              btn.disabled = true;
            });
          })();
          </script>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div><!-- /info -->


  <!-- KATHA ZONE ════════════════════════════════════════════ -->
  <div
      class="vana-stage-katha"
      id="vana-stage-katha"
      aria-label="<?php echo esc_attr( vana_t( 'stage.katha_passages', $lang ) ?: 'Passagens Hari-Katha' ); ?>"
      aria-live="polite"
      hidden
  ></div>


  <!-- ╔═══════════════════════════════════════════════════════╗
       ║  🆕 ZONA MUTÁVEL — v6.0.0                            ║
       ║  Container controlado pelo VanaStateRouter.js.        ║
       ║  SSR renderiza o estado inicial; JS assume depois.    ║
       ╚═══════════════════════════════════════════════════════╝ -->
  <div
      class="vana-mutable-zone"
      id="vana-mutable-zone"
      data-state="<?php echo esc_attr( $mutable_zone_state ); ?>"
      data-event-key="<?php echo esc_attr( $current_event['event_key'] ); ?>"
      aria-live="polite"
      aria-label="<?php echo esc_attr( vana_t( 'stage.mutable_zone', $lang ) ?: 'Conteúdo contextual' ); ?>"
  >
    <?php
    /**
     * Slot interno — o VanaStateRouter.js injeta conteúdo aqui.
     *
     * Estados possíveis (data-state):
     *   "neutral"  → vazio ou hero placeholder
     *   "katha"    → passages + transcript (via REST)
     *   "gallery"  → grid de fotos do evento
     *   "sangha"   → momentos sangha
     *   "map"      → mapa expandido (futuro)
     *
     * SSR hint: renderiza um skeleton/placeholder conforme o estado
     * para evitar CLS (Cumulative Layout Shift).
     */
    ?>
    <?php if ( $mutable_zone_state === 'katha' && $stage_katha_dom_ref ) : ?>
      <!-- SSR hint: skeleton katha -->
      <div class="vana-mz__skeleton vana-mz__skeleton--katha" aria-hidden="true">
        <div class="vana-mz__skeleton-line" style="width:60%"></div>
        <div class="vana-mz__skeleton-line" style="width:90%"></div>
        <div class="vana-mz__skeleton-line" style="width:75%"></div>
      </div>
    <?php elseif ( $mutable_zone_state === 'neutral' ) : ?>
      <!-- SSR hint: zona vazia — JS pode popular depois -->
    <?php endif; ?>
  </div><!-- /vana-mutable-zone -->


  <!-- SEGMENTOS / CAPÍTULOS ═════════════════════════════════ -->
  <?php if ( ! empty( $stage_segments ) && $seg_provider === 'youtube' ) : ?>
    <div class="vana-stage-segments"
         role="navigation"
         aria-label="<?php echo esc_attr( vana_t( 'stage.chapters', $lang ) ); ?>">
      <div style="font-weight:900;color:var(--vana-muted);margin-bottom:10px;
                  font-size:.9rem;text-transform:uppercase;">
        <?php echo esc_html( vana_t( 'stage.chapters_label', $lang ) ); ?>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ( $stage_segments as $seg ) :
              if ( ! is_array( $seg ) ) continue;

              $ts_raw = $seg['timestamp_start'] ?? $seg['t'] ?? $seg['time_local'] ?? $seg['time'] ?? null;

              if ( is_int( $ts_raw ) || ( is_string( $ts_raw ) && ctype_digit( $ts_raw ) ) ) {
                  $ts_seconds = (int) $ts_raw;
                  $h = intdiv( $ts_seconds, 3600 );
                  $m = intdiv( $ts_seconds % 3600, 60 );
                  $s = $ts_seconds % 60;
                  $t_display = $h > 0
                      ? sprintf( '%d:%02d:%02d', $h, $m, $s )
                      : sprintf( '%d:%02d', $m, $s );
              } elseif ( is_string( $ts_raw ) && $ts_raw !== '' ) {
                  $t_display  = $ts_raw;
                  $ts_seconds = array_sum( array_map(
                      fn( $p, $i ) => (int) $p * [ 3600, 60, 1 ][ $i ],
                      array_reverse( explode( ':', $ts_raw ) ),
                      array_keys( array_reverse( explode( ':', $ts_raw ) ) )
                  ) );
              } else {
                  continue;
              }

              $st = Vana_Utils::pick_i18n_key( $seg, 'title', $lang );
              if ( $st === '' ) continue;
        ?>
          <button
            type="button"
            class="vana-seg-btn"
            data-vana-stage-seg="1"
            data-t="<?php echo esc_attr( $ts_seconds ); ?>"
            aria-label="<?php echo esc_attr( vana_t( 'stage.seg_jump', $lang ) . $t_display . ' — ' . $st ); ?>"
          >
            <strong><?php echo esc_html( $t_display ); ?></strong>
            <?php echo esc_html( $st ); ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</section>
