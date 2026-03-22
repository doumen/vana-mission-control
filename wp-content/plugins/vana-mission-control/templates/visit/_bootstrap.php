<?php
/**
 * Bootstrap SSR — single-vana_visit
 *
 * Chamado no topo do template single da visita (antes do get_header).
 * Resolve VisitStageViewModel e expõe variáveis via extract().
 *
 * Variáveis disponíveis após este arquivo:
 *
 *   — Do ViewModel (to_template_vars):
 *       $visit_id, $visit_ref, $timeline, $overrides,
 *       $active_day, $active_day_date, $active_events, $active_event,
 *       $hero, $stage_mode,
 *       $viewer_mode, $viewer_event_key, $viewer_item_id,
 *       $editorial_hero_type, $editorial_hero_event_key, $editorial_hero_item_id,
 *       $visit_timezone, $visit_status
 *
 *   — Derivadas (calculadas aqui):
 *       $lang               string 'pt'|'en'
 *       $data               array  alias de $timeline
 *       $days               array  $timeline['days']
 *       $active_index       int    índice do dia ativo em $days
 *       $tour_id            int    ID do post pai (tour)
 *       $tour_url           string permalink do tour
 *       $tour_title         string título do tour
 *       $visit_city_ref     string referência da cidade
 *       $visit_tz_str       string string do timezone
 *       $visit_tz           DateTimeZone objeto timezone
 *       $prev_visit         array|null {id, permalink, title, has_mag}
 *       $next_visit         array|null {id, permalink, title, has_mag}
 *
 * @package VanaMissionControl
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Guard: evita double-resolve em includes aninhados ─────────────────────────
if ( isset( $vana_bootstrap_loaded ) && $vana_bootstrap_loaded === true ) {
    return;
}
$vana_bootstrap_loaded = true;

// ── PRE-LOAD: Carrega funções utilitárias do Stage antes das parts ─────────────
// (sempre carrega, não condicional, pois múltiplas funções são necessárias)
$vana_stage_file = defined( 'VANA_MC_PATH' )
    ? VANA_MC_PATH . 'inc/vana-stage.php'
    : dirname( __DIR__, 2 ) . '/inc/vana-stage.php';

if ( file_exists( $vana_stage_file ) ) {
    require_once $vana_stage_file;
}

// ── Dependências do plugin ────────────────────────────────────────────────────
if ( ! class_exists( 'VisitStageResolver' ) ) {
    wp_die(
        esc_html__( 'VanaMissionControl plugin não está ativo.', 'vana-mission-control' ),
        '',
        [ 'response' => 500 ]
    );
}

// ── Valida post ───────────────────────────────────────────────────────────────
$visit_id = get_the_ID();

if ( ! $visit_id || get_post_type( $visit_id ) !== 'vana_visit' ) {
    wp_die(
        esc_html__( 'Post inválido para este template.', 'vana-mission-control' ),
        '',
        [ 'response' => 404 ]
    );
}

// ── 1. Resolve ViewModel ──────────────────────────────────────────────────────
$vana_vm = VisitStageResolver::resolve( $visit_id );
extract( $vana_vm->to_template_vars() );

// ── 2. Idioma ─────────────────────────────────────────────────────────────────
$lang = sanitize_key( $_GET['lang'] ?? 'pt' );
$lang = in_array( $lang, [ 'pt', 'en' ], true ) ? $lang : 'pt';

// ── 3. Aliases de dados ───────────────────────────────────────────────────────
$data = $timeline;
$days = is_array( $data['days'] ?? null ) ? $data['days'] : [];

// ── 4. Índice do dia ativo ────────────────────────────────────────────────────
$active_index = 0;
foreach ( $days as $i => $d ) {
    if ( ( $d['date_local'] ?? '' ) === $active_day_date ) {
        $active_index = $i;
        break;
    }
}

// ── 5. Tour ───────────────────────────────────────────────────────────────────
$tour_id = (int) wp_get_post_parent_id( $visit_id );
if ( ! $tour_id ) {
    $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
}
$tour_url   = $tour_id ? (string) get_permalink( $tour_id )  : '';
$tour_title = $tour_id ? (string) get_the_title( $tour_id )  : '';

// ── 6. Localização & Timezone ─────────────────────────────────────────────────
$location_meta  = is_array( $data['location_meta'] ?? null ) ? $data['location_meta'] : [];
$visit_city_ref = (string) ( $location_meta['city_ref'] ?? '' );
$visit_tz_str   = (string) ( $location_meta['tz'] ?? $visit_timezone ?? 'UTC' );

try {
    $visit_tz = new DateTimeZone( $visit_tz_str ?: 'UTC' );
} catch ( Exception $e ) {
    $visit_tz = new DateTimeZone( 'UTC' );
}

// ── 7. Navegação Prev / Next ──────────────────────────────────────────────────
if ( ! function_exists( 'vana_visit_prev_next_ids' ) ) {
    function vana_visit_prev_next_ids( int $current_id ): array {
        if ( function_exists( 'vana_get_chronological_visits' ) ) {
            $sequence = vana_get_chronological_visits();
            if ( ! empty( $sequence ) ) {
                $ids = array_column( $sequence, 'id' );
                $idx = array_search( $current_id, $ids, true );
                if ( $idx !== false ) {
                    return [
                        ( $idx > 0 )                ? (int) $ids[ $idx - 1 ] : 0,
                        ( $idx < count( $ids ) - 1) ? (int) $ids[ $idx + 1 ] : 0,
                    ];
                }
            }
        }

        $start = get_post_meta( $current_id, '_vana_start_date', true );
        if ( ! $start ) {
            return [ 0, 0 ];
        }

        $base = [
            'post_type'      => 'vana_visit',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_key'       => '_vana_start_date',
        ];

        $prev_q = new WP_Query( array_merge( $base, [
            'orderby'    => 'meta_value',
            'order'      => 'DESC',
            'meta_query' => [ [ 'key' => '_vana_start_date', 'value' => $start, 'compare' => '<', 'type' => 'DATE' ] ],
        ] ) );

        $next_q = new WP_Query( array_merge( $base, [
            'orderby'    => 'meta_value',
            'order'      => 'ASC',
            'meta_query' => [ [ 'key' => '_vana_start_date', 'value' => $start, 'compare' => '>', 'type' => 'DATE' ] ],
        ] ) );

        return [
            ! empty( $prev_q->posts ) ? (int) $prev_q->posts[0] : 0,
            ! empty( $next_q->posts ) ? (int) $next_q->posts[0] : 0,
        ];
    }
}

if ( ! function_exists( '_vana_build_nav_visit' ) ) {
    function _vana_build_nav_visit( int $id, string $lang ): ?array {
        if ( $id <= 0 ) {
            return null;
        }
        $json  = get_post_meta( $id, '_vana_visit_timeline_json', true );
        $vdata = $json ? json_decode( $json, true ) : [];
        $vdata = is_array( $vdata ) ? $vdata : [];
        return [
            'id'        => $id,
            'permalink' => (string) get_permalink( $id ),
            'title'     => (string) ( $vdata[ 'title_' . $lang ] ?? $vdata['title_pt'] ?? get_the_title( $id ) ),
            'has_mag'   => get_post_meta( $id, '_vana_mag_state', true ) === 'publicada',
        ];
    }
}

[ $prev_id, $next_id ] = vana_visit_prev_next_ids( $visit_id );
$prev_visit = _vana_build_nav_visit( $prev_id, $lang );
$next_visit = _vana_build_nav_visit( $next_id, $lang );
