<?php
/**
 * Visit Template - Main Render
 * 
 * Fase 3: Renderiza a visita completa com:
 * - Hero Header
 * - Navigation (day-tabs, event-selector)
 * - Stage (VOD player + stage)
 * - Conteúdo auxiliar (hari-katha, schedule, gallery, etc)
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $visit_id, $timeline, $active_day, $active_events ) ) {
    return;
}

// Variáveis disponíveis do _bootstrap.php:
// $visit_id, $timeline, $active_day, $active_events, $active_event
// $hero, $stage_mode, $lang, $visit_status, $viewer_mode

?>

<div class="vana-visit" data-visit-id="<?php echo esc_attr( $visit_id ); ?>">

  <!-- ─────────────────────────────────────────────────────────────────────────
       HERO HEADER
       ───────────────────────────────────────────────────────────────────────── -->
  <?php
  if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/hero-header.php' ) ) {
      include VANA_MC_PATH . 'templates/visit/parts/hero-header.php';
  }
  ?>

  <!-- ─────────────────────────────────────────────────────────────────────────
       ANCRE SHORTCUTS (Hari Katha, Schedule, VOD, etc)
       ───────────────────────────────────────────────────────────────────────── -->
  <?php
  if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/anchor-chips.php' ) ) {
      include VANA_MC_PATH . 'templates/visit/parts/anchor-chips.php';
  }
  ?>

  <main class="vana-visit-main">

    <!-- ─────────────────────────────────────────────────────────────────────────
         DAY TABS (navegação entre dias)
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( count( (array) $timeline['days'] ) > 1 ) {
        if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/day-tabs.php' ) ) {
            include VANA_MC_PATH . 'templates/visit/parts/day-tabs.php';
        }
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         EVENT SELECTOR (Se múltiplos eventos no dia)
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( is_array( $active_events ) && count( $active_events ) > 1 ) {
        if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/event-selector.php' ) ) {
            include VANA_MC_PATH . 'templates/visit/parts/event-selector.php';
        }
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         STAGE (VOD Player + Stage Fragment)
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/stage.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/stage.php';
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         HARI KATHA SECTION
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/hari-katha.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/hari-katha.php';
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         SCHEDULE SECTION
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/schedule.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/schedule.php';
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         SANGHA MOMENTS SECTION
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/sangha-moments.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/sangha-moments.php';
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         GALLERY SECTION
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/gallery.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/gallery.php';
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         VOD LIST SECTION
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/vod-list.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/vod-list.php';
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         REVISTA CARD SECTION
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/revista-card.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/revista-card.php';
    }
    ?>

    <!-- ─────────────────────────────────────────────────────────────────────────
         COMMUNITY LINKS
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/community-links.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/community-links.php';
    }
    ?>

  </main>

  <!-- ─────────────────────────────────────────────────────────────────────────
       ASSETS: Styles + Scripts
       ───────────────────────────────────────────────────────────────────────── -->
  <?php
  if ( file_exists( VANA_MC_PATH . 'templates/visit/assets/visit-style.php' ) ) {
      include VANA_MC_PATH . 'templates/visit/assets/visit-style.php';
  }
  if ( file_exists( VANA_MC_PATH . 'templates/visit/assets/visit-scripts.php' ) ) {
      include VANA_MC_PATH . 'templates/visit/assets/visit-scripts.php';
  }
  ?>

</div>
