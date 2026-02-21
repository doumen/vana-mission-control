<?php
/**
 * Partial: Sangha Moments (Depoimentos e Momentos da Comunidade)
 * Arquivo: templates/visit/parts/sangha-moments.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id
 *   $active_day, $active_day_date
 *
 * Responsabilidades:
 *   1. Renderizar cards de depoimentos/momentos da sangha
 *   2. Suportar tipos: quote, moment, service, realization
 *   3. Suportar avatar (URL ou inicial gerada)
 *   4. Renderizar CTA de submissão de depoimento
 *   5. Não renderizar nada se não há momentos no dia
 *
 * Estrutura esperada em day.sangha_moments[]:
 *   {
 *     "type":        "quote|moment|service|realization",
 *     "author":      "Fulano das",
 *     "role_pt":     "Devoto",
 *     "role_en":     "Devotee",
 *     "avatar_url":  "https://...",      (opcional)
 *     "text_pt":     "Depoimento...",
 *     "text_en":     "Testimonial...",
 *     "city":        "São Paulo, BR",    (opcional)
 *     "featured":    true                (opcional)
 *   }
 */
defined('ABSPATH') || exit;

$moments = is_array($active_day['sangha_moments'] ?? null)
    ? $active_day['sangha_moments']
    : [];

// Filtra itens inválidos (sem texto)
$moments = array_values(array_filter($moments, function ($m) use ($lang) {
    $key = 'text_' . $lang;
    $fb  = $lang === 'en' ? 'text_pt' : 'text_en';
    return is_array($m) && (
        !empty($m[$key]) || !empty($m[$fb])
    );
}));

$moment_count = count($moments);

// Nada a renderizar
if ($moment_count === 0) return;

// ── Mapeamento type → visual ──────────────────────────────────
$type_meta = [
    'quote'       => [
        'icon'    => '❝',
        'label'   => ['pt' => 'Citação',      'en' => 'Quote'],
        'color'   => 'var(--vana-gold)',
        'bg'      => '#fffbeb',
        'border'  => '#fde68a',
    ],
    'moment'      => [
        'icon'    => '🌸',
        'label'   => ['pt' => 'Momento',      'en' => 'Moment'],
        'color'   => 'var(--vana-pink)',
        'bg'      => '#fdf2f8',
        'border'  => '#fbcfe8',
    ],
    'service'     => [
        'icon'    => '🙏',
        'label'   => ['pt' => 'Serviço',      'en' => 'Service'],
        'color'   => 'var(--vana-orange)',
        'bg'      => '#fff7ed',
        'border'  => '#fed7aa',
    ],
    'realization' => [
        'icon'    => '✨',
        'label'   => ['pt' => 'Realização',   'en' => 'Realization'],
        'color'   => '#7c3aed',
        'bg'      => '#f5f3ff',
        'border'  => '#ddd6fe',
    ],
];

$default_type = $type_meta['moment'];

// Labels i18n
$lbl_section  = $lang === 'en' ? 'Sangha Moments'      : 'Momentos da Sangha';
$lbl_share    = $lang === 'en' ? 'Share your moment'   : 'Compartilhar seu momento';
$lbl_share_d  = $lang === 'en'
    ? 'Did this visit touch your heart? Share a quote, a realization or a moment of service with the community.'
    : 'Esta visita tocou seu coração? Compartilhe uma citação, uma realização ou um momento de serviço com a comunidade.';
$lbl_btn      = $lang === 'en' ? 'Submit →'            : 'Enviar →';
$lbl_city     = $lang === 'en' ? 'Location'            : 'Localização';
$lbl_featured = $lang === 'en' ? 'Featured'            : 'Destaque';

// URL de submissão (sobrescrita via day.moments_submit_url)
$submit_url = esc_url_raw(
    (string) ($active_day['moments_submit_url'] ?? '')
    ?: 'https://www.facebook.com/vanamadhuryamofficial'
);
?>

<section
  class="vana-section vana-section--sangha"
  aria-labelledby="vana-sangha-heading"
>

  <!-- Cabeçalho da seção -->
  <div style="display: flex; align-items: center; justify-content: space-between;
              flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">

    <h2 class="vana-section-title" id="vana-sangha-heading" style="margin-bottom: 0;">
      <?php echo esc_html($lbl_section); ?>
      <span style="
        font-size:   0.85rem;
        font-weight: 700;
        color:       var(--vana-muted);
        margin-left: 10px;
        font-family: 'Questrial', sans-serif;
      ">
        (<?php echo (int) $moment_count; ?>)
      </span>
    </h2>

    <!-- Botão rápido de submissão -->
    <a
      href="<?php echo esc_url($submit_url); ?>"
      target="_blank"
      rel="noopener noreferrer"
      style="
        display:         inline-flex;
        align-items:     center;
        gap:             6px;
        padding:         8px 16px;
        border-radius:   8px;
        background:      var(--vana-bg-soft);
        border:          1px solid var(--vana-line);
        color:           var(--vana-text);
        font-weight:     700;
        font-size:       0.88rem;
        text-decoration: none;
        transition:      background .2s;
      "
      aria-label="<?php echo esc_attr(
        $lbl_share . ' (' . ($lang === 'en' ? 'opens in new tab' : 'abre em nova aba') . ')'
      ); ?>"
      onmouseover="this.style.background='var(--vana-line)'"
      onmouseout="this.style.background='var(--vana-bg-soft)'"
    >
      <span class="dashicons dashicons-heart"
            aria-hidden="true"
            style="font-size: 15px; width: 15px; height: 15px;
                   color: var(--vana-pink);"></span>
      <?php echo esc_html($lbl_share); ?> ↗
    </a>

  </div>
  <!-- /cabeçalho -->

  <!-- Grid de cards -->
  <div
    class="vana-grid vana-grid--sangha"
    role="list"
    aria-label="<?php echo esc_attr($lbl_section); ?>"
    style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));"
  >

    <?php foreach ($moments as $idx => $moment):
      if (!is_array($moment)) continue;

      // ── Textos ──────────────────────────────────────────────
      $text   = $lang === 'en'
                  ? sanitize_textarea_field((string) ($moment['text_en'] ?? $moment['text_pt'] ?? ''))
                  : sanitize_textarea_field((string) ($moment['text_pt'] ?? $moment['text_en'] ?? ''));
      $author = sanitize_text_field((string) ($moment['author'] ?? ''));
      $role   = $lang === 'en'
                  ? sanitize_text_field((string) ($moment['role_en'] ?? $moment['role_pt'] ?? ''))
                  : sanitize_text_field((string) ($moment['role_pt'] ?? $moment['role_en'] ?? ''));
      $city        = sanitize_text_field((string) ($moment['city']       ?? ''));
      $avatar_url  = esc_url_raw((string)         ($moment['avatar_url'] ?? ''));
      $is_featured = !empty($moment['featured']);

      // ── Tipo visual ─────────────────────────────────────────
      $type    = sanitize_text_field((string) ($moment['type'] ?? 'moment'));
      $tmeta   = $type_meta[$type] ?? $default_type;
      $t_icon  = $tmeta['icon'];
      $t_label = $tmeta['label'][$lang] ?? $tmeta['label']['pt'];
      $t_color = $tmeta['color'];
      $t_bg    = $tmeta['bg'];
      $t_bdr   = $tmeta['border'];

      // ── Avatar: URL ou inicial ───────────────────────────────
      $has_avatar  = $avatar_url !== '';
      $initial     = $author ? mb_strtoupper(mb_substr($author, 0, 1)) : '?';

      // ── Aria label do card ───────────────────────────────────
      $card_aria = $t_label
          . ($author ? ' — ' . $author : '')
          . ($is_featured ? (' — ' . $lbl_featured) : '');

    ?>

      <div
        class="vana-card vana-card--moment<?php echo $is_featured ? ' vana-card--featured' : ''; ?>"
        role="listitem"
        aria-label="<?php echo esc_attr($card_aria); ?>"
        style="
          background:    <?php echo esc_attr($t_bg); ?>;
          border:        1px solid <?php echo esc_attr($t_bdr); ?>;
          border-top:    3px solid <?php echo esc_attr($t_color); ?>;
          padding:       20px;
          border-radius: 12px;
          display:       flex;
          flex-direction: column;
          gap:           14px;
          position:      relative;
        "
      >

        <!-- Badge featured -->
        <?php if ($is_featured): ?>
          <div style="
            position:       absolute;
            top:            12px;
            right:          12px;
            background:     var(--vana-gold);
            color:          #111;
            padding:        2px 10px;
            border-radius:  20px;
            font-size:      0.68rem;
            font-weight:    900;
            text-transform: uppercase;
          " aria-hidden="true">
            <?php echo esc_html($lbl_featured); ?>
          </div>
        <?php endif; ?>

        <!-- Badge tipo -->
        <div style="
          display:     inline-flex;
          align-items: center;
          gap:         6px;
          font-size:   0.78rem;
          font-weight: 900;
          color:       <?php echo esc_attr($t_color); ?>;
          text-transform: uppercase;
          width:       fit-content;
        " aria-hidden="true">
          <span><?php echo esc_html($t_icon); ?></span>
          <span><?php echo esc_html($t_label); ?></span>
        </div>

        <!-- Texto do depoimento -->
        <blockquote style="
          margin:      0;
          font-size:   1rem;
          line-height: 1.7;
          color:       var(--vana-text);
          font-style:  italic;
          position:    relative;
          padding-left: 16px;
          border-left:  3px solid <?php echo esc_attr($t_bdr); ?>;
        ">
          <?php echo nl2br(esc_html($text)); ?>
        </blockquote>

        <!-- Autor -->
        <div style="
          display:     flex;
          align-items: center;
          gap:         10px;
          margin-top:  auto;
        ">

          <!-- Avatar -->
          <?php if ($has_avatar): ?>
            <img
              src="<?php echo esc_url($avatar_url); ?>"
              alt="<?php echo esc_attr($author ?: $initial); ?>"
              loading="lazy"
              decoding="async"
              width="40"
              height="40"
              style="
                width:         40px;
                height:        40px;
                border-radius: 50%;
                object-fit:    cover;
                flex-shrink:   0;
                border:        2px solid <?php echo esc_attr($t_bdr); ?>;
              "
            >
          <?php else: ?>
            <!-- Avatar gerado por inicial -->
            <div style="
              width:           40px;
              height:          40px;
              border-radius:   50%;
              background:      <?php echo esc_attr($t_color); ?>;
              color:           #fff;
              font-weight:     900;
              font-size:       1.1rem;
              font-family:     'Syne', sans-serif;
              display:         flex;
              align-items:     center;
              justify-content: center;
              flex-shrink:     0;
              opacity:         .85;
            " aria-hidden="true">
              <?php echo esc_html($initial); ?>
            </div>
          <?php endif; ?>

          <!-- Nome + role + city -->
          <div>
            <?php if ($author): ?>
              <div style="
                font-weight: 900;
                font-family: 'Syne', sans-serif;
                font-size:   0.95rem;
                color:       var(--vana-text);
                line-height: 1.2;
              ">
                <?php echo esc_html($author); ?>
              </div>
            <?php endif; ?>

            <div style="
              font-size:   0.8rem;
              color:       var(--vana-muted);
              margin-top:  2px;
              display:     flex;
              flex-wrap:   wrap;
              gap:         4px 8px;
            ">
              <?php if ($role): ?>
                <span><?php echo esc_html($role); ?></span>
              <?php endif; ?>

              <?php if ($city): ?>
                <?php if ($role): ?><span aria-hidden="true">·</span><?php endif; ?>
                <span>
                  <span class="dashicons dashicons-location"
                        aria-hidden="true"
                        style="font-size: 12px; width: 12px; height: 12px;
                               color: var(--vana-pink); vertical-align: middle;"></span>
                  <?php echo esc_html($city); ?>
                </span>
              <?php endif; ?>
            </div>

          </div>
          <!-- /nome -->

        </div>
        <!-- /autor -->

      </div>
      <!-- /card -->

    <?php endforeach; ?>

  </div>
  <!-- /grid -->

  <!-- CTA de submissão (expandido) -->
  <div style="
    margin-top:      24px;
    padding:         20px 24px;
    border-radius:   12px;
    background:      var(--vana-bg-soft);
    border:          1px solid var(--vana-line);
    display:         flex;
    align-items:     center;
    justify-content: space-between;
    gap:             16px;
    flex-wrap:       wrap;
  ">

    <div>
      <div style="
        font-family:   'Syne', sans-serif;
        font-weight:   900;
        font-size:     1rem;
        color:         var(--vana-text);
        margin-bottom: 4px;
      ">
        <span style="margin-right: 6px;" aria-hidden="true">🙏</span>
        <?php echo esc_html($lbl_share); ?>
      </div>
      <div style="color: var(--vana-muted); font-size: 0.9rem; line-height: 1.5;">
        <?php echo esc_html($lbl_share_d); ?>
      </div>
    </div>

    <a
      href="<?php echo esc_url($submit_url); ?>"
      target="_blank"
      rel="noopener noreferrer"
      style="
        display:         inline-block;
        padding:         12px 22px;
        border-radius:   8px;
        background:      var(--vana-pink);
        color:           #fff;
        font-weight:     900;
        text-decoration: none;
        font-size:       0.95rem;
        white-space:     nowrap;
        transition:      opacity .2s;
      "
      aria-label="<?php echo esc_attr(
        $lbl_btn . ' (' . ($lang === 'en' ? 'opens in new tab' : 'abre em nova aba') . ')'
      ); ?>"
      onmouseover="this.style.opacity='.85'"
      onmouseout="this.style.opacity='1'"
    >
      <?php echo esc_html($lbl_btn); ?>
    </a>

  </div>
  <!-- /CTA -->

</section>
