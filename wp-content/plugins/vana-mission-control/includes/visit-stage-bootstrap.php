<?php
/**
 * Stage bootstrap helper — canonical readers and minimal bootstrap for stage templates
 *
 * Provides a single entrypoint to read timeline, timezone and resolve active event/day
 * to reduce divergence between SSR and REST renderers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-visit-event-resolver.php';

/**
 * Return canonical stage bootstrap data for a visit.
 *
 * Optional $opts keys:
 *  - requested_event_key: (string) explicit event_key to resolve
 *  - requested_day: (string) explicit day slug/date to resolve
 *  - lang: (string) language code
 *
 * @param int $visit_id
 * @param array $opts
 * @return array{
 *   visit_id:int,
 *   timeline:array,
 *   overrides:array,
 *   visit_tz:string,
 *   visit_status:string,
 *   visit_city_ref:string,
 *   lang:string,
 *   event_data:array,
 *   active_event:array|null,
 *   active_day:array|null,
 *   active_day_date:string
 * }
 */
function vana_visit_stage_bootstrap( int $visit_id, array $opts = [] ): array {
    $lang = (string) ( $opts['lang'] ?? ( isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : 'pt' ) );

    // 1. Timeline — canonical key: _vana_visit_timeline_json ; fallback legacy _vana_visit_data
    $raw = get_post_meta( $visit_id, '_vana_visit_timeline_json', true );
    if ( empty( $raw ) ) {
        $legacy = get_post_meta( $visit_id, '_vana_visit_data', true );
        if ( is_string( $legacy ) && $legacy !== '' ) {
            $decoded = json_decode( $legacy, true );
            $timeline = is_array( $decoded ) ? $decoded : [];
        } elseif ( is_array( $legacy ) ) {
            $timeline = $legacy;
        } else {
            $timeline = [];
        }
    } else {
        $decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        $timeline = is_array( $decoded ) ? $decoded : [];
    }

    // 2. Overrides (keep existing key)
    $overrides_raw = get_post_meta( $visit_id, '_vana_overrides_json', true );
    $overrides = is_string( $overrides_raw ) ? ( json_decode( $overrides_raw, true ) ?: [] ) : ( is_array( $overrides_raw ) ? $overrides_raw : [] );

    // 3. Timezone — canonicalize to _vana_visit_timezone with fallback to _vana_tz
    $visit_tz = (string) ( get_post_meta( $visit_id, '_vana_visit_timezone', true ) ?: '' );
    if ( $visit_tz === '' ) {
        $visit_tz = (string) ( get_post_meta( $visit_id, '_vana_tz', true ) ?: 'UTC' );
    }
    if ( $visit_tz === '' ) {
        $visit_tz = 'UTC';
    }

    // 4. Visit status — prefer timeline; fallback to metadata.status then post meta
    $visit_status = (string) (
        $timeline['visit_status']
        ?? ( $timeline['metadata']['status'] ?? null )
        ?? get_post_meta( $visit_id, '_vana_visit_status', true )
        ?: ''
    );

    // 5. City ref
    $visit_city_ref = (string) ( $timeline['visit_city_ref'] ?? ( $timeline['location_meta']['city_ref'] ?? '' ) ?: '' );

    // 6. Resolve active context via VisitEventResolver (reuses same logic as SSR)
    $requested_event_key = (string) ( $opts['requested_event_key'] ?? ( isset( $_GET['event_key'] ) ? sanitize_text_field( wp_unslash( $_GET['event_key'] ) ) : '' ) );
    $requested_day = (string) ( $opts['requested_day'] ?? ( isset( $_GET['v_day'] ) ? sanitize_text_field( wp_unslash( $_GET['v_day'] ) ) : '' ) );

    $event_data = VisitEventResolver::resolve( $timeline, $overrides, $requested_event_key, $requested_day, $visit_tz );

    $active_event = $event_data['active_event'] ?? null;
    $active_day = $event_data['active_day'] ?? null;
    $active_day_date = (string) ( $event_data['active_day_date'] ?? '' );

    return [
        'visit_id'        => $visit_id,
        'timeline'        => $timeline,
        'overrides'       => $overrides,
        'visit_tz'        => $visit_tz,
        'visit_status'    => $visit_status,
        'visit_city_ref'  => $visit_city_ref,
        'lang'            => $lang,
        'event_data'      => $event_data,
        'active_event'    => $active_event,
        'active_day'      => $active_day,
        'active_day_date' => $active_day_date,
    ];
}
