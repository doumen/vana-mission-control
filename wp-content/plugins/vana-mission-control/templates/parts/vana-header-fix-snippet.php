<?php
/**
 * Snippet: corrected agenda SVG + scroll-top anchor
 * Include this in your theme header/footer where appropriate.
 */
?>

<!-- Corrected Agenda Button SVG (replace existing button markup) -->
<button
    type="button"
    id="vana-agenda-open-btn"
    class="vana-header__agenda-btn"
    data-drawer="vana-agenda-drawer"
    data-vana-agenda-open
    aria-expanded="false"
    aria-controls="vana-agenda-drawer"
    aria-label="<?php echo esc_attr( $lang === 'en' ? 'Schedule' : 'Agenda' ); ?>"
>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" aria-hidden="true">
        <rect x="3" y="4" width="18" height="17" rx="2"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="3" y1="9" x2="21" y2="9"/>
    </svg>
    <span class="vana-header__agenda-label">
        <?php echo esc_html( $lang === 'en' ? 'Schedule' : 'Agenda' ); ?>
    </span>
</button>

<!-- Scroll to top — include before closing </body> or in footer -->
<a href="#vana-hero-anchor"
   class="vana-scroll-top"
   aria-label="<?php echo esc_attr( $lang === 'en' ? 'Back to top' : 'Voltar ao topo' ); ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <polyline points="18 15 12 9 6 15"/>
    </svg>
</a>

<!-- Anchor placeholder: add id="vana-hero-anchor" to your hero section -->
<!-- <section id="vana-hero-anchor" class="vana-hero ..."> ... </section> -->
