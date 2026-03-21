<?php
/**
 * Partial: Community Links
 * Arquivo: templates/visit/parts/community-links.php
 *
 * Duas seções separadas:
 *   1. Redes oficiais de Gurudeva (discreto)
 *   2. Organização do evento (contatos, grupos WhatsApp)
 *      → lido de get_post_meta($visit_id, '_vana_event_links_json', true)
 *      → se vazio, não renderiza a seção 2
 *
 * Variáveis esperadas: $lang, $visit_id
 */
defined('ABSPATH') || exit;

// ── i18n ──────────────────────────────────────────────────────
$lbl_follow  = vana_t('community.follow',    $lang);
$lbl_org     = vana_t('community.org_title', $lang);
$lbl_org_sub = vana_t('community.org_sub',   $lang);
$lbl_new_tab = vana_t('community.new_tab',   $lang);

// ── Redes fixas de Gurudeva ───────────────────────────────────
$gurudeva_links = [
    [
        'url'   => 'https://www.youtube.com/@vanamadhuryamofficial',
        'icon'  => 'dashicons-youtube',
        'label' => 'YouTube',
        'color' => 'rgba(255,0,0,0.12)',
        'border'=> 'rgba(255,0,0,0.25)',
        'cls'   => 'vana-community-link--yt',
    ],
    [
        'url'   => 'https://www.facebook.com/vanamadhuryamofficial',
        'icon'  => 'dashicons-facebook-alt',
        'label' => 'Facebook',
        'color' => 'rgba(24,119,242,0.10)',
        'border'=> 'rgba(24,119,242,0.25)',
        'cls'   => 'vana-community-link--fb',
    ],
    [
        'url'   => 'https://www.instagram.com/vanamadhuryamofficial/',
        'icon'  => 'dashicons-instagram',
        'label' => 'Instagram',
        'color' => 'rgba(243,11,115,0.10)',
        'border'=> 'rgba(243,11,115,0.25)',
        'cls'   => 'vana-community-link--ig',
    ],
];

// ── Links de organização (meta da visita) ─────────────────────
// Estrutura esperada em _vana_event_links_json:
// [
//   { "type": "whatsapp|telegram|email|phone|web",
//     "label_pt": "Grupo PT",
//     "label_en": "PT Group",
//     "url": "https://chat.whatsapp.com/..." },
//   ...
// ]
$event_links_raw  = get_post_meta($visit_id, '_vana_event_links_json', true);
$event_links      = [];
if ($event_links_raw) {
    $decoded = json_decode($event_links_raw, true);
    if (is_array($decoded)) $event_links = $decoded;
}

// ── Mapa ícone por tipo ───────────────────────────────────────
$type_icon = [
    'whatsapp' => ['icon' => 'dashicons-whatsapp',     'color' => 'rgba(37,211,102,0.12)',  'border' => 'rgba(37,211,102,0.30)'],
    'telegram' => ['icon' => 'dashicons-paperplane',   'color' => 'rgba(0,136,204,0.10)',   'border' => 'rgba(0,136,204,0.28)'],
    'email'    => ['icon' => 'dashicons-email-alt',    'color' => 'rgba(255,217,6,0.12)',   'border' => 'rgba(255,217,6,0.35)'],
    'phone'    => ['icon' => 'dashicons-phone',        'color' => 'rgba(22,163,74,0.10)',   'border' => 'rgba(22,163,74,0.28)'],
    'web'      => ['icon' => 'dashicons-admin-site',   'color' => 'rgba(23,13,242,0.08)',   'border' => 'rgba(23,13,242,0.22)'],
];
$default_icon = ['icon' => 'dashicons-admin-links', 'color' => 'rgba(100,116,139,0.10)', 'border' => 'rgba(100,116,139,0.25)'];
?>

<section
  class="vana-section vana-section--community"
  aria-label="<?php echo esc_attr(vana_t('community.aria', $lang)); ?>"
  style="margin-top: 48px;
         padding-top: 32px;
         border-top: 1px solid var(--vana-line);"
>

  <!-- ══════════════════════════════════════════════
       1. REDES DE GURUDEVA (sempre visível, discreto)
       ══════════════════════════════════════════════ -->
  <div style="
    display:         flex;
    align-items:     center;
    justify-content: space-between;
    flex-wrap:       wrap;
    gap:             12px;
    margin-bottom:   16px;
  ">
    <span style="
      font-family:    'Syne', sans-serif;
      font-weight:    700;
      font-size:      0.82rem;
      color:          var(--vana-muted);
      text-transform: uppercase;
      letter-spacing: 0.10em;
    ">
      <?php echo esc_html($lbl_follow); ?>
    </span>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <?php foreach ($gurudeva_links as $gl): ?>
        <a
          href="<?php echo esc_url($gl['url']); ?>"
          target="_blank"
          rel="noopener noreferrer"
          class="vana-community-link <?php echo esc_attr($gl['cls']); ?>"
          aria-label="<?php echo esc_attr($gl['label'] . ' (' . $lbl_new_tab . ')'); ?>"
          style="
            display:         inline-flex;
            align-items:     center;
            gap:             7px;
            padding:         8px 14px;
            border-radius:   8px;
            text-decoration: none;
            font-weight:     700;
            font-size:       0.85rem;
            font-family:     'Syne', sans-serif;
            color:           var(--vana-text);
            background:      <?php echo esc_attr($gl['color']); ?>;
            border:          1px solid <?php echo esc_attr($gl['border']); ?>;
            transition:      opacity .2s, transform .2s;
          "
          onmouseover="this.style.opacity='.8';this.style.transform='translateY(-2px)'"
          onmouseout="this.style.opacity='1';this.style.transform='translateY(0)'"
        >
          <span class="dashicons <?php echo esc_attr($gl['icon']); ?>"
                aria-hidden="true"
                style="font-size:16px;width:16px;height:16px;"></span>
          <?php echo esc_html($gl['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($event_links)): ?>
  <!-- ══════════════════════════════════════════════
       2. ORGANIZAÇÃO DO EVENTO (condicional)
       ══════════════════════════════════════════════ -->
  <div style="
    background:    var(--vana-bg-soft);
    border:        1px solid var(--vana-line);
    border-radius: 12px;
    padding:       20px 22px;
    margin-top:    8px;
  ">
    <div style="margin-bottom: 14px;">
      <div style="
        font-family:    'Syne', sans-serif;
        font-weight:    900;
        font-size:      1rem;
        color:          var(--vana-text);
        margin-bottom:  4px;
      ">
        <span class="dashicons dashicons-groups"
              aria-hidden="true"
              style="color:var(--vana-gold);
                     margin-right:6px;
                     font-size:1.1rem;
                     width:auto;height:auto;
                     vertical-align:middle;"></span>
        <?php echo esc_html($lbl_org); ?>
      </div>
      <div style="color:var(--vana-muted);font-size:.88rem;">
        <?php echo esc_html($lbl_org_sub); ?>
      </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php foreach ($event_links as $el):
        if (!is_array($el)) continue;

        $el_url   = esc_url_raw((string) ($el['url'] ?? ''));
        if (!$el_url) continue;

        $el_label = $lang === 'en'
            ? sanitize_text_field((string) ($el['label_en'] ?? $el['label_pt'] ?? ''))
            : sanitize_text_field((string) ($el['label_pt'] ?? $el['label_en'] ?? ''));
        if (!$el_label) $el_label = $el_url;

        $el_type  = sanitize_key((string) ($el['type'] ?? 'web'));
        $el_meta  = $type_icon[$el_type] ?? $default_icon;

        // Para email e telefone: não abre nova aba
        $el_target = in_array($el_type, ['email', 'phone'], true) ? '_self' : '_blank';
        $el_rel    = $el_target === '_blank' ? 'noopener noreferrer' : '';
      ?>
        <a
          href="<?php echo esc_url($el_url); ?>"
          target="<?php echo esc_attr($el_target); ?>"
          <?php if ($el_rel): ?>rel="<?php echo esc_attr($el_rel); ?>"<?php endif; ?>
          style="
            display:         inline-flex;
            align-items:     center;
            gap:             7px;
            padding:         9px 16px;
            border-radius:   8px;
            text-decoration: none;
            font-weight:     700;
            font-size:       0.88rem;
            font-family:     'Questrial', sans-serif;
            color:           var(--vana-text);
            background:      <?php echo esc_attr($el_meta['color']); ?>;
            border:          1px solid <?php echo esc_attr($el_meta['border']); ?>;
            transition:      opacity .2s, transform .2s;
          "
          aria-label="<?php echo esc_attr(
            $el_label . ($el_target === '_blank' ? ' (' . $lbl_new_tab . ')' : '')
          ); ?>"
          onmouseover="this.style.opacity='.8';this.style.transform='translateY(-2px)'"
          onmouseout="this.style.opacity='1';this.style.transform='translateY(0)'"
        >
          <span class="dashicons <?php echo esc_attr($el_meta['icon']); ?>"
                aria-hidden="true"
                style="font-size:16px;width:16px;height:16px;"></span>
          <?php echo esc_html($el_label); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; // event_links ?>

</section>
