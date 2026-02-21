<?php
/**
 * Partial: VOD List (Grade de Aulas)
 * Arquivo: templates/visit/parts/vod-list.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id
 *   $active_day, $active_day_date
 *   $vod_list, $vod_count, $active_vod_index
 *
 * Responsabilidades:
 *   1. Renderizar grid de cards de vídeo (VOD)
 *   2. Destacar o card ativo (selecionado no Stage)
 *   3. Suportar thumbnail automático (YouTube) ou imagem custom
 *   4. Não renderizar nada se não há VODs no dia
 */
defined('ABSPATH') || exit;

// Nada a renderizar se o dia não tem VODs
if (empty($vod_list) || $vod_count === 0) return;

// Label da seção
$section_label = $lang === 'en' ? 'Classes' : 'Aulas';
?>

<section
  class="vana-section vana-section--vod"
  aria-labelledby="vana-vod-heading"
>

  <h2 class="vana-section-title" id="vana-vod-heading">
    <?php echo esc_html($section_label); ?>
    <span style="
      font-size:      0.85rem;
      font-weight:    700;
      color:          var(--vana-muted);
      margin-left:    10px;
      font-family:    'Questrial', sans-serif;
    ">
      (<?php echo (int) $vod_count; ?>)
    </span>
  </h2>

  <div
    class="vana-grid"
    role="list"
    aria-label="<?php echo esc_attr($section_label); ?>"
  >

    <?php foreach ($vod_list as $idx => $vod):
      if (!is_array($vod)) continue;

      // ── Textos i18n ───────────────────────────────────────
      $vod_title = Vana_Utils::pick_i18n_key($vod, 'title', $lang);
      $vod_desc  = Vana_Utils::pick_i18n_key($vod, 'description', $lang);

      // ── Mídia ─────────────────────────────────────────────
      $resolved   = vana_stage_resolve_media($vod);
      $provider   = $resolved['provider'];
      $video_id   = $resolved['video_id'];
      $media_url  = $resolved['url'];

      // ── Thumbnail ─────────────────────────────────────────
      // Prioridade: thumb_url custom > YouTube auto > placeholder
      $thumb_url = (string) ($vod['thumb_url'] ?? '');

      if ($thumb_url === '' && $provider === 'youtube' && $video_id) {
          // maxresdefault com fallback para hqdefault
          $thumb_url = 'https://i.ytimg.com/vi/' . esc_attr($video_id) . '/hqdefault.jpg';
      }

      $thumb_placeholder = $thumb_url === '';

      // ── Estado ativo ──────────────────────────────────────
      $is_active = ($idx === $active_vod_index);

      // ── URL do card ───────────────────────────────────────
      // Clique no card → troca o vod ativo na URL (recarrega Stage)
      $card_url = vana_visit_url($visit_id, $active_day_date, $idx, $lang);

      // ── Segmentos ─────────────────────────────────────────
      $segments      = is_array($vod['segments'] ?? null) ? $vod['segments'] : [];
      $segments_count = count($segments);

      // ── Duração (campo opcional) ──────────────────────────
      $duration = sanitize_text_field((string) ($vod['duration'] ?? ''));

    ?>

      <div
        class="vana-card<?php echo $is_active ? ' vana-card--active' : ''; ?>"
        role="listitem"
        style="<?php echo $is_active
          ? 'border-color: var(--vana-gold); box-shadow: 0 0 0 3px rgba(255,217,6,.35);'
          : ''; ?>"
      >

        <a
          href="<?php echo esc_url($card_url); ?>"
          aria-label="<?php echo esc_attr(
            ($lang === 'en' ? 'Watch class: ' : 'Assistir aula: ') . $vod_title
            . ($is_active ? ($lang === 'en' ? ' (selected)' : ' (selecionada)') : '')
          ); ?>"
          aria-current="<?php echo $is_active ? 'true' : 'false'; ?>"
        >

          <!-- ── Thumbnail ─────────────────────────────────── -->
          <div class="vana-card__media">

            <?php if (!$thumb_placeholder): ?>
              <img
                src="<?php echo esc_url($thumb_url); ?>"
                alt="<?php echo esc_attr($vod_title); ?>"
                loading="lazy"
                decoding="async"
                width="480"
                height="270"
              >
            <?php else: ?>
              <!-- Placeholder gradiente com ícone -->
              <div style="
                position:        absolute;
                inset:           0;
                background:      var(--vana-hero-gradient);
                display:         flex;
                align-items:     center;
                justify-content: center;
              " aria-hidden="true">
                <span class="dashicons dashicons-video-alt3"
                      style="font-size:2.5rem; width:auto; height:auto;
                             color:var(--vana-muted); opacity:.5;">
                </span>
              </div>
            <?php endif; ?>

            <!-- Play button overlay -->
            <div class="vana-card__play" aria-hidden="true">
              <?php if ($provider === 'facebook'): ?>
                <span class="dashicons dashicons-facebook-alt"
                      style="font-size:1.3rem; width:auto; height:auto;
                             color:var(--vana-blue);"></span>
              <?php elseif ($provider === 'instagram'): ?>
                <span class="dashicons dashicons-instagram"
                      style="font-size:1.3rem; width:auto; height:auto;
                             color:var(--vana-pink);"></span>
              <?php else: ?>
                <span class="dashicons dashicons-controls-play"
                      style="font-size:1.3rem; width:auto; height:auto;"></span>
              <?php endif; ?>
            </div>

            <!-- Badge "active" no canto superior esquerdo -->
            <?php if ($is_active): ?>
              <div style="
                position:       absolute;
                top:            10px;
                left:           10px;
                background:     var(--vana-gold);
                color:          #111;
                padding:        3px 10px;
                border-radius:  20px;
                font-weight:    900;
                font-size:      0.7rem;
                text-transform: uppercase;
                z-index:        2;
              " aria-hidden="true">
                <?php echo esc_html($lang === 'en' ? 'Playing' : 'Reproduzindo'); ?>
              </div>
            <?php endif; ?>

            <!-- Badge de duração no canto inferior direito -->
            <?php if ($duration): ?>
              <div style="
                position:       absolute;
                bottom:         8px;
                right:          8px;
                background:     rgba(0,0,0,.72);
                color:          #fff;
                padding:        2px 8px;
                border-radius:  4px;
                font-size:      0.8rem;
                font-weight:    700;
                font-family:    monospace;
                z-index:        2;
              " aria-label="<?php echo esc_attr(
                ($lang === 'en' ? 'Duration: ' : 'Duração: ') . $duration
              ); ?>">
                <?php echo esc_html($duration); ?>
              </div>
            <?php endif; ?>

          </div>
          <!-- /thumbnail -->

          <!-- ── Corpo do card ─────────────────────────────── -->
          <div class="vana-card__body">

            <p class="vana-card__name">
              <?php echo esc_html($vod_title ?: ($lang === 'en' ? 'Untitled class' : 'Aula sem título')); ?>
            </p>

            <?php if ($vod_desc): ?>
              <p style="
                color:              var(--vana-muted);
                font-size:          0.9rem;
                line-height:        1.5;
                margin:             6px 0 0;
                display:            -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow:           hidden;
              ">
                <?php echo esc_html($vod_desc); ?>
              </p>
            <?php endif; ?>

            <!-- Meta: provider + nº de capítulos -->
            <div style="
              display:     flex;
              align-items: center;
              gap:         10px;
              margin-top:  10px;
              color:       var(--vana-muted);
              font-size:   0.82rem;
              font-weight: 700;
            ">

              <!-- Provider badge -->
              <?php
              $provider_labels = [
                  'youtube'   => ['icon' => 'dashicons-youtube',       'color' => '#dc2626', 'label' => 'YouTube'],
                  'facebook'  => ['icon' => 'dashicons-facebook-alt',  'color' => '#1877f2', 'label' => 'Facebook'],
                  'instagram' => ['icon' => 'dashicons-instagram',     'color' => '#e1306c', 'label' => 'Instagram'],
                  'drive'     => ['icon' => 'dashicons-cloud',         'color' => '#0f9d58', 'label' => 'Drive'],
              ];
              if (isset($provider_labels[$provider])):
                $pl = $provider_labels[$provider];
              ?>
                <span style="display:inline-flex; align-items:center; gap:4px;">
                  <span class="dashicons <?php echo esc_attr($pl['icon']); ?>"
                        aria-hidden="true"
                        style="font-size:14px; width:14px; height:14px;
                               color:<?php echo esc_attr($pl['color']); ?>;"></span>
                  <?php echo esc_html($pl['label']); ?>
                </span>
              <?php endif; ?>

              <!-- Nº de capítulos -->
              <?php if ($segments_count > 0): ?>
                <span>·</span>
                <span>
                  <?php printf(
                    esc_html(_n('%d capítulo', '%d capítulos', $segments_count, 'vana')),
                    $segments_count
                  ); ?>
                </span>
              <?php endif; ?>

            </div>
            <!-- /meta -->

          </div>
          <!-- /corpo -->

        </a>

      </div>
      <!-- /card -->

    <?php endforeach; ?>

  </div>
  <!-- /grid -->

</section>
