<?php
/**
 * Partial: Community Links (Canais da Missão)
 * Arquivo: templates/visit/parts/community-links.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $active_day, $visit_id
 *
 * Responsabilidades:
 *   1. Renderizar cards de canais oficiais da missão
 *      (YouTube, Facebook, Instagram, Blogger/Site)
 *   2. Suportar links customizados por dia via JSON (day.links[])
 *   3. Não renderizar nada se não há links definidos nem defaults
 *
 * Estrutura esperada em day.links[] (opcional):
 *   {
 *     "type":     "youtube|facebook|instagram|site|whatsapp|telegram|custom",
 *     "url":      "https://...",
 *     "label_pt": "Assistir no YouTube",
 *     "label_en": "Watch on YouTube",
 *     "icon":     "dashicons-youtube"   (opcional — usa default do type)
 *   }
 */
defined('ABSPATH') || exit;

// ── 1. LINKS DO DIA (JSON) ──────────────────────────────────────
$day_links = is_array($active_day['links'] ?? null) ? $active_day['links'] : [];

// ── 2. CANAIS DEFAULT DA MISSÃO ──────────────────────────────────
// Sempre exibidos como base, abaixo dos links do dia
$default_channels = [
    [
        'type'     => 'youtube',
        'url'      => 'https://www.youtube.com/@vanamadhuryamofficial',
        'label_pt' => 'YouTube — Vana Madhuryam',
        'label_en' => 'YouTube — Vana Madhuryam',
        'desc_pt'  => 'Palestras, kirtans e diários de missão',
        'desc_en'  => 'Lectures, kirtans and mission diaries',
    ],
    [
        'type'     => 'facebook',
        'url'      => 'https://www.facebook.com/vanamadhuryamofficial',
        'label_pt' => 'Facebook — Vana Madhuryam',
        'label_en' => 'Facebook — Vana Madhuryam',
        'desc_pt'  => 'Comunidade e transmissões ao vivo',
        'desc_en'  => 'Community and live streams',
    ],
    [
        'type'     => 'instagram',
        'url'      => 'https://www.instagram.com/vanamadhuryamofficial/',
        'label_pt' => 'Instagram — @vanamadhuryamofficial',
        'label_en' => 'Instagram — @vanamadhuryamofficial',
        'desc_pt'  => 'Momentos, citações e bastidores',
        'desc_en'  => 'Moments, quotes and behind the scenes',
    ],
];

// ── 3. MAPEAMENTO type → visual ──────────────────────────────────
$type_meta = [
    'youtube'   => [
        'icon'      => 'dashicons-youtube',
        'color'     => '#dc2626',
        'bg'        => '#fee2e2',
        'btn_color' => '#dc2626',
        'btn_label' => ['pt' => 'Assistir', 'en' => 'Watch'],
    ],
    'facebook'  => [
        'icon'      => 'dashicons-facebook-alt',
        'color'     => '#1877f2',
        'bg'        => '#dbeafe',
        'btn_color' => '#1877f2',
        'btn_label' => ['pt' => 'Seguir', 'en' => 'Follow'],
    ],
    'instagram' => [
        'icon'      => 'dashicons-instagram',
        'color'     => '#e1306c',
        'bg'        => '#fce7f3',
        'btn_color' => '#e1306c',
        'btn_label' => ['pt' => 'Seguir', 'en' => 'Follow'],
    ],
    'whatsapp'  => [
        'icon'      => 'dashicons-phone',
        'color'     => '#16a34a',
        'bg'        => '#dcfce7',
        'btn_color' => '#16a34a',
        'btn_label' => ['pt' => 'Entrar', 'en' => 'Join'],
    ],
    'telegram'  => [
        'icon'      => 'dashicons-share',
        'color'     => '#0088cc',
        'bg'        => '#e0f2fe',
        'btn_color' => '#0088cc',
        'btn_label' => ['pt' => 'Entrar', 'en' => 'Join'],
    ],
    'site'      => [
        'icon'      => 'dashicons-admin-site-alt3',
        'color'     => '#64748b',
        'bg'        => '#f1f5f9',
        'btn_color' => '#64748b',
        'btn_label' => ['pt' => 'Acessar', 'en' => 'Visit'],
    ],
    'custom'    => [
        'icon'      => 'dashicons-admin-links',
        'color'     => 'var(--vana-orange)',
        'bg'        => '#fff7ed',
        'btn_color' => 'var(--vana-orange)',
        'btn_label' => ['pt' => 'Acessar', 'en' => 'Visit'],
    ],
];

// ── 4. MERGE: links do dia primeiro, depois defaults ─────────────
// Remove defaults cujo type já aparece nos links do dia
$day_types = array_filter(array_column($day_links, 'type'));
$filtered_defaults = array_filter($default_channels, function ($ch) use ($day_types) {
    return !in_array($ch['type'] ?? '', $day_types, true);
});

$all_links = array_merge($day_links, array_values($filtered_defaults));

// Nada a renderizar
if (empty($all_links)) return;
?>

<section
  class="vana-section vana-section--community"
  aria-labelledby="vana-community-heading"
>

  <h2 class="vana-section-title" id="vana-community-heading">
    <?php echo esc_html($lang === 'en' ? 'Mission Channels' : 'Canais da Missão'); ?>
  </h2>

  <div
    class="vana-grid"
    role="list"
    aria-label="<?php echo esc_attr($lang === 'en' ? 'Mission channels' : 'Canais da missão'); ?>"
    style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));"
  >

    <?php foreach ($all_links as $link):
      if (!is_array($link)) continue;

      $type      = sanitize_text_field((string) ($link['type']     ?? 'custom'));
      $url       = esc_url_raw((string)          ($link['url']      ?? ''));
      $label     = $lang === 'en'
                     ? sanitize_text_field((string) ($link['label_en'] ?? $link['label_pt'] ?? ''))
                     : sanitize_text_field((string) ($link['label_pt'] ?? $link['label_en'] ?? ''));
      $desc      = $lang === 'en'
                     ? sanitize_text_field((string) ($link['desc_en']  ?? $link['desc_pt']  ?? ''))
                     : sanitize_text_field((string) ($link['desc_pt']  ?? $link['desc_en']  ?? ''));

      if (!$url || !$label) continue;

      // Visual do tipo
      $meta      = $type_meta[$type] ?? $type_meta['custom'];
      $icon      = sanitize_text_field((string) ($link['icon'] ?? $meta['icon']));
      $color     = $meta['color'];
      $bg        = $meta['bg'];
      $btn_color = $meta['btn_color'];
      $btn_label = $meta['btn_label'][$lang] ?? $meta['btn_label']['pt'];

      // Link externo sempre abre em nova aba
      $is_external = str_starts_with($url, 'http');
      $target      = $is_external ? '_blank' : '_self';
      $rel         = $is_external ? 'noopener noreferrer' : '';
    ?>

      <div
        class="vana-card vana-card--channel"
        role="listitem"
        style="border-top: 3px solid <?php echo esc_attr($color); ?>;"
      >

        <div class="vana-card__body" style="padding: 20px; gap: 12px;">

          <!-- Ícone + Label -->
          <div style="display: flex; align-items: center; gap: 12px;">

            <div style="
              width:           44px;
              height:          44px;
              border-radius:   12px;
              background:      <?php echo esc_attr($bg); ?>;
              display:         flex;
              align-items:     center;
              justify-content: center;
              flex-shrink:     0;
            " aria-hidden="true">
              <span
                class="dashicons <?php echo esc_attr($icon); ?>"
                style="font-size: 1.4rem; width: auto; height: auto;
                       color: <?php echo esc_attr($color); ?>;"
              ></span>
            </div>

            <div>
              <div style="
                font-family: 'Syne', sans-serif;
                font-weight: 900;
                font-size:   1rem;
                color:       var(--vana-text);
                line-height: 1.2;
              ">
                <?php echo esc_html($label); ?>
              </div>

              <?php if ($desc): ?>
                <div style="
                  color:     var(--vana-muted);
                  font-size: 0.82rem;
                  margin-top: 2px;
                  line-height: 1.4;
                ">
                  <?php echo esc_html($desc); ?>
                </div>
              <?php endif; ?>
            </div>

          </div>
          <!-- /ícone + label -->

          <!-- CTA -->
          <a
            href="<?php echo esc_url($url); ?>"
            target="<?php echo esc_attr($target); ?>"
            <?php echo $rel ? 'rel="' . esc_attr($rel) . '"' : ''; ?>
            style="
              display:         block;
              margin-top:      auto;
              text-align:      center;
              padding:         10px 16px;
              border-radius:   8px;
              background:      <?php echo esc_attr($btn_color); ?>;
              color:           #fff;
              font-weight:     900;
              text-decoration: none;
              font-size:       0.95rem;
              transition:      opacity .2s;
            "
            aria-label="<?php echo esc_attr(
              $btn_label . ' — ' . $label
              . ($is_external ? (' (' . ($lang === 'en' ? 'opens in new tab' : 'abre em nova aba') . ')') : '')
            ); ?>"
            onmouseover="this.style.opacity='.85'"
            onmouseout="this.style.opacity='1'"
          >
            <?php echo esc_html($btn_label); ?>
            <?php if ($is_external): ?>
              <span aria-hidden="true" style="margin-left: 4px; font-size: .85em;">↗</span>
            <?php endif; ?>
          </a>

        </div>
        <!-- /body -->

      </div>
      <!-- /card -->

    <?php endforeach; ?>

  </div>
  <!-- /grid -->

</section>
