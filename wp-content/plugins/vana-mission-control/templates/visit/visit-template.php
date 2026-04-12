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

// Restore variables from $GLOBALS if needed (when called from parent template)
error_log( '[visit-template start] isset($days): ' . (isset($days) ? 'YES' : 'NO') );
error_log( '[visit-template start] $GLOBALS[_vana_visit][days] count: ' . (isset($GLOBALS['_vana_visit']['days']) ? count($GLOBALS['_vana_visit']['days']) : 'NOT_SET') );

if ( !isset($visit_id) && isset($GLOBALS['_vana_visit']) ) {
    extract( $GLOBALS['_vana_visit'], EXTR_IF_EXISTS );
    error_log( '[visit-template] After extract, $days count: ' . count($days ?? []) );
}

// Also explicitly get days from globals
if ( !isset($days) ) {
    $days = $GLOBALS['_vana_visit']['days'] ?? [];
    error_log( '[visit-template] Assigned days from GLOBALS, count: ' . count($days) );
}
if ( !isset($index) ) {
    $index = isset($GLOBALS['_vana_visit']['index']) ? $GLOBALS['_vana_visit']['index'] : [];
}
if ( !isset($lang) ) {
    $lang = $GLOBALS['_vana_visit']['lang'] ?? 'pt';
}

error_log( '[visit-template before guard] $days count: ' . count($days ?? []));

if ( ! isset( $visit_id, $timeline, $active_day, $active_events ) ) {
    error_log( '[visit-template] GUARD RETURN - $days still available?: ' . count($days ?? []) );
    return;
}

error_log( '[visit-template after guard] $days count: ' . count($days ?? []));

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

        <!-- ═══════════════════════════════════════════════════════════════
                 ZONA MUTÁVEL — v6.1 (PR-1: extraída do stage.php)

                 Documento de Decisões v4 §15.1:
                 "A zona mutável é o ÚNICO lugar que troca.
                    Header, hero, chips, stage são MOLDURA."

                 Contém sub-zona #vana-stage-katha para compatibilidade
                 com VanaStageController.js (será absorvida em PR futuro).

                 Container controlado pelo VanaStateRouter.js.
                 SSR renderiza estado inicial; JS assume depois.
                 ═══════════════════════════════════════════════════════════════ -->
        <?php
        // Estado SSR: inferido do evento ativo.
        // $stage_katha_id é definido em stage.php e sobrevive via include().
        $_mz_katha_id = isset( $stage_katha_id ) ? (string) $stage_katha_id : '';
        $_mz_state    = $_mz_katha_id !== '' ? 'katha' : 'neutral';
        ?>
        <div
                class="vana-mutable-zone"
                id="vana-mutable-zone"
                data-state="<?php echo esc_attr( $_mz_state ); ?>"
                data-event-key="<?php echo esc_attr( $active_event['event_key'] ?? '' ); ?>"
                data-katha-id="<?php echo esc_attr( $_mz_katha_id ); ?>"
                aria-live="polite"
                aria-label="<?php echo esc_attr(
                        function_exists( 'vana_t' )
                                ? vana_t( 'stage.mutable_zone', $lang )
                                : 'Conteúdo contextual'
                ); ?>"
        >
            <!-- Sub-zona katha: VanaStageController.js injeta passages aqui -->
            <div
                    class="vana-stage-katha"
                    id="vana-stage-katha"
                    aria-label="<?php echo esc_attr(
                            function_exists( 'vana_t' )
                                    ? vana_t( 'stage.katha_passages', $lang )
                                    : 'Passagens Hari-Katha'
                    ); ?>"
                    aria-live="polite"
                    hidden
            ></div>

            <?php if ( $_mz_state === 'katha' && $_mz_katha_id !== '' ) : ?>
                <!-- SSR hint: skeleton katha (evita CLS) -->
                <div class="vana-mz__skeleton vana-mz__skeleton--katha" aria-hidden="true">
                    <div class="vana-mz__skeleton-line" style="width:60%"></div>
                    <div class="vana-mz__skeleton-line" style="width:90%"></div>
                    <div class="vana-mz__skeleton-line" style="width:75%"></div>
                </div>
            <?php endif; ?>
        </div><!-- /vana-mutable-zone -->
        <?php unset( $_mz_katha_id, $_mz_state ); ?>

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
    // Crítico: Garantir que $days, $index, $lang, $visit_id estão presentes
    if ( !isset($days) || empty($days) ) {
        $days = $GLOBALS['_vana_visit']['days'] ?? $timeline['days'] ?? [];
        error_log('[CRITICAL FIX] $days was empty, assigned from globals. Now count: ' . count($days));
    }
    if ( !isset($index) ) {
        $index = $GLOBALS['_vana_visit']['index'] ?? [];
    }
    if ( !isset($lang) ) {
        $lang = $GLOBALS['_vana_visit']['lang'] ?? 'pt';
    }
    
    error_log('[DRAWER INCLUDE] $days count RIGHT NOW: ' . count($days ?? []) . ' | is_array: ' . (is_array($days) ? 'YES' : 'NO'));
    
    $agenda_part = VANA_MC_PATH . 'templates/visit/parts/agenda-drawer.php';
    if ( file_exists( $agenda_part ) ) {
        include $agenda_part;
    }
    ?>
        <script>
        /* ── Vana CFG — Schema 6.1 ─────────────────────────────────────────────
             Expõe o timeline SSR ao JS para:
                 - getVodIndex()        → monta _vodIndex[vod_key] = vod
                 - vana:event:select    → swapStageYouTube()
                 - initAgendaDrawer()   → renderiza agenda lateral
             ─────────────────────────────────────────────────────────────────── */
        window.CFG = {

            // ── Identidade da visita ──────────────────────────────────────────
            visitId:      <?php echo (int) $visit_id; ?>,
            visitRef:     <?php echo wp_json_encode( $timeline['visit_ref']  ?? '' ); ?>,
            lang:         <?php echo wp_json_encode( $lang ); ?>,
            timezone:     <?php echo wp_json_encode( $visit_tz_str ?? 'UTC' ); ?>,

            // ── Dia e evento ativos (SSR) ─────────────────────────────────────
            activeDayKey:   <?php echo wp_json_encode( $active_day_date ?? '' ); ?>,
            activeEventKey: <?php echo wp_json_encode( $active_event['event_key'] ?? '' ); ?>,

            // ── Timeline completo (Schema 6.1) ───────────────────────────────
            // days[]  → fonte da verdade para agenda e VODs
            // index{} → lookup O(1) para vods/segments/kathas
            timeline: <?php
                echo wp_json_encode( [
                    'days'    => $timeline['days']    ?? [],
                    'index'   => $timeline['index']   ?? [],
                    'orphans' => $timeline['orphans'] ?? [],
                    'stats'   => $timeline['stats']   ?? [],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            ?>,

            // ── REST roots ───────────────────────────────────────────────────
            restRoot:  <?php echo wp_json_encode( rest_url( 'vana/v1' ) ); ?>,
            restNonce: <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,

            // ── Flags de modo ────────────────────────────────────────────────
            visitStatus: <?php echo wp_json_encode( $visit_status ?? 'past' ); ?>,
            stageMode:   <?php echo wp_json_encode( $stage_mode   ?? 'vod'  ); ?>,
        };

        /* ── Debug SSR (remove em produção) ─────────────────────────────── */
        console.log('[visit-template] Data keys:',
            <?php echo wp_json_encode( array_keys( $timeline ?? [] ) ); ?>
        );
        console.log('[visit-template] Days available:',
            <?php echo is_array( $timeline['days'] ?? null ) ? count( $timeline['days'] ) : 0; ?>
        );
        console.log('[visit-template] timeline.days count:',
            CFG.timeline.days.length
        );
        console.log('[visit-template] timeline days:',
            JSON.stringify(CFG.timeline.days).substring(0, 200)
        );
        </script>

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
