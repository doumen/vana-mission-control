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

  <?php
  // ── Helpers compartilhados (vana_visit_url, etc) ──
  if ( file_exists( VANA_MC_PATH . 'templates/visit/vana-utils.php' ) ) {
      include_once VANA_MC_PATH . 'templates/visit/vana-utils.php';
  }
  ?>
  <!-- ─────────────────────────────────────────────────────────────────────────
       HERO HEADER
       ───────────────────────────────────────────────────────────────────────── -->
  <?php
  if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/hero-header.php' ) ) {
      include VANA_MC_PATH . 'templates/visit/parts/hero-header.php';
  }
  ?>

  <!-- ─────────────────────────────────────────────────────────────────────────
       AGENDA DRAWER
       ───────────────────────────────────────────────────────────────────────── -->
  <?php
    // Agenda drawer removed from top; will be included inside <main> before closing.
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
    // if ( count( (array) $timeline['days'] ) > 1 ) {
    //     if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/day-tabs.php' ) ) {
    //         include VANA_MC_PATH . 'templates/visit/parts/day-tabs.php';
    //     }
    // }
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

    <!-- ── STAGE + VOD GRID ──────────────────────────────────────────────────── -->
    <div class="vana-stage-grid">
      <div class="vana-stage-grid__main">
        <!-- ─────────────────────────────────────────────────────────────────────────
             STAGE (VOD Player + Stage Fragment)
             ───────────────────────────────────────────────────────────────────────── -->
        <?php
        if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/stage.php' ) ) {
            include VANA_MC_PATH . 'templates/visit/parts/stage.php';
        }
        ?>
      </div><!-- /vana-stage-grid__main -->

      <aside class="vana-stage-grid__sidebar">
        <!-- ─────────────────────────────────────────────────────────────────────────
             VOD LIST SECTION
             ───────────────────────────────────────────────────────────────────────── -->
        <?php
        if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/vod-list.php' ) ) {
            include VANA_MC_PATH . 'templates/visit/parts/vod-list.php';
        }
        ?>
      </aside><!-- /vana-stage-grid__sidebar -->
    </div><!-- /vana-stage-grid -->

    <!-- ─────────────────────────────────────────────────────────────────────────
         UNIFIED SECTIONS PANEL
         (Hari-Katha | Galeria | Sangha | Revista)
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/sections.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/sections.php';
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
         COMMUNITY LINKS
         ───────────────────────────────────────────────────────────────────────── -->
    <?php
    if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/community-links.php' ) ) {
        include VANA_MC_PATH . 'templates/visit/parts/community-links.php';
    }
    ?>

    <!-- Inclui a gaveta da agenda dentro do main, antes do fechamento -->
    <?php
    $agenda_part = VANA_MC_PATH . 'templates/visit/parts/agenda-drawer.php';
    if ( file_exists( $agenda_part ) ) {
        include $agenda_part;
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
