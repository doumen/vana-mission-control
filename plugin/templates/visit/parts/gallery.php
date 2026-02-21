<?php
/**
 * Partial: Gallery (Galeria de Fotos do Dia)
 * Arquivo: templates/visit/parts/gallery.php
 *
 * Variáveis esperadas do _bootstrap.php:
 *   $lang, $visit_id
 *   $active_day, $active_day_date
 *
 * Responsabilidades:
 *   1. Renderizar grid de fotos do dia (day.photos[])
 *   2. Suportar lightbox via VanaGallery (visit-scripts.php)
 *   3. Suportar legendas e créditos por foto
 *   4. Renderizar CTA de contribuição (adicionar fotos)
 *   5. Não renderizar nada se não há fotos no dia
 *
 * Estrutura esperada em day.photos[]:
 *   {
 *     "thumb_url":   "https://...",   (obrigatório)
 *     "full_url":    "https://...",   (opcional — usa thumb se ausente)
 *     "caption_pt":  "Descrição",     (opcional)
 *     "caption_en":  "Description",   (opcional)
 *     "credit":      "@fotógrafo",    (opcional)
 *     "featured":    true             (opcional — destaque maior no grid)
 *   }
 */
defined('ABSPATH') || exit;

$photos = is_array($active_day['photos'] ?? null) ? $active_day['photos'] : [];

// Filtra fotos inválidas (sem thumb_url)
$photos = array_values(array_filter($photos, function ($p) {
    return is_array($p) && !empty($p['thumb_url']);
}));

$photo_count = count($photos);

// Nada a renderizar se não há fotos
if ($photo_count === 0) return;

// Labels i18n
$lbl_gallery     = $lang === 'en' ? 'Photos'          : 'Fotos';
$lbl_add_photos  = $lang === 'en' ? 'Add your photos' : 'Adicionar suas fotos';
$lbl_add_desc    = $lang === 'en'
    ? 'Were you there? Share your photos of this visit with the community.'
    : 'Você estava lá? Compartilhe suas fotos desta visita com a comunidade.';
$lbl_send        = $lang === 'en' ? 'Send photos →'   : 'Enviar fotos →';
$lbl_photo_of    = $lang === 'en' ? 'Photo'            : 'Foto';
$lbl_credit      = $lang === 'en' ? 'Photo by'         : 'Foto por';
$lbl_featured    = $lang === 'en' ? 'Featured'         : 'Destaque';

// URL de envio de fotos (pode ser sobrescrita por day.photos_submit_url)
$photos_submit_url = esc_url_raw(
    (string) ($active_day['photos_submit_url'] ?? '')
    ?: 'https://www.facebook.com/vanamadhuryamofficial'
);
?>

<section
  class="vana-section vana-section--gallery"
  aria-labelledby="vana-gallery-heading"
>

  <!-- Cabeçalho da seção -->
  <div style="display: flex; align-items: center; justify-content: space-between;
              flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">

    <h2 class="vana-section-title" id="vana-gallery-heading" style="margin-bottom: 0;">
      <?php echo esc_html($lbl_gallery); ?>
      <span style="
        font-size:   0.85rem;
        font-weight: 700;
        color:       var(--vana-muted);
        margin-left: 10px;
        font-family: 'Questrial', sans-serif;
      ">
        (<?php echo (int) $photo_count; ?>)
      </span>
    </h2>

    <!-- Botão rápido de contribuição -->
    <a
      href="<?php echo esc_url($photos_submit_url); ?>"
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
        $lbl_add_photos . ' (' . ($lang === 'en' ? 'opens in new tab' : 'abre em nova aba') . ')'
      ); ?>"
      onmouseover="this.style.background='var(--vana-line)'"
      onmouseout="this.style.background='var(--vana-bg-soft)'"
    >
      <span class="dashicons dashicons-camera-alt"
            aria-hidden="true"
            style="font-size: 15px; width: 15px; height: 15px;
                   color: var(--vana-pink);"></span>
      <?php echo esc_html($lbl_add_photos); ?> ↗
    </a>

  </div>

  <!-- Grid masonry-like via CSS columns -->
  <div
    class="vana-gallery-grid"
    role="list"
    aria-label="<?php echo esc_attr($lbl_gallery); ?>"
    style="
      columns:        3 200px;
      column-gap:     12px;
    "
  >

    <?php foreach ($photos as $idx => $photo):

      $thumb_url  = esc_url_raw((string)  ($photo['thumb_url']  ?? ''));
      $full_url   = esc_url_raw((string)  ($photo['full_url']   ?? $thumb_url));
      $caption    = $lang === 'en'
                      ? sanitize_text_field((string) ($photo['caption_en'] ?? $photo['caption_pt'] ?? ''))
                      : sanitize_text_field((string) ($photo['caption_pt'] ?? $photo['caption_en'] ?? ''));
      $credit     = sanitize_text_field((string) ($photo['credit']   ?? ''));
      $is_featured = !empty($photo['featured']);

      // Texto alternativo acessível
      $alt = $caption
          ?: ($lbl_photo_of . ' ' . ($idx + 1)
              . ($active_day_date ? ' — ' . $active_day_date : ''));

    ?>

      <div
        class="vana-gallery-item<?php echo $is_featured ? ' vana-gallery-item--featured' : ''; ?>"
        role="listitem"
        style="
          break-inside:  avoid;
          margin-bottom: 12px;
          border-radius: 10px;
          overflow:      hidden;
          position:      relative;
          cursor:        pointer;
          border:        1px solid var(--vana-line);
          <?php echo $is_featured ? 'column-span: all;' : ''; ?>
        "
        data-vana-photo="1"
        data-full="<?php echo esc_attr($full_url); ?>"
        data-caption="<?php echo esc_attr(
          $caption . ($credit ? ' — ' . $lbl_credit . ': ' . $credit : '')
        ); ?>"
        tabindex="0"
        role="button"
        aria-label="<?php echo esc_attr(
          ($lang === 'en' ? 'Open photo ' : 'Abrir foto ') . ($idx + 1)
          . ($caption ? ': ' . $caption : '')
        ); ?>"
      >

        <!-- Imagem -->
        <img
          src="<?php echo esc_url($thumb_url); ?>"
          alt="<?php echo esc_attr($alt); ?>"
          loading="lazy"
          decoding="async"
          style="
            display:    block;
            width:      100%;
            height:     auto;
            transition: transform .3s, filter .3s;
          "
          onmouseover="this.style.transform='scale(1.03)';this.style.filter='brightness(.9)'"
          onmouseout="this.style.transform='scale(1)';this.style.filter='brightness(1)'"
        >

        <!-- Badge destaque -->
        <?php if ($is_featured): ?>
          <div style="
            position:       absolute;
            top:            10px;
            left:           10px;
            background:     var(--vana-gold);
            color:          #111;
            padding:        3px 10px;
            border-radius:  20px;
            font-size:      0.7rem;
            font-weight:    900;
            text-transform: uppercase;
            z-index:        2;
          " aria-hidden="true">
            <?php echo esc_html($lbl_featured); ?>
          </div>
        <?php endif; ?>

        <!-- Overlay de legenda (hover) -->
        <?php if ($caption || $credit): ?>
          <div style="
            position:        absolute;
            bottom:          0;
            left:            0;
            right:           0;
            background:      linear-gradient(transparent, rgba(0,0,0,.72));
            color:           #fff;
            padding:         24px 12px 10px;
            font-size:       0.82rem;
            line-height:     1.4;
            opacity:         0;
            transition:      opacity .25s;
            pointer-events:  none;
            z-index:         2;
          "
          class="vana-gallery-caption"
          aria-hidden="true"
          >
            <?php if ($caption): ?>
              <div style="font-weight: 700;">
                <?php echo esc_html($caption); ?>
              </div>
            <?php endif; ?>
            <?php if ($credit): ?>
              <div style="opacity: .8; font-size: .75rem; margin-top: 2px;">
                <?php echo esc_html($lbl_credit . ': ' . $credit); ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </div>
      <!-- /item -->

    <?php endforeach; ?>

  </div>
  <!-- /grid -->

  <!-- CTA de contribuição (expandido) -->
  <div style="
    margin-top:   24px;
    padding:      20px 24px;
    border-radius: 12px;
    background:   var(--vana-bg-soft);
    border:       1px solid var(--vana-line);
    display:      flex;
    align-items:  center;
    justify-content: space-between;
    gap:          16px;
    flex-wrap:    wrap;
  ">

    <div>
      <div style="
        font-family: 'Syne', sans-serif;
        font-weight: 900;
        font-size:   1rem;
        color:       var(--vana-text);
        margin-bottom: 4px;
      ">
        <span class="dashicons dashicons-camera-alt"
              aria-hidden="true"
              style="font-size: 1.1rem; width: auto; height: auto;
                     color: var(--vana-pink); margin-right: 6px;"></span>
        <?php echo esc_html($lbl_add_photos); ?>
      </div>
      <div style="color: var(--vana-muted); font-size: 0.9rem; line-height: 1.5;">
        <?php echo esc_html($lbl_add_desc); ?>
      </div>
    </div>

    <a
      href="<?php echo esc_url($photos_submit_url); ?>"
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
        $lbl_send . ' (' . ($lang === 'en' ? 'opens in new tab' : 'abre em nova aba') . ')'
      ); ?>"
      onmouseover="this.style.opacity='.85'"
      onmouseout="this.style.opacity='1'"
    >
      <?php echo esc_html($lbl_send); ?>
    </a>

  </div>
  <!-- /CTA contribuição -->

</section>

<!-- CSS: overlay legenda visível no hover/focus -->
<style>
.vana-gallery-item:hover   .vana-gallery-caption,
.vana-gallery-item:focus   .vana-gallery-caption,
.vana-gallery-item:focus-within .vana-gallery-caption {
  opacity: 1 !important;
}
.vana-gallery-item:focus {
  outline: 3px solid var(--vana-gold);
  outline-offset: 2px;
}
</style>
