<?php
/**
 * Partial: Stage (Palco Principal)
 * Arquivo: templates/visit/parts/stage.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id, $visit_tz
 *   $active_day, $active_day_date
 *   $active_vod, $vod_list, $vod_count
 *   $active_vod_index
 *
 * Responsabilidades:
 *   1. Resolver a mídia do Stage (hero ou vod ativo)
 *   2. Renderizar o player (YouTube / Drive / Facebook / Instagram / link)
 *   3. Renderizar info do Stage (badge + título + descrição)
 *   4. Renderizar localização + mapa lazy
 *   5. Renderizar segmentos / capítulos (YouTube only)
 */
defined('ABSPATH') || exit;

// ── 1. RESOLVER ITEM DO STAGE ──────────────────────────────────
$hero       = is_array($active_day['hero'] ?? null) ? $active_day['hero'] : [];
$stage_item = !empty($active_vod) ? $active_vod : $hero;

// Textos i18n
$stage_title = Vana_Utils::pick_i18n_key($stage_item, 'title',       $lang);
$stage_desc  = Vana_Utils::pick_i18n_key($stage_item, 'description', $lang);

// Segmentos / capítulos
$stage_segments = is_array($stage_item['segments'] ?? null) ? $stage_item['segments'] : [];

// Localização da aula
$stage_loc      = is_array($stage_item['location'] ?? null) ? $stage_item['location'] : [];
$stage_loc_name = (string) ($stage_loc['name'] ?? '');
$stage_lat      = (string) ($stage_loc['lat']  ?? '');
$stage_lng      = (string) ($stage_loc['lng']  ?? '');

// ── 2. RESOLVER MÍDIA ─────────────────────────────────────────
$resolved       = vana_stage_resolve_media($stage_item);
$stage_provider = (string) ($resolved['provider'] ?? '');
$stage_video_id = (string) ($resolved['video_id'] ?? '');
$stage_url      = (string) ($resolved['url']      ?? '');

// ── 3. LIVE BADGE (qualquer item com status=live no schedule) ──
$has_live = false;
$schedule = is_array($active_day['schedule'] ?? null) ? $active_day['schedule'] : [];
foreach ($schedule as $_item) {
    if (is_array($_item) && ($_item['status'] ?? '') === 'live') {
        $has_live = true;
        break;
    }
}

// ── 4. MAPA ───────────────────────────────────────────────────
$lat        = is_numeric($stage_lat) ? $stage_lat : '';
$lng        = is_numeric($stage_lng) ? $stage_lng : '';
$has_coords = ($lat !== '' && $lng !== '');
$maps_embed = $has_coords
    ? 'https://maps.google.com/maps?q=' . rawurlencode($lat . ',' . $lng)
      . '&hl=' . ($lang === 'en' ? 'en' : 'pt') . '&z=15&output=embed'
    : '';

// ── IDs únicos para o mapa (suporte a múltiplos stages na página) ──
$map_btn_id    = 'vanaLoadMapBtn_'   . esc_attr($active_day_date);
$map_wrap_id   = 'vanaMapWrap_'      . esc_attr($active_day_date);
$map_iframe_id = 'vanaMapIframe_'    . esc_attr($active_day_date);
?>

<section class="vana-stage" aria-label="<?php echo esc_attr($lang === 'en' ? 'Main stage' : 'Palco principal'); ?>">

  <!-- ══════════════════════════════════════════════════════════
       PLAYER
       ══════════════════════════════════════════════════════════ -->
  <div class="vana-stage-video">

    <?php if ($stage_provider === 'youtube' && $stage_video_id): ?>

      <iframe
        id="vanaStageIframe"
        src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($stage_video_id); ?>?rel=0"
        title="<?php echo esc_attr($stage_title ?: ($lang === 'en' ? 'Class' : 'Aula')); ?>"
        style="position:absolute; inset:0; width:100%; height:100%; border:0;"
        allowfullscreen
        loading="lazy"
      ></iframe>

    <?php elseif ($stage_provider === 'drive' && $stage_url):
      $fid = vana_drive_file_id($stage_url);
      if ($fid): ?>

        <iframe
          id="vanaStageIframe"
          src="https://drive.google.com/file/d/<?php echo esc_attr($fid); ?>/preview"
          title="<?php echo esc_attr($stage_title ?: 'Google Drive'); ?>"
          style="position:absolute; inset:0; width:100%; height:100%; border:0;"
          allow="autoplay"
          loading="lazy"
        ></iframe>

      <?php else: ?>

        <div class="vana-stage-placeholder">
          <a
            href="<?php echo esc_url($stage_url); ?>"
            target="_blank"
            rel="noopener"
            class="vana-stage-cta"
          >
            <?php echo esc_html($lang === 'en' ? 'Watch on Google Drive →' : 'Abrir vídeo no Drive →'); ?>
          </a>
        </div>

      <?php endif; ?>

    <?php elseif ($stage_provider === 'facebook' && $stage_url):
      $fb_href  = esc_url_raw($stage_url);
      $fb_embed = 'https://www.facebook.com/plugins/video.php?href='
                  . rawurlencode($fb_href) . '&show_text=0&width=1200';
      ?>

      <iframe
        id="vanaFbIframe"
        src="<?php echo esc_url($fb_embed); ?>"
        title="<?php echo esc_attr($lang === 'en' ? 'Class (Facebook)' : 'Aula ao vivo (Facebook)'); ?>"
        style="position:absolute; inset:0; width:100%; height:100%; border:0;"
        scrolling="no"
        allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"
        allowfullscreen="1"
        referrerpolicy="origin-when-cross-origin"
      ></iframe>

      <!-- Fallback Facebook -->
      <div
        id="vanaFbFallback"
        style="display:none; padding:40px; text-align:center; background:rgba(255,255,255,.92);
               position:absolute; inset:0; z-index:2; flex-direction:column;
               align-items:center; justify-content:center; backdrop-filter:blur(6px);"
        role="alert"
        aria-live="polite"
      >
        <div style="font-weight:900; color:var(--vana-text); font-size:1.3rem; margin-bottom:10px; font-family:'Syne',sans-serif;">
          <?php echo esc_html($lang === 'en' ? 'Class (Facebook)' : 'Aula ao vivo (Facebook)'); ?>
        </div>
        <div style="color:var(--vana-muted); margin-bottom:25px; max-width:80%;">
          <?php echo esc_html($lang === 'en'
            ? 'If the embedded player does not load, open it directly on Facebook or copy the link.'
            : 'Se o player embutido não carregar, abra diretamente no Facebook ou copie o link.'
          ); ?>
        </div>
        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
          <a
            href="<?php echo esc_url($stage_url); ?>"
            target="_blank"
            rel="noopener"
            style="display:inline-block; background:var(--vana-blue); color:#fff;
                   padding:12px 24px; border-radius:8px; font-weight:900;
                   text-decoration:none; font-size:1.05rem;"
          >
            <?php echo esc_html($lang === 'en' ? 'Open on Facebook →' : 'Abrir no Facebook →'); ?>
          </a>
          <button
            type="button"
            id="vanaCopyFbLink"
            data-url="<?php echo esc_attr($stage_url); ?>"
            style="display:inline-block; background:#fff; color:var(--vana-text);
                   border:1px solid var(--vana-line); padding:12px 24px;
                   border-radius:8px; font-weight:900; font-size:1.05rem;
                   cursor:pointer; transition:.2s;"
          >
            <?php echo esc_html($lang === 'en' ? 'Copy link' : 'Copiar Link'); ?>
          </button>
        </div>
      </div>

    <?php elseif ($stage_provider === 'instagram' && $stage_url): ?>

      <div class="vana-stage-placeholder" style="background:#fff;">
        <div style="font-weight:900; color:var(--vana-text); font-size:1.3rem;
                    margin-bottom:10px; font-family:'Syne',sans-serif;">
          <?php echo esc_html($lang === 'en' ? 'Class (Instagram)' : 'Aula ao vivo (Instagram)'); ?>
        </div>
        <div style="color:var(--vana-muted); margin-bottom:20px;">
          <?php echo esc_html($lang === 'en'
            ? 'The video opens in a new tab.'
            : 'O vídeo abre numa nova aba.'
          ); ?>
        </div>
        <a
          href="<?php echo esc_url($stage_url); ?>"
          target="_blank"
          rel="noopener"
          style="display:inline-block; background:var(--vana-pink); color:#fff;
                 padding:12px 24px; border-radius:8px; font-weight:900;
                 text-decoration:none; font-size:1.1rem;"
        >
          <?php echo esc_html($lang === 'en' ? 'Open on Instagram →' : 'Abrir no Instagram →'); ?>
        </a>
      </div>

    <?php elseif ($stage_url): ?>

      <div class="vana-stage-placeholder">
        <a
          href="<?php echo esc_url($stage_url); ?>"
          target="_blank"
          rel="noopener"
          class="vana-stage-cta"
          style="background:var(--vana-line);"
        >
          <?php echo esc_html($lang === 'en' ? 'Open video link →' : 'Abrir link do vídeo →'); ?>
        </a>
      </div>

    <?php else: ?>

      <!-- Sem mídia: estado vazio diferenciado para live -->
      <div
        class="vana-stage-placeholder"
        style="color:var(--vana-muted); font-size:1.2rem; text-align:center; padding:40px;"
        role="status"
        aria-live="polite"
      >
        <?php if ($has_live): ?>
          <span class="dashicons dashicons-video-alt3"
                style="font-size:2.5rem; display:block; margin-bottom:12px; color:var(--vana-pink);"
                aria-hidden="true"></span>
          <?php echo esc_html($lang === 'en'
            ? 'LIVE — link will appear shortly.'
            : 'AO VIVO — o link aparecerá em breve.'
          ); ?>
        <?php else: ?>
          <span class="dashicons dashicons-format-video"
                style="font-size:2.5rem; display:block; margin-bottom:12px; color:var(--vana-line);"
                aria-hidden="true"></span>
          <?php echo esc_html($lang === 'en'
            ? 'No class selected for this day.'
            : 'Nenhuma aula selecionada para este dia.'
          ); ?>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div>
  <!-- /player -->

  <!-- ══════════════════════════════════════════════════════════
       INFO DO STAGE (badge + título + descrição)
       ══════════════════════════════════════════════════════════ -->
  <div class="vana-stage-info">

    <div style="display:flex; align-items:center; gap:15px; margin-bottom:<?php echo $stage_desc ? '15px' : '0'; ?>;">
      <span class="vana-stage-info-badge">
        <?php echo esc_html($lang === 'en' ? 'Class' : 'Aula'); ?>
      </span>
      <h2
        id="vanaStageTitle"
        style="margin:0; font-family:'Syne',sans-serif; font-size:1.3rem;"
      >
        <?php echo esc_html($stage_title ?: ($lang === 'en' ? 'Recording' : 'Gravação')); ?>
      </h2>
    </div>

    <?php if ($stage_desc): ?>
      <div class="vana-stage-desc" style="color:var(--vana-muted); line-height:1.6; font-size:1.05rem;">
        <?php echo nl2br(esc_html($stage_desc)); ?>
      </div>
    <?php endif; ?>

    <!-- ── Localização + Mapa lazy ── -->
    <?php if ($stage_loc_name || $has_coords): ?>
      <div class="vana-stage-loc" style="margin-top:16px;">

        <div style="display:flex; align-items:center; justify-content:space-between;
                    gap:12px; flex-wrap:wrap; padding:10px 14px; border-radius:12px;
                    border:1px solid var(--vana-line); background:var(--vana-bg-soft);">

          <div style="display:flex; align-items:center; gap:10px;">
            <span class="dashicons dashicons-location-alt"
                  aria-hidden="true"
                  style="color:var(--vana-pink);"></span>
            <strong style="color:var(--vana-text);">
              <?php echo esc_html($stage_loc_name ?: $visit_city_ref ?? ''); ?>
            </strong>
          </div>

          <?php if ($has_coords): ?>
            <button
              type="button"
              id="<?php echo esc_attr($map_btn_id); ?>"
              style="padding:8px 12px; background:#fff; border:1px solid var(--vana-line);
                     border-radius:8px; cursor:pointer; font-weight:700; font-size:.9rem;"
              aria-expanded="false"
              aria-controls="<?php echo esc_attr($map_wrap_id); ?>"
            >
              <?php echo esc_html($lang === 'en' ? 'Load map' : 'Carregar mapa'); ?>
            </button>
          <?php endif; ?>

        </div>

        <?php if ($has_coords): ?>
          <div
            id="<?php echo esc_attr($map_wrap_id); ?>"
            style="display:none; margin-top:12px; border-radius:12px; overflow:hidden;
                   border:1px solid var(--vana-line); height:200px; background:#e2e8f0;"
            aria-hidden="true"
          >
            <iframe
              id="<?php echo esc_attr($map_iframe_id); ?>"
              width="100%"
              height="100%"
              style="border:0;"
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              allowfullscreen
              title="<?php echo esc_attr($lang === 'en' ? 'Event location map' : 'Mapa do local do evento'); ?>"
              data-src="<?php echo esc_url($maps_embed); ?>"
            ></iframe>
          </div>

          <script>
          (function () {
            var btn    = document.getElementById(<?php echo wp_json_encode($map_btn_id); ?>);
            var wrap   = document.getElementById(<?php echo wp_json_encode($map_wrap_id); ?>);
            var iframe = document.getElementById(<?php echo wp_json_encode($map_iframe_id); ?>);
            if (!btn || !wrap || !iframe) return;

            btn.addEventListener('click', function () {
              if (!iframe.getAttribute('src')) {
                iframe.setAttribute('src', iframe.getAttribute('data-src') || '');
              }
              wrap.style.display   = 'block';
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

  <!-- ══════════════════════════════════════════════════════════
       SEGMENTOS / CAPÍTULOS (YouTube only)
       ══════════════════════════════════════════════════════════ -->
  <?php if (!empty($stage_segments) && $stage_provider === 'youtube'): ?>
    <div class="vana-stage-segments" role="navigation" aria-label="<?php echo esc_attr($lang === 'en' ? 'Chapters' : 'Capítulos'); ?>">

      <div style="font-weight:900; color:var(--vana-muted); margin-bottom:10px;
                  font-size:.9rem; text-transform:uppercase;">
        <?php echo esc_html($lang === 'en' ? 'Chapters' : 'Tópicos Abordados'); ?>
      </div>

      <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <?php foreach ($stage_segments as $seg):
          if (!is_array($seg)) continue;

          $t  = sanitize_text_field((string) ($seg['t'] ?? $seg['time_local'] ?? $seg['time'] ?? ''));
          $st = Vana_Utils::pick_i18n_key($seg, 'title', $lang);
          if ($t === '' || $st === '') continue;
        ?>
          <button
            type="button"
            class="vana-seg-btn"
            data-vana-stage-seg="1"
            data-t="<?php echo esc_attr($t); ?>"
            aria-label="<?php echo esc_attr(
              ($lang === 'en' ? 'Jump to ' : 'Ir para ') . $t . ' — ' . $st
            ); ?>"
          >
            <strong><?php echo esc_html($t); ?></strong>
            <?php echo esc_html($st); ?>
          </button>
        <?php endforeach; ?>
      </div>

    </div>
  <?php endif; ?>
  <!-- /segmentos -->

</section>

<?php
// ── CSS inline para o placeholder (usado em 4 variantes) ──────
// Evita repetição inline em cada branch do if/elseif acima
?>
<style>
.vana-stage-placeholder {
  position:        absolute;
  inset:           0;
  background:      #fff;
  display:         flex;
  flex-direction:  column;
  align-items:     center;
  justify-content: center;
  padding:         40px;
}
.vana-stage-cta {
  font-weight:     900;
  text-decoration: none;
  color:           var(--vana-text);
  font-size:       1.2rem;
  background:      var(--vana-gold);
  padding:         12px 24px;
  border-radius:   8px;
  transition:      opacity .2s;
}
.vana-stage-cta:hover { opacity: .85; }
</style>
