cat > templates/visit/parts/stage-fragment.php << 'PHPEOF'
<?php
/**
 * Fragment: Stage (HTMX)
 * Arquivo: templates/visit/parts/stage-fragment.php
 * Version: 1.0.0
 *
 * Consumido por: Vana_REST_Stage_Fragment::handle()
 * NÃO incluir diretamente — sempre via REST /vana/v1/stage-fragment
 *
 * Params (já sanitizados pelo endpoint):
 *   $_GET['visit_id']  int
 *   $_GET['item_id']   int
 *   $_GET['item_type'] string  vod|gallery|sangha
 *   $_GET['lang']      string  pt|en
 */
defined('ABSPATH') || exit;

// ── 0. PARAMS ─────────────────────────────────────────────────
$visit_id  = (int)    ($_GET['visit_id']  ?? 0);
$item_id   = (int)    ($_GET['item_id']   ?? 0);
$item_type = (string) ($_GET['item_type'] ?? 'vod');
$lang      = (string) ($_GET['lang']      ?? 'pt');

// ── 1. CARREGAR ITEM DO ACERVO ────────────────────────────────
// Estratégia: post_meta _vana_katha_data (Hari-Katha CPT) ou
//             fallback para _media_items do vana_submission.
$item_post = get_post($item_id);
if (! $item_post) {
    echo '<div class="vana-stage-fragment-error">'
       . esc_html__('Item não encontrado.', 'vana-mc')
       . '</div>';
    return;
}

// Tenta ler como Hari-Katha primeiro
$katha_data = get_post_meta($item_id, '_vana_katha_data', true);
$stage_item = is_array($katha_data) ? $katha_data : [];

// Fallback: _media_items (vana_submission)
if (empty($stage_item)) {
    $media_items = get_post_meta($item_id, '_media_items', true);
    $stage_item  = is_array($media_items) && !empty($media_items)
                   ? $media_items[0]
                   : [];
}

// Último fallback: constrói item a partir dos campos do post
if (empty($stage_item)) {
    $stage_item = [
        'title'       => ['pt' => $item_post->post_title, 'en' => $item_post->post_title],
        'description' => ['pt' => $item_post->post_excerpt, 'en' => $item_post->post_excerpt],
        'provider'    => get_post_meta($item_id, '_provider', true) ?: '',
        'video_id'    => get_post_meta($item_id, '_video_id', true) ?: '',
        'url'         => get_post_meta($item_id, '_external_url', true) ?: '',
        'segments'    => [],
        'location'    => [],
    ];
}

// ── 2. RESOLVER TEXTOS ────────────────────────────────────────
$stage_title    = Vana_Utils::pick_i18n_key($stage_item, 'title',       $lang);
$stage_desc     = Vana_Utils::pick_i18n_key($stage_item, 'description', $lang);
$stage_segments = is_array($stage_item['segments'] ?? null) ? $stage_item['segments'] : [];
$stage_loc      = is_array($stage_item['location']  ?? null) ? $stage_item['location']  : [];
$stage_loc_name = (string) ($stage_loc['name'] ?? '');
$stage_lat      = (string) ($stage_loc['lat']  ?? '');
$stage_lng      = (string) ($stage_loc['lng']  ?? '');

// ── 3. RESOLVER MÍDIA ─────────────────────────────────────────
$resolved       = vana_stage_resolve_media($stage_item);
$stage_provider = (string) ($resolved['provider'] ?? '');
$stage_video_id = (string) ($resolved['video_id'] ?? '');
$stage_url      = (string) ($resolved['url']      ?? '');

// ── 4. MAPA ───────────────────────────────────────────────────
$lat        = is_numeric($stage_lat) ? $stage_lat : '';
$lng        = is_numeric($stage_lng) ? $stage_lng : '';
$has_coords = ($lat !== '' && $lng !== '');
$maps_embed = $has_coords
    ? 'https://maps.google.com/maps?q=' . rawurlencode($lat . ',' . $lng)
      . '&hl=' . ($lang === 'en' ? 'en' : 'pt') . '&z=15&output=embed'
    : '';

$uid           = 'frag_' . $item_id . '_' . wp_rand(100, 999);
$map_btn_id    = 'vanaLoadMapBtn_'  . $uid;
$map_wrap_id   = 'vanaMapWrap_'     . $uid;
$map_iframe_id = 'vanaMapIframe_'   . $uid;

// ── 5. i18n ───────────────────────────────────────────────────
$lbl_class       = vana_t('stage.class',       $lang);
$lbl_recording   = vana_t('stage.recording',   $lang);
$lbl_drive_cta   = vana_t('stage.drive_cta',   $lang);
$lbl_fb_title    = vana_t('stage.fb_title',    $lang);
$lbl_fb_fallback = vana_t('stage.fb_fallback', $lang);
$lbl_fb_open     = vana_t('stage.fb_open',     $lang);
$lbl_copy_link   = vana_t('stage.copy_link',   $lang);
$lbl_ig_title    = vana_t('stage.ig_title',    $lang);
$lbl_ig_sub      = vana_t('stage.ig_sub',      $lang);
$lbl_ig_open     = vana_t('stage.ig_open',     $lang);
$lbl_generic_cta = vana_t('stage.generic_cta', $lang);
$lbl_empty       = vana_t('stage.empty',       $lang);
$lbl_map_load    = vana_t('stage.map_load',    $lang);
$lbl_map_aria    = vana_t('stage.map_aria',    $lang);
$lbl_chapters    = vana_t('stage.chapters',    $lang);
$lbl_chapters_lbl= vana_t('stage.chapters_label', $lang);
$lbl_seg_jump    = vana_t('stage.seg_jump',    $lang);
?>

<section
  class="vana-stage vana-stage--fragment"
  data-item-id="<?php echo esc_attr($item_id); ?>"
  data-item-type="<?php echo esc_attr($item_type); ?>"
  aria-label="<?php echo esc_attr(vana_t('stage.aria', $lang)); ?>"
>

  <!-- ── PLAYER ──────────────────────────────────────────────── -->
  <div class="vana-stage-video">

    <?php if ($stage_provider === 'youtube' && $stage_video_id): ?>

      <iframe
        id="vanaStageIframe"
        src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($stage_video_id); ?>?rel=0&amp;autoplay=1&amp;enablejsapi=1&amp;origin=<?php echo esc_attr( home_url() ); ?>"
        title="<?php echo esc_attr($stage_title ?: $lbl_class); ?>"
        style="position:absolute;inset:0;width:100%;height:100%;border:0;"
        allowfullscreen
        allow="autoplay"
        loading="lazy"
      ></iframe>

    <?php elseif ($stage_provider === 'drive' && $stage_url):
        $fid = vana_drive_file_id($stage_url);
        if ($fid): ?>

        <iframe
          id="vanaStageIframe"
          src="https://drive.google.com/file/d/<?php echo esc_attr($fid); ?>/preview"
          title="<?php echo esc_attr($stage_title ?: 'Google Drive'); ?>"
          style="position:absolute;inset:0;width:100%;height:100%;border:0;"
          allow="autoplay"
          loading="lazy"
        ></iframe>

      <?php else: ?>
        <div class="vana-stage-placeholder">
          <a href="<?php echo esc_url($stage_url); ?>" target="_blank" rel="noopener" class="vana-stage-cta">
            <?php echo esc_html($lbl_drive_cta); ?>
          </a>
        </div>
      <?php endif; ?>

    <?php elseif ($stage_provider === 'facebook' && $stage_url):
        $fb_embed = 'https://www.facebook.com/plugins/video.php?href='
                    . rawurlencode(esc_url_raw($stage_url)) . '&show_text=0&width=1200'; ?>

      <iframe
        src="<?php echo esc_url($fb_embed); ?>"
        title="<?php echo esc_attr($lbl_fb_title); ?>"
        style="position:absolute;inset:0;width:100%;height:100%;border:0;"
        scrolling="no"
        allow="autoplay;clipboard-write;encrypted-media;picture-in-picture;web-share"
        allowfullscreen referrerpolicy="origin-when-cross-origin"
      ></iframe>

    <?php elseif ($stage_provider === 'instagram' && $stage_url): ?>

      <div class="vana-stage-placeholder" style="background:var(--vana-bg);">
        <div style="font-weight:900;color:var(--vana-text);font-size:1.3rem;margin-bottom:10px;font-family:'Syne',sans-serif;">
          <?php echo esc_html($lbl_ig_title); ?>
        </div>
        <div style="color:var(--vana-muted);margin-bottom:20px;"><?php echo esc_html($lbl_ig_sub); ?></div>
        <a href="<?php echo esc_url($stage_url); ?>" target="_blank" rel="noopener"
           style="display:inline-block;background:var(--vana-pink);color:#fff;padding:12px 24px;border-radius:8px;font-weight:900;text-decoration:none;font-size:1.1rem;">
          <?php echo esc_html($lbl_ig_open); ?>
        </a>
      </div>

    <?php elseif ($stage_url): ?>

      <div class="vana-stage-placeholder">
        <a href="<?php echo esc_url($stage_url); ?>" target="_blank" rel="noopener"
           class="vana-stage-cta" style="background:var(--vana-line);">
          <?php echo esc_html($lbl_generic_cta); ?>
        </a>
      </div>

    <?php else: ?>

      <div class="vana-stage-placeholder"
           style="color:var(--vana-muted);font-size:1.2rem;text-align:center;padding:40px;"
           role="status" aria-live="polite">
        <span class="dashicons dashicons-format-video"
              style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--vana-line);"
              aria-hidden="true"></span>
        <?php echo esc_html($lbl_empty); ?>
      </div>

    <?php endif; ?>

  </div><!-- /player -->

  <!-- ── INFO ────────────────────────────────────────────────── -->
  <div class="vana-stage-info">

    <div style="display:flex;align-items:center;gap:15px;margin-bottom:<?php echo $stage_desc ? '15px' : '0'; ?>;">
      <span class="vana-stage-info-badge"><?php echo esc_html($lbl_class); ?></span>
      <h2 id="vanaStageTitle"
          style="margin:0;font-family:'Syne',sans-serif;font-size:1.3rem;">
        <?php echo esc_html($stage_title ?: $lbl_recording); ?>
      </h2>
    </div>

    <?php if ($stage_desc): ?>
      <div class="vana-stage-desc" style="color:var(--vana-muted);line-height:1.6;font-size:1.05rem;">
        <?php echo nl2br(esc_html($stage_desc)); ?>
      </div>
    <?php endif; ?>

    <!-- Localização + Mapa lazy -->
    <?php if ($stage_loc_name || $has_coords): ?>
      <div class="vana-stage-loc" style="margin-top:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
                    padding:10px 14px;border-radius:12px;border:1px solid var(--vana-line);
                    background:var(--vana-bg-soft);">
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="dashicons dashicons-location-alt" aria-hidden="true" style="color:var(--vana-pink);"></span>
            <strong style="color:var(--vana-text);"><?php echo esc_html($stage_loc_name); ?></strong>
          </div>
          <?php if ($has_coords): ?>
            <button type="button"
                    id="<?php echo esc_attr($map_btn_id); ?>"
                    style="padding:8px 12px;background:rgba(255,255,255,0.06);border:1px solid var(--vana-line);
                           border-radius:8px;cursor:pointer;font-weight:700;font-size:.9rem;"
                    aria-expanded="false"
                    aria-controls="<?php echo esc_attr($map_wrap_id); ?>">
              <?php echo esc_html($lbl_map_load); ?>
            </button>
          <?php endif; ?>
        </div>

        <?php if ($has_coords): ?>
          <div id="<?php echo esc_attr($map_wrap_id); ?>"
               style="display:none;margin-top:12px;border-radius:12px;overflow:hidden;
                      border:1px solid var(--vana-line);height:200px;background:#e2e8f0;"
               aria-hidden="true">
            <iframe id="<?php echo esc_attr($map_iframe_id); ?>"
                    width="100%" height="100%" style="border:0;" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" allowfullscreen
                    title="<?php echo esc_attr($lbl_map_aria); ?>"
                    data-src="<?php echo esc_url($maps_embed); ?>"></iframe>
          </div>
          <script>
          (function(){
            var btn=document.getElementById(<?php echo wp_json_encode($map_btn_id); ?>);
            var wrap=document.getElementById(<?php echo wp_json_encode($map_wrap_id); ?>);
            var iframe=document.getElementById(<?php echo wp_json_encode($map_iframe_id); ?>);
            if(!btn||!wrap||!iframe)return;
            btn.addEventListener('click',function(){
              if(!iframe.getAttribute('src'))iframe.setAttribute('src',iframe.getAttribute('data-src')||'');
              wrap.style.display='block';
              wrap.setAttribute('aria-hidden','false');
              btn.setAttribute('aria-expanded','true');
              btn.disabled=true;
            });
          })();
          </script>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div><!-- /info -->

  <!-- ── SEGMENTOS ────────────────────────────────────────────── -->
  <?php if (!empty($stage_segments) && $stage_provider === 'youtube'): ?>
    <div class="vana-stage-segments" role="navigation" aria-label="<?php echo esc_attr($lbl_chapters); ?>">
      <div style="font-weight:900;color:var(--vana-muted);margin-bottom:10px;font-size:.9rem;text-transform:uppercase;">
        <?php echo esc_html($lbl_chapters_lbl); ?>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($stage_segments as $seg):
          if (!is_array($seg)) continue;
          $t  = sanitize_text_field((string)($seg['t'] ?? $seg['time_local'] ?? $seg['time'] ?? ''));
          $st = Vana_Utils::pick_i18n_key($seg, 'title', $lang);
          if ($t === '' || $st === '') continue;
        ?>
          <button type="button" class="vana-seg-btn"
                  data-vana-stage-seg="1"
                  data-t="<?php echo esc_attr($t); ?>"
                  aria-label="<?php echo esc_attr($lbl_seg_jump . $t . ' — ' . $st); ?>">
            <strong><?php echo esc_html($t); ?></strong>
            <?php echo esc_html($st); ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</section>
PHPEOF
