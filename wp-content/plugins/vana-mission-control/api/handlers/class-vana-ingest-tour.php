<?php
/**
 * Handler: Vana_Ingest_Tour
 * Upsert de tours via REST API (kind=tour).
 *
 * Payload esperado:
 * {
 *   "kind":        "tour",
 *   "origin_key":  "tour:india_2026",
 *   "data": {
 *     "region_code":  "IND",
 *     "season_code":  "KAR",
 *     "year_start":   2026,
 *     "year_end":     2026,
 *     "title_pt":     "Tour Espiritual Índia 2026",
 *     "title_en":     "India Spiritual Tour 2026"
 *   }
 * }
 *
 * @package Vana_Mission_Control
 */

defined( 'ABSPATH' ) || exit;

class Vana_Ingest_Tour {

    /**
     * Upsert de tour — idempotente por origin_key.
     */
    public static function upsert( array $payload ): WP_REST_Response {

        // ── 1. Validação de envelope ──────────────────────────────────────────
        $origin_key = Vana_Utils::sanitize_origin_key(
            (string) ( $payload['origin_key'] ?? '' )
        );

        if ( $origin_key === '' || strpos( $origin_key, 'tour:' ) !== 0 ) {
            return Vana_Utils::api_response(
                false,
                'Envelope inválido: origin_key inválido (esperado prefixo tour:)',
                422
            );
        }

        // ── 2. Extrair e sanitizar data ───────────────────────────────────────
        $data        = is_array( $payload['data'] ?? null ) ? $payload['data'] : [];
        $s           = 'sanitize_text_field';
        $region_code = strtoupper( $s( (string) ( $data['region_code'] ?? '' ) ) );
        $season_code = strtoupper( $s( (string) ( $data['season_code'] ?? '' ) ) );
        $year_start  = (int) ( $data['year_start'] ?? 0 );
        $year_end    = (int) ( $data['year_end']   ?? 0 );
        $title_pt    = $s( (string) ( $data['title_pt'] ?? '' ) );
        $title_en    = $s( (string) ( $data['title_en'] ?? '' ) );

        // Título WP: title_pt > title_en > origin_key
        $wp_title = $title_pt ?: $title_en ?: $origin_key;

        // ── 3. Lock por origin_key (evita race condition) ─────────────────────
        $lock_key = 'vana_lock_tour_' . md5( $origin_key );
        if ( get_transient( $lock_key ) ) {
            return Vana_Utils::api_response(
                false,
                'Processamento em andamento para este origin_key',
                409
            );
        }
        set_transient( $lock_key, 1, 30 );

        try {
            // ── 4. Lookup por origin_key (idempotência) ───────────────────────
            $existing = get_posts( [
                'post_type'      => 'vana_tour',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_query'     => [ [
                    'key'   => '_vana_origin_key',
                    'value' => $origin_key,
                ] ],
                'fields'         => 'ids',
            ] );

            $tour_id  = ! empty( $existing ) ? (int) $existing[0] : 0;
            $is_new   = ( $tour_id === 0 );

            // ── 5. Criar ou atualizar o post ──────────────────────────────────
            $post_data = [
                'post_type'   => 'vana_tour',
                'post_title'  => $wp_title,
                'post_status' => 'publish',
            ];

            if ( $is_new ) {
                $tour_id = wp_insert_post( $post_data, true );
                if ( is_wp_error( $tour_id ) ) {
                    delete_transient( $lock_key );
                    return Vana_Utils::api_response(
                        false,
                        'Erro ao criar tour: ' . $tour_id->get_error_message(),
                        500
                    );
                }
            } else {
                $post_data['ID'] = $tour_id;
                wp_update_post( $post_data );
            }

            // ── 6. Gravar metas ───────────────────────────────────────────────
            update_post_meta( $tour_id, '_vana_origin_key',  $origin_key  );
            update_post_meta( $tour_id, '_vana_region_code', $region_code );
            update_post_meta( $tour_id, '_vana_season_code', $season_code );
            update_post_meta( $tour_id, '_vana_title_pt',    $title_pt    );
            update_post_meta( $tour_id, '_vana_title_en',    $title_en    );

            if ( $year_start > 0 ) {
                update_post_meta( $tour_id, '_vana_year_start', $year_start );
            }
            if ( $year_end > 0 ) {
                update_post_meta( $tour_id, '_vana_year_end', $year_end );
            }

            delete_transient( $lock_key );

            // ── 7. Resposta ───────────────────────────────────────────────────
            return Vana_Utils::api_response( true, $is_new ? 'Tour criada' : 'Tour atualizada', 200, [
                'tour_id'     => $tour_id,
                'origin_key'  => $origin_key,
                'is_new'      => $is_new,
                'region_code' => $region_code,
                'season_code' => $season_code,
                'year_start'  => $year_start,
                'year_end'    => $year_end,
            ] );

        } catch ( \Throwable $e ) {
            delete_transient( $lock_key );
            return Vana_Utils::api_response( false, 'Exceção: ' . $e->getMessage(), 500 );
        }
    }
}
