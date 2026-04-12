<?php
/**
 * Partial: Orphan Extras (VODs, Photos, Sangha não vinculados a events)
 * Arquivo: templates/visit/parts/orphan-extras.php
 * Schema:  6.1 — fonte: variáveis soltas do _bootstrap.php
 *
 * Variáveis disponíveis via _bootstrap.php (extract):
 *   $has_orphan_vods, $orphan_vods
 *   $has_orphan_photos, $orphan_photos
 *   $has_orphan_sangha, $orphan_sangha
 *   $lang, $visit_id
 */
defined('ABSPATH') || exit;

// ── Compat shim: _bootstrap exporta vars soltas; este partial usa $tour[] ──
if ( ! isset( $tour ) || ! is_array( $tour ) ) {
    $tour = [
        'has_orphan_vods'   => ! empty( $has_orphan_vods )   ? $has_orphan_vods   : false,
        'has_orphan_photos' => ! empty( $has_orphan_photos ) ? $has_orphan_photos : false,
        'has_orphan_sangha' => ! empty( $has_orphan_sangha ) ? $has_orphan_sangha : false,
        'orphan_vods'       => $orphan_vods   ?? [],
        'orphan_photos'     => $orphan_photos ?? [],
        'orphan_sangha'     => $orphan_sangha ?? [],
    ];
}

// Nada a fazer se não há órfãos
if (
    empty( $tour['has_orphan_vods'] )
    && empty( $tour['has_orphan_photos'] )
    && empty( $tour['has_orphan_sangha'] )
) {
    return;
}

// ── i18n ──────────────────────────────────────────────────────
$lbl_orphan_vods    = vana_t( 'orphan.vods.section',   $lang );
$lbl_orphan_photos  = vana_t( 'orphan.photos.section', $lang );
$lbl_orphan_sangha  = vana_t( 'orphan.sangha.section', $lang );
$lbl_orphan_sub     = vana_t( 'orphan.subtitle',       $lang );
?>

<?php // ═══════════════════════════════════════════════════════════
      //  SEÇÃO 1: VODs Órfãos
      // ═══════════════════════════════════════════════════════════
if ( ! empty( $tour['orphan_vods'] ) ): ?>

<section
  id="vana-section-orphan-vods"
  class="vana-section vana-section--orphan-vods"
  aria-labelledby="vana-orphan-vods-heading"
  style="padding-top:8px;"
>
  <h2 class="vana-section-title" id="vana-orphan-vods-heading">
    <span class="dashicons dashicons-video-alt3"
          aria-hidden="true"
          style="font-size:1.6rem;width:auto;height:auto;
                 color:var(--vana-gold);margin-right:8px;
                 vertical-align:middle;"></span>
    <?php echo esc_html( $lbl_orphan_vods ); ?>
    <span style="font-size:.85rem;font-weight:700;color:var(--vana-muted);
                 margin-left:10px;font-family:'Questrial',sans-serif;">
      (<?php echo count( $tour['orphan_vods'] ); ?>)
    </span>
  </h2>
  <p style="color:var(--vana-muted);font-size:1.05rem;margin:-10px 0 28px;">
    <?php echo esc_html( $lbl_orphan_sub ); ?>
  </p>

  <div class="vana-grid" role="list"
       aria-label="<?php echo esc_attr( $lbl_orphan_vods ); ?>">

    <?php foreach ( $tour['orphan_vods'] as $idx => $vod ):
      if ( ! is_array( $vod ) ) continue;

      $vod_title  = Vana_Utils::pick_i18n_key( $vod, 'title', $lang );
      $provider   = $vod['provider'] ?? 'youtube';
      $video_id   = $vod['video_id'] ?? '';
      $thumb_url  = $vod['thumb_url'] ?? '';
      $duration_s = $vod['duration_s'] ?? null;

      // Thumb fallback
      if ( $thumb_url === '' && $provider === 'youtube' && $video_id ) {
          $thumb_url = 'https://i.ytimg.com/vi/' . esc_attr( $video_id ) . '/hqdefault.jpg';
      }

      // Duração formatada
      $duration_label = '';
      if ( $duration_s && is_numeric( $duration_s ) ) {
          $h = floor( $duration_s / 3600 );
          $m = floor( ( $duration_s % 3600 ) / 60 );
          $s = $duration_s % 60;
          $duration_label = $h > 0
              ? sprintf( '%d:%02d:%02d', $h, $m, $s )
              : sprintf( '%d:%02d', $m, $s );
      }

      // URL direta (YouTube → watch, outros → url campo)
      $watch_url = '';
      if ( $provider === 'youtube' && $video_id ) {
          $watch_url = 'https://www.youtube.com/watch?v=' . urlencode( $video_id );
      } elseif ( ! empty( $vod['url'] ) ) {
          $watch_url = $vod['url'];
      }

      // Segments
      $segments_count = is_array( $vod['segments'] ?? null ) ? count( $vod['segments'] ) : 0;
    ?>

    <div class="vana-card" role="listitem">
      <a
        href="<?php echo esc_url( $watch_url ); ?>"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="<?php echo esc_attr( $vod_title ); ?>"
      >
        <!-- Thumbnail -->
        <div class="vana-card__media">
          <?php if ( $thumb_url ): ?>
            <img
              src="<?php echo esc_url( $thumb_url ); ?>"
              alt="<?php echo esc_attr( $vod_title ); ?>"
              loading="lazy" decoding="async"
              width="480" height="270"
            >
          <?php else: ?>
            <div style="position:absolute;inset:0;background:var(--vana-hero-gradient);
                        display:flex;align-items:center;justify-content:center;"
                 aria-hidden="true">
              <span class="dashicons dashicons-video-alt3"
                    style="font-size:2.5rem;width:auto;height:auto;
                           color:var(--vana-muted);opacity:.5;"></span>
            </div>
          <?php endif; ?>

          <!-- Play overlay -->
          <div class="vana-card__play" aria-hidden="true">
            <span class="dashicons dashicons-controls-play"
                  style="font-size:1.3rem;width:auto;height:auto;"></span>
          </div>

          <!-- Badge órfão -->
          <div style="position:absolute;top:10px;left:10px;
                      background:var(--vana-orange,#f59e0b);color:#111;
                      padding:3px 10px;border-radius:20px;font-weight:900;
                      font-size:.7rem;text-transform:uppercase;z-index:2;"
               aria-hidden="true">
            <?php echo esc_html( $lang === 'en' ? 'Extra' : 'Extra' ); ?>
          </div>

          <!-- Duração -->
          <?php if ( $duration_label ): ?>
            <div style="position:absolute;bottom:8px;right:8px;
                        background:rgba(0,0,0,.72);color:#fff;
                        padding:2px 8px;border-radius:4px;font-size:.8rem;
                        font-weight:700;font-family:monospace;z-index:2;">
              <?php echo esc_html( $duration_label ); ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="vana-card__body">
          <p class="vana-card__name">
            <?php echo esc_html( $vod_title ?: vana_t( 'vod.untitled', $lang ) ); ?>
          </p>

          <div style="display:flex;align-items:center;gap:10px;margin-top:10px;
                      color:var(--vana-muted);font-size:.82rem;font-weight:700;">
            <?php
            $provider_labels = [
                'youtube'   => [ 'icon' => 'dashicons-youtube',      'color' => '#dc2626', 'label' => 'YouTube' ],
                'facebook'  => [ 'icon' => 'dashicons-facebook-alt', 'color' => '#1877f2', 'label' => 'Facebook' ],
                'instagram' => [ 'icon' => 'dashicons-instagram',    'color' => '#e1306c', 'label' => 'Instagram' ],
                'drive'     => [ 'icon' => 'dashicons-cloud',        'color' => '#0f9d58', 'label' => 'Drive' ],
            ];
            if ( isset( $provider_labels[ $provider ] ) ):
                $pl = $provider_labels[ $provider ];
            ?>
              <span style="display:inline-flex;align-items:center;gap:4px;">
                <span class="dashicons <?php echo esc_attr( $pl['icon'] ); ?>"
                      aria-hidden="true"
                      style="font-size:14px;width:14px;height:14px;
                             color:<?php echo esc_attr( $pl['color'] ); ?>;"></span>
                <?php echo esc_html( $pl['label'] ); ?>
              </span>
            <?php endif; ?>

            <?php if ( $segments_count > 0 ): ?>
              <span>·</span>
              <span>
                <?php printf(
                    esc_html( _n( '%d capítulo', '%d capítulos', $segments_count, 'vana-mission-control' ) ),
                    $segments_count
                ); ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </a>
    </div>

    <?php endforeach; ?>
  </div>
</section>

<?php endif; // orphan_vods ?>


<?php // ═══════════════════════════════════════════════════════════
      //  SEÇÃO 2: Fotos Órfãs
      // ═══════════════════════════════════════════════════════════
if ( ! empty( $tour['orphan_photos'] ) ): ?>

<section
  id="vana-section-orphan-photos"
  class="vana-section vana-section--orphan-photos"
  aria-labelledby="vana-orphan-photos-heading"
  style="padding-top:8px;"
>
  <h2 class="vana-section-title" id="vana-orphan-photos-heading">
    <span class="dashicons dashicons-format-image"
          aria-hidden="true"
          style="font-size:1.6rem;width:auto;height:auto;
                 color:var(--vana-gold);margin-right:8px;
                 vertical-align:middle;"></span>
    <?php echo esc_html( $lbl_orphan_photos ); ?>
    <span style="font-size:.85rem;font-weight:700;color:var(--vana-muted);
                 margin-left:10px;font-family:'Questrial',sans-serif;">
      (<?php echo count( $tour['orphan_photos'] ); ?>)
    </span>
  </h2>

  <div class="vana-sangha-wall" role="list">

    <?php foreach ( $tour['orphan_photos'] as $photo ):
      if ( ! is_array( $photo ) ) continue;

      $photo_url   = $photo['url']      ?? $photo['src'] ?? '';
      $photo_thumb = $photo['thumb_url'] ?? $photo_url;
      $photo_cap   = Vana_Utils::pick_i18n_key( $photo, 'caption', $lang );
      $photo_alt   = $photo_cap ?: ( $lang === 'en' ? 'Visit photo' : 'Foto da visita' );
    ?>

    <article class="vana-moment vana-moment--gurudeva" role="listitem">
      <button
        type="button"
        class="vana-moment-btn"
        data-vana-modal-open="1"
        data-vana-sangha-item="1"
        data-vana-gallery-item="1"
        data-kicker="<?php echo esc_attr( $lang === 'en' ? 'Extra Photo' : 'Foto Extra' ); ?>"
        data-title="<?php echo esc_attr( $photo_cap ); ?>"
        data-message=""
        data-image="<?php echo esc_attr( $photo_url ); ?>"
        data-external-url=""
        data-media-items="<?php echo esc_attr( wp_json_encode( [
            [ 'type' => 'image', 'url' => $photo_url ],
        ] ) ); ?>"
        aria-label="<?php echo esc_attr( $photo_alt ); ?>"
      >
        <div class="vana-moment-media vana-moment-media--gallery">
          <img src="<?php echo esc_url( $photo_thumb ); ?>"
               alt="<?php echo esc_attr( $photo_alt ); ?>"
               loading="lazy">
          <!-- Badge extra -->
          <span style="position:absolute;top:8px;left:8px;
                       background:var(--vana-orange,#f59e0b);color:#111;
                       padding:2px 8px;border-radius:12px;font-size:.7rem;
                       font-weight:900;text-transform:uppercase;">
            Extra
          </span>
        </div>

        <?php if ( $photo_cap ): ?>
        <div class="vana-moment-inner">
          <div class="vana-moment-text"><?php echo esc_html( $photo_cap ); ?></div>
        </div>
        <?php endif; ?>

        <div class="vana-moment-footer">
          <span class="vana-moment-badge vana-moment-badge--gold">
            <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
            <?php echo esc_html( $lang === 'en' ? 'Photo' : 'Foto' ); ?>
          </span>
        </div>
      </button>
    </article>

    <?php endforeach; ?>
  </div>
</section>

<?php endif; // orphan_photos ?>


<?php // ═══════════════════════════════════════════════════════════
      //  SEÇÃO 3: Sangha Órfão
      // ═══════════════════════════════════════════════════════════
if ( ! empty( $tour['orphan_sangha'] ) ): ?>

<section
  id="vana-section-orphan-sangha"
  class="vana-section vana-section--orphan-sangha"
  aria-labelledby="vana-orphan-sangha-heading"
  style="padding-top:8px;"
>
  <h2 class="vana-section-title" id="vana-orphan-sangha-heading">
    <span class="dashicons dashicons-groups"
          aria-hidden="true"
          style="font-size:1.6rem;width:auto;height:auto;
                 color:var(--vana-pink);margin-right:8px;
                 vertical-align:middle;"></span>
    <?php echo esc_html( $lbl_orphan_sangha ); ?>
    <span style="font-size:.85rem;font-weight:700;color:var(--vana-muted);
                 margin-left:10px;font-family:'Questrial',sans-serif;">
      (<?php echo count( $tour['orphan_sangha'] ); ?>)
    </span>
  </h2>

  <div class="vana-sangha-wall" role="list">

    <?php foreach ( $tour['orphan_sangha'] as $s ):
      if ( ! is_array( $s ) ) continue;

      $s_name    = Vana_Utils::pick_i18n_key( $s, 'name',    $lang ) ?: vana_t( 'sangha.anon', $lang );
      $s_msg     = Vana_Utils::pick_i18n_key( $s, 'message', $lang );
      $s_image   = $s['image_url'] ?? $s['url'] ?? '';
      $s_video   = $s['video_url'] ?? '';
      $s_initial = mb_strtoupper( mb_substr( $s_name, 0, 1 ) );

      // Media items para o modal
      $s_media = [];
      if ( $s_image ) $s_media[] = [ 'type' => 'image', 'url' => $s_image ];
      if ( $s_video ) $s_media[] = [ 'type' => 'video', 'url' => $s_video ];

      $is_text_only = empty( $s_image ) && empty( $s_video );
      $moment_class = 'vana-moment' . ( $is_text_only ? ' vana-moment--text-only' : '' );

      // Badge
      if ( $s_video ) {
          $badge_icon  = 'dashicons-video-alt3';
          $badge_label = vana_t( 'sangha.badge.video', $lang );
      } elseif ( $s_image ) {
          $badge_icon  = 'dashicons-format-image';
          $badge_label = vana_t( 'sangha.badge.photo', $lang );
      } else {
          $badge_icon  = 'dashicons-format-quote';
          $badge_label = vana_t( 'sangha.badge.message', $lang );
      }

      // Thumb de vídeo
      $video_thumb = '';
      if ( $s_video ) {
          if ( preg_match(
              '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i',
              $s_video, $ym
          ) ) {
              $video_thumb = "https://img.youtube.com/vi/{$ym[1]}/hqdefault.jpg";
          }
      }
    ?>

    <article class="<?php echo esc_attr( $moment_class ); ?>" role="listitem">
      <button
        type="button"
        class="vana-moment-btn"
        data-vana-modal-open="1"
        data-vana-sangha-item="1"
        data-kicker="<?php echo esc_attr( $lang === 'en' ? 'Sangha Extra' : 'Sangha Extra' ); ?>"
        data-title="<?php echo esc_attr( $s_name ); ?>"
        data-message="<?php echo esc_attr( $s_msg ); ?>"
        data-image="<?php echo esc_attr( $s_image ); ?>"
        data-external-url="<?php echo esc_attr( $s_video ); ?>"
        data-media-items="<?php echo esc_attr( wp_json_encode( $s_media ) ); ?>"
        aria-label="<?php echo esc_attr( $s_name ); ?>"
      >
        <div class="vana-moment-inner">
          <div class="vana-moment-user">
            <div class="vana-moment-avatar" aria-hidden="true">
              <?php echo esc_html( $s_initial ); ?>
            </div>
            <div class="vana-moment-name"><?php echo esc_html( $s_name ); ?></div>
          </div>
          <?php if ( $s_msg ): ?>
            <div class="vana-moment-text"><?php echo esc_html( $s_msg ); ?></div>
          <?php endif; ?>
        </div>

        <?php if ( $s_image ): ?>
          <div class="vana-moment-media">
            <img src="<?php echo esc_url( $s_image ); ?>"
                 alt="<?php echo esc_attr( $s_name ); ?>"
                 loading="lazy">
          </div>
        <?php elseif ( $video_thumb ): ?>
          <div class="vana-moment-media"
               style="position:relative;background:#0f172a;aspect-ratio:16/9;
                      display:flex;align-items:center;justify-content:center;">
            <img src="<?php echo esc_url( $video_thumb ); ?>" alt="Video"
                 style="width:100%;height:100%;object-fit:cover;opacity:.85;"
                 loading="lazy">
            <div style="position:absolute;top:50%;left:50%;
                        transform:translate(-50%,-50%);
                        width:52px;height:52px;border-radius:50%;
                        background:rgba(0,0,0,.65);border:2px solid rgba(255,217,6,.7);
                        display:flex;align-items:center;justify-content:center;
                        color:var(--vana-gold);font-size:22px;"
                 aria-hidden="true">
              <span class="dashicons dashicons-controls-play"></span>
            </div>
          </div>
        <?php endif; ?>

        <div class="vana-moment-footer">
          <span class="vana-moment-badge">
            <span class="dashicons <?php echo esc_attr( $badge_icon ); ?>"
                  aria-hidden="true"></span>
            <?php echo esc_html( $badge_label ); ?>
          </span>
        </div>
      </button>
    </article>

    <?php endforeach; ?>
  </div>
</section>

<?php endif; // orphan_sangha ?>
