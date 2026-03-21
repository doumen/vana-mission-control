<?php
/**
 * Partial: Galeria de Gurudeva
 * Arquivo: templates/visit/parts/gallery.php
 * Version: 3.0.0
 *
 * Fonte: CPT vana_submission (post_status = publish, subtype = gurudeva_gallery)
 * Exibe fotos e vídeos enviados pelos devotos via [vana_oferenda_form]
 * marcados como "Galeria de Gurudeva".
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id, $active_day_date
 */
defined('ABSPATH') || exit;

// ── i18n ──────────────────────────────────────────────────────
$lbl_section   = vana_t('gallery.section', $lang);
$lbl_subtitle  = vana_t('gallery.subtitle', $lang);
$lbl_anon      = vana_t('sangha.anon', $lang);
$lbl_photo_b   = vana_t('sangha.badge.photo', $lang);
$lbl_video_b   = vana_t('sangha.badge.video', $lang);
$lbl_open_orig = vana_t('sangha.open_link', $lang);
$lbl_empty     = vana_t('gallery.empty', $lang);
$lbl_send      = vana_t('gallery.send', $lang);
$lbl_add_desc  = vana_t('gallery.add_desc', $lang);

// ── Query: submissions gurudeva_gallery aprovadas ─────────────
$gurudeva_submissions = new WP_Query([
    'post_type'      => 'vana_submission',
    'post_status'    => 'publish',
    'posts_per_page' => 96,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => '_visit_id',
            'value'   => $visit_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => '_subtype',
            'value'   => 'gurudeva_gallery',
            'compare' => '=',
        ],
    ],
    'orderby'        => 'date',
    'order'          => 'ASC',
]);

$total = $gurudeva_submissions->found_posts;

// URL do formulário de envio
$submit_url = esc_url(
    add_query_arg(['lang' => $lang], get_permalink($visit_id))
    . '#form-oferenda'
);
?>

<!-- ============================================================
     SEÇÃO GALERIA DE GURUDEVA
     ============================================================ -->
<section
  id="vana-section-gallery"
  class="vana-section vana-section--gurudeva-gallery"
  aria-labelledby="vana-gallery-heading"
  style="padding-top:8px;"
>

  <!-- Cabeçalho -->
  <div style="display:flex;align-items:center;justify-content:space-between;
              flex-wrap:wrap;gap:12px;margin-bottom:4px;">
    <h2 class="vana-section-title" id="vana-gallery-heading" style="margin-bottom:0;">
      <span class="dashicons dashicons-format-image"
            aria-hidden="true"
            style="font-size:1.6rem;width:auto;height:auto;
                   color:var(--vana-gold);margin-right:8px;
                   vertical-align:middle;"></span>
      <?php echo esc_html($lbl_section); ?>
      <?php if ($total > 0): ?>
        <span style="font-size:.85rem;font-weight:700;color:var(--vana-muted);
                     margin-left:10px;font-family:'Questrial',sans-serif;">
          (<?php echo (int) $total; ?>)
        </span>
      <?php endif; ?>
    </h2>

    <!-- Botão enviar fotos -->
    <a href="<?php echo esc_url($submit_url); ?>"
       style="display:inline-flex;align-items:center;gap:6px;
              padding:8px 16px;border-radius:8px;
              background:var(--vana-bg-soft);border:1px solid var(--vana-line);
              color:var(--vana-text);font-weight:700;font-size:.88rem;
              text-decoration:none;transition:background .2s;"
       onmouseover="this.style.background='var(--vana-gold-soft,rgba(255,217,6,.15))'"
       onmouseout="this.style.background='var(--vana-bg-soft)'"
    >
      <span class="dashicons dashicons-camera-alt"
            aria-hidden="true"
            style="font-size:15px;width:15px;height:15px;
                   color:var(--vana-pink);"></span>
      <?php echo esc_html($lbl_send); ?>
    </a>
  </div>

  <p style="color:var(--vana-muted);font-size:1.05rem;margin:0 0 28px;">
    <?php echo esc_html($lbl_subtitle); ?>
  </p>

  <?php if ($gurudeva_submissions->have_posts()): ?>

  <!-- ── Wall de cards ──────────────────────────────────────── -->
  <div class="vana-sangha-wall" role="list">

    <?php while ($gurudeva_submissions->have_posts()): $gurudeva_submissions->the_post();
      $sid      = get_the_ID();
      $name_raw = (string) get_post_meta($sid, '_sender_display_name', true);
      $name     = trim($name_raw) !== '' ? trim($name_raw) : $lbl_anon;
      $msg      = wp_strip_all_tags((string) get_post_meta($sid, '_message', true));
      $ts       = (int) get_post_meta($sid, '_submitted_at', true);
      $pub_city = (string) get_post_meta($sid, '_vana_public_user_city', true);
      $date_lbl = $ts ? wp_date('d/m/Y', $ts) : '';
      $initial  = mb_strtoupper(mb_substr($name, 0, 1));

      // ── Lê _media_items (schema v2) com fallback v1 ───────
      $media_items = [];
      $raw_items   = get_post_meta($sid, '_media_items', true);

      if (!empty($raw_items)) {
          $media_items = is_array($raw_items)
              ? $raw_items
              : (json_decode($raw_items, true) ?? []);
      } else {
          $img_v1 = esc_url((string) get_post_meta($sid, '_image_url',    true));
          $ext_v1 = (string)          get_post_meta($sid, '_external_url', true);
          if ($img_v1) $media_items[] = ['type' => 'image', 'url' => $img_v1, 'r2_key' => ''];
          if ($ext_v1) $media_items[] = ['type' => 'video', 'url' => $ext_v1, 'r2_key' => ''];
      }
    
      // ✅ Filtra apenas itens aprovados
      $media_items = array_values(array_filter(
      $media_items,
      fn($item) => ($item['status'] ?? 'approved') === 'approved'
      ));
      
      // ── Primeiro item de cada tipo para o card ────────────
      $first_image = '';
      $first_video = '';
      foreach ($media_items as $item) {
          if ($item['type'] === 'image' && $first_image === '') $first_image = esc_url($item['url']);
          if ($item['type'] === 'video' && $first_video === '') $first_video = $item['url'];
      }

      // ── Thumb de vídeo ────────────────────────────────────
      $video_thumb   = '';
      $provider_type = '';
      if ($first_video !== '') {
          if (preg_match(
              '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i',
              $first_video, $ym
          )) {
              $video_thumb   = "https://img.youtube.com/vi/{$ym[1]}/hqdefault.jpg";
              $provider_type = 'youtube';
          } elseif (strpos($first_video, 'drive.google.com') !== false) {
              if (preg_match('~\/d\/([a-zA-Z0-9_-]+)~', $first_video, $dm)) {
                  $video_thumb = "https://drive.google.com/thumbnail?id={$dm[1]}&sz=w400";
              }
              $provider_type = 'drive';
          } elseif (strpos($first_video, 'facebook.com') !== false || strpos($first_video, 'fb.watch') !== false) {
              $video_thumb   = 'FB_VIDEO';
              $provider_type = 'facebook';
          }
      }

      $has_media    = ($first_image || $video_thumb);
      $is_text_only = !$has_media;
      $badge_icon   = $first_video  !== '' ? 'dashicons-video-alt3'
                    : ($first_image !== '' ? 'dashicons-format-image' : 'dashicons-format-quote');
      $badge_label  = $first_video  !== '' ? $lbl_video_b
                    : ($first_image !== '' ? $lbl_photo_b
                    : vana_t('sangha.badge.message', $lang));

      $media_json   = esc_attr(wp_json_encode($media_items));
      $kicker       = vana_t('gallery.kicker', $lang);
      $moment_class = 'vana-moment vana-moment--gurudeva'
                    . ($is_text_only ? ' vana-moment--text-only' : '');
    ?>

    <article class="<?php echo esc_attr($moment_class); ?>" role="listitem">
      <button
        type="button"
        class="vana-moment-btn"
        data-vana-modal-open="1"
        data-vana-sangha-item="1"
        data-vana-gallery-item="1"
        data-kicker="<?php echo esc_attr($kicker); ?>"
        data-title="<?php echo esc_attr($name); ?>"
        data-message="<?php echo esc_attr($msg); ?>"
        data-image="<?php echo esc_attr($first_image); ?>"
        data-external-url="<?php echo esc_attr($first_video); ?>"
        data-media-items="<?php echo $media_json; ?>"
        aria-label="<?php echo esc_attr($kicker . ' — ' . $name); ?>"
      >
        <!-- Mídia -->
        <?php if ($first_image): ?>
          <div class="vana-moment-media vana-moment-media--gallery">
            <img src="<?php echo $first_image; ?>"
                 alt="<?php echo esc_attr(vana_t('gallery.photo_alt', $lang)); ?>"
                 loading="lazy">
            <?php if (count($media_items) > 1): ?>
              <span class="vana-media-count" aria-hidden="true">
                +<?php echo count($media_items) - 1; ?>
              </span>
            <?php endif; ?>
          </div>

        <?php elseif ($video_thumb): ?>
          <div class="vana-moment-media vana-moment-media--gallery"
               style="position:relative;background:#0f172a;aspect-ratio:16/9;
                      display:flex;align-items:center;justify-content:center;">
            <?php if ($video_thumb === 'FB_VIDEO'): ?>
              <div style="position:absolute;inset:0;background:#1877F2;
                          display:flex;align-items:center;justify-content:center;
                          flex-direction:column;color:#fff;">
                <span class="dashicons dashicons-facebook-alt"
                      style="font-size:40px;width:40px;height:40px;"></span>
                <small style="font-weight:bold;margin-top:5px;">Facebook Video</small>
              </div>
            <?php else: ?>
              <img src="<?php echo esc_url($video_thumb); ?>"
                   alt="Video thumb"
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
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Corpo do card -->
        <div class="vana-moment-inner">
          <div class="vana-moment-user">
            <div class="vana-moment-avatar vana-moment-avatar--gold" aria-hidden="true">
              <?php echo esc_html($initial); ?>
            </div>
            <div class="vana-moment-name"><?php echo esc_html($name); ?></div>
          </div>
          <?php if ($msg): ?>
            <div class="vana-moment-text"><?php echo esc_html($msg); ?></div>
          <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="vana-moment-footer">
          <span class="vana-moment-badge vana-moment-badge--gold">
            <span class="dashicons <?php echo esc_attr($badge_icon); ?>"
                  aria-hidden="true"></span>
            <?php echo esc_html($badge_label); ?>
          </span>
          <?php if ($pub_city): ?>
            <span style="display:flex;align-items:center;gap:5px;
                         color:var(--vana-muted);font-size:.8rem;font-weight:800;">
              <span class="dashicons dashicons-location"
                    style="font-size:14px;color:var(--vana-gold);"
                    aria-hidden="true"></span>
              <?php echo esc_html($pub_city); ?>
            </span>
          <?php else: ?>
            <span style="color:var(--vana-muted);font-size:.8rem;">
              <?php echo esc_html($date_lbl); ?>
            </span>
          <?php endif; ?>
        </div>

      </button>
    </article>

    <?php endwhile; wp_reset_postdata(); ?>
  </div>
  <!-- /.vana-sangha-wall -->

  <?php else: ?>

  <!-- Estado vazio -->
  <div style="text-align:center;padding:48px 24px;
              background:var(--vana-bg-soft);border:1px dashed var(--vana-line);
              border-radius:16px;color:var(--vana-muted);">
    <div style="font-size:3rem;margin-bottom:12px;" aria-hidden="true">🌸</div>
    <p style="font-size:1rem;margin:0 0 20px;">
      <?php echo esc_html($lbl_empty); ?>
    </p>
    <a href="<?php echo esc_url($submit_url); ?>"
       style="display:inline-block;padding:10px 24px;border-radius:8px;
              background:var(--vana-gold);color:#0f172a;
              font-weight:900;text-decoration:none;font-size:.95rem;">
      <?php echo esc_html($lbl_send); ?>
    </a>
  </div>

  <?php endif; ?>

  <!-- CTA inferior -->
  <?php if ($total > 0): ?>
  <div style="margin-top:28px;padding:20px 24px;border-radius:12px;
              background:var(--vana-bg-soft);border:1px solid var(--vana-line);
              display:flex;align-items:center;justify-content:space-between;
              gap:16px;flex-wrap:wrap;">
    <div>
      <div style="font-family:'Syne',sans-serif;font-weight:900;font-size:1rem;
                  color:var(--vana-text);margin-bottom:4px;">
        <span class="dashicons dashicons-camera-alt" aria-hidden="true"
              style="font-size:1.1rem;width:auto;height:auto;
                     color:var(--vana-gold);margin-right:6px;"></span>
        <?php echo esc_html(vana_t('gallery.add_photos', $lang)); ?>
      </div>
      <div style="color:var(--vana-muted);font-size:.9rem;line-height:1.5;">
        <?php echo esc_html($lbl_add_desc); ?>
      </div>
    </div>
    <a href="<?php echo esc_url($submit_url); ?>"
       style="display:inline-block;padding:12px 22px;border-radius:8px;
              background:var(--vana-gold);color:#0f172a;font-weight:900;
              text-decoration:none;font-size:.95rem;white-space:nowrap;
              transition:opacity .2s;"
       onmouseover="this.style.opacity='.85'"
       onmouseout="this.style.opacity='1'">
      <?php echo esc_html($lbl_send); ?>
    </a>
  </div>
  <?php endif; ?>

</section>
