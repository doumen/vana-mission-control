<?php
/**
 * Part: Revista Card
 * Exibe link/card para a revista/publicação da visita (se disponível).
 *
 * Variáveis esperadas (via _bootstrap_shim.php):
 *   $visit_data  — array com dados da visita
 */
defined('ABSPATH') || exit;

// Dados da revista (opcional — não quebra se ausente)
$revista_url   = $visit_data['revista_url']   ?? '';
$revista_title = $visit_data['revista_title'] ?? __( 'Revista da Visita', 'vana-mc' );
$revista_cover = $visit_data['revista_cover'] ?? '';

if ( empty( $revista_url ) ) {
    return; // Silencioso — sem dados, sem output
}
?>
<section
  id="vana-section-revista"
  class="vana-revista-card" 
  aria-label="<?php esc_attr_e( 'Revista', 'vana-mc' ); ?>">
  <a href="<?php echo esc_url( $revista_url ); ?>"
     target="_blank" rel="noopener noreferrer"
     class="vana-revista-card__link">
    <?php if ( $revista_cover ) : ?>
      <img src="<?php echo esc_url( $revista_cover ); ?>"
           alt="<?php echo esc_attr( $revista_title ); ?>"
           class="vana-revista-card__cover" loading="lazy" />
    <?php endif; ?>
    <span class="vana-revista-card__title">
      <?php echo esc_html( $revista_title ); ?>
    </span>
  </a>
</section>
