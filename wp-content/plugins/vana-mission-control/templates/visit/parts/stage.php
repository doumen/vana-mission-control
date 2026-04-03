<?php
/**
 * Partial: Stage (Palco Principal) — schema 5.1
 * Arquivo: templates/visit/parts/stage.php
 *
 * Requer (do _bootstrap.php):
 *   $lang, $visit_id, $visit_tz, $visit_city_ref
 *   $active_day, $active_day_date, $active_event, $visit_status
 *
 * @since 5.1.1
 */
defined( 'ABSPATH' ) || exit;

// ── 1. Normaliza para schema 5.1 a partir de $active_event ─
$_evt       = is_array( $active_event ) ? $active_event : [];
// Prefer schema 6.1 canonical `vods` on the event, fall back to legacy `media.vods`.
$_vods      = is_array( $_evt['vods'] ?? null )
         ? $_evt['vods']
         : ( is_array( $_evt['media']['vods'] ?? null )
           ? $_evt['media']['vods']
           : [] );
$_gallery   = is_array( $_evt['media']['gallery']         ?? null ) ? $_evt['media']['gallery']         : ( $active_day['gallery']          ?? [] );
$_sangha    = is_array( $_evt['media']['sangha_moments']  ?? null ) ? $_evt['media']['sangha_moments']  : ( $active_day['sangha_moments']   ?? [] );
$_vod_first = $_vods[0] ?? [];

$current_event = vana_normalize_event([
    'active_vod'   => $_vod_first,
    'vod_list'     => array_slice( $_vods, 1 ),
    'hero'         => $active_day['hero']  ?? [],
    'gallery'      => $_gallery,
    'sangha'       => $_sangha,
    'event_key'    => $_evt['event_key']   ?? '',
    'title_pt'     => Vana_Utils::pick_i18n_key( $_vod_first, 'title', 'pt' ),
    'title_en'     => Vana_Utils::pick_i18n_key( $_vod_first, 'title', 'en' ),
    'time_start'   => $_evt['time_start']  ?? ( $active_day['date_local'] ?? '' ),
    'status'       => $_evt['status']      ?? ( $visit_status ?? '' ),
]);

// ── 2. Resolve conteúdo via hierarquia ────────────────────
$stage = vana_get_stage_content( $current_event );

// ── 3. Metadados de apoio ─────────────────────────────────
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
$stage_segments = is_array( ( $stage['data'] ?? [] )['segments'] ?? null )
                  ? $stage['data']['segments']
                  : [];
$stage_loc      = is_array( ( $stage['data'] ?? [] )['location'] ?? null )
                  ? $stage['data']['location']
                  : ( is_array( $active_day['hero']['location'] ?? null )
                      ? $active_day['hero']['location']
                      : [] );

// ── 4. Mapa ───────────────────────────────────────────────
$lat        = is_numeric( $stage_loc['lat'] ?? '' ) ? (string) $stage_loc['lat'] : '';
$lng        = is_numeric( $stage_loc['lng'] ?? '' ) ? (string) $stage_loc['lng'] : '';
$has_coords = ( $lat !== '' && $lng !== '' );
$maps_embed = $has_coords
    ? 'https://maps.google.com/maps?q=' . rawurlencode( "$lat,$lng" )
      . '&hl=' . ( $lang === 'en' ? 'en' : 'pt' ) . '&z=15&output=embed'
    : '';

// IDs únicos para o mapa
$map_btn_id    = 'vanaLoadMapBtn_'  . esc_attr( $active_day_date );
$map_wrap_id   = 'vanaMapWrap_'     . esc_attr( $active_day_date );
$map_iframe_id = 'vanaMapIframe_'   . esc_attr( $active_day_date );

// ── 5. Live badge ─────────────────────────────────────────
$has_live = false;
foreach ( is_array( $active_day['schedule'] ?? null ) ? $active_day['schedule'] : [] as $_item ) {
    if ( is_array( $_item ) && ( $_item['status'] ?? '' ) === 'live' ) {
        $has_live = true;
        break;
    }
}

// ── 6. Detecção de modo ──────────────────────────────────
// Modo neutro: sem evento selecionado ainda
// (usado na carga inicial ou quando não há eventos neste dia)
$is_neutral_mode = empty( $current_event['event_key'] ) || empty( $stage['type'] );
$is_transitioning = false; // será controlado pelo JS em Fase E
?>

<section
    class="vana-stage <?php echo $is_neutral_mode ? 'vana-stage--neutral' : ''; ?>"
    id="vana-stage"
    data-event-key="<?php echo esc_attr( $current_event['event_key'] ); ?>"
    aria-label="<?php echo esc_attr( vana_t( 'stage.aria', $lang ) ); ?>"
    data-is-neutral="<?php echo $is_neutral_mode ? '1' : '0'; ?>"
    data-transitioning="<?php echo $is_transitioning ? '1' : '0'; ?>"
>

  <!-- ══════════════════════════════════════════════════════
       PLAYER
       ══════════════════════════════════════════════════════ -->
  <div class="vana-stage-video">

    <?php if ( $stage['type'] === 'vod' ) : ?>

      <?php echo vana_render_vod_player( $stage['data'], $lang ); ?>
      <?php if ( $stage['live'] ) : ?>
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
            alt="<?php echo esc_attr( $photo['caption'] ?? '' ); ?>"
            style="width:100%;height:100px;object-fit:cover;border-radius:8px;"
            loading="lazy"
          />
        <?php endforeach; ?>
      </div>

    <?php elseif ( $stage['type'] === 'sangha' ) : ?>

      <div class="vana-stage-placeholder" style="padding:40px;text-align:center;">
        <blockquote style="font-size:1.2rem;line-height:1.7;color:var(--vana-text);
                           margin:0 0 16px;font-style:italic;">
          <?php echo nl2br( esc_html( $stage['data']['text'] ?? '' ) ); ?>
        </blockquote>
        <cite style="color:var(--vana-muted);font-size:.95rem;">
          — <?php echo esc_html( $stage['data']['author'] ?? '' ); ?>
        </cite>
      </div>

    <?php else : ?>

      <!-- Placeholder diferenciado: live vs. sem mídia -->
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

  </div>
  <!-- /player -->

  <!-- ══════════════════════════════════════════════════════
       INFO
       ══════════════════════════════════════════════════════ -->
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

    <!-- Botões: Share + Open Hari-Katha -->
    <?php if ( ! $is_neutral_mode ): ?>
      <div class="vana-stage-actions" style="display:flex;gap:10px;margin:12px 0;flex-wrap:wrap;">
        <button
          type="button"
          class="vana-stage-action-btn vana-stage-action-btn--share"
          id="vana-stage-share-btn"
          aria-label="<?php echo esc_attr( vana_t( 'stage.share', $lang ) ?: 'Compartilhar' ); ?>"
          title="<?php echo esc_attr( vana_t( 'stage.share', $lang ) ?: 'Compartilhar evento' ); ?>"
        >
          <span aria-hidden="true">📤</span>
          <span><?php echo esc_html( vana_t( 'stage.share', $lang ) ?: 'Compartilhar' ); ?></span>
        </button>

        <button
          type="button"
          class="vana-stage-action-btn vana-stage-action-btn--hk"
          id="vana-stage-hk-btn"
          data-drawer="vana-hk-drawer"
          aria-label="<?php echo esc_attr( vana_t( 'stage.open_hk', $lang ) ?: 'Abrir Hari-Katha' ); ?>"
          title="<?php echo esc_attr( vana_t( 'stage.open_hk', $lang ) ?: 'Abrir Hari-Katha do evento' ); ?>"
        >
          <span aria-hidden="true">🙏</span>
          <span><?php echo esc_html( vana_t( 'stage.open_hk', $lang ) ?: 'Hari-Katha' ); ?></span>
        </button>
      </div>
    <?php endif; ?>

    <?php if ( $stage_desc ) : ?>
      <div class="vana-stage-desc"
           style="color:var(--vana-muted);line-height:1.6;font-size:1.05rem;">
        <?php echo nl2br( esc_html( $stage_desc ) ); ?>
      </div>
    <?php endif; ?>

    <!-- Localização + Mapa lazy -->
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

  </div>
  <!-- /info -->

  <!-- ══════════════════════════════════════════════════════
       SEGMENTOS / CAPÍTULOS (YouTube only)
       ══════════════════════════════════════════════════════ -->
  <?php
  $seg_provider = $resolved['provider'] ?? ( $stage['type'] === 'vod'
      ? ( vana_stage_resolve_media( $stage['data'] )['provider'] ?? '' )
      : '' );
  if ( ! empty( $stage_segments ) && $seg_provider === 'youtube' ) :
  ?>
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
              $t  = sanitize_text_field( (string) ( $seg['t'] ?? $seg['time_local'] ?? $seg['time'] ?? '' ) );
              $st = Vana_Utils::pick_i18n_key( $seg, 'title', $lang );
              if ( $t === '' || $st === '' ) continue;
        ?>
          <button
            type="button"
            class="vana-seg-btn"
            data-vana-stage-seg="1"
            data-t="<?php echo esc_attr( $t ); ?>"
            aria-label="<?php echo esc_attr( vana_t( 'stage.seg_jump', $lang ) . $t . ' — ' . $st ); ?>"
          >
            <strong><?php echo esc_html( $t ); ?></strong>
            <?php echo esc_html( $st ); ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</section>
