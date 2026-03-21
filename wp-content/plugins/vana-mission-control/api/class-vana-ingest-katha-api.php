<?php
/**
 * Vana Ingest Katha API
 *
 * Endpoint:  POST /wp-json/vana/v1/ingest-katha
 * Auth:      HMAC (mesmo padrão do plugin)
 * Schema:    3.2
 */
defined( 'ABSPATH' ) || exit;

final class Vana_Ingest_Katha_API {

    public static function register(): void {
        register_rest_route( 'vana/v1', '/ingest-katha', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle' ],
            'permission_callback' => [ 'Vana_Ingest_API', 'check_permission' ],
        ] );
    }

    // ──────────────────────────────────────────────────────────────
    // Entry point
    // ──────────────────────────────────────────────────────────────

    public static function handle( WP_REST_Request $request ): WP_REST_Response {

        $raw = $request->get_body();

        if ( empty( $raw ) ) {
            return Vana_Utils::api_response( false, 'Payload vazio.', 400 );
        }

        $payload = json_decode( $raw, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return Vana_Utils::api_response( false, 'JSON inválido: ' . json_last_error_msg(), 400 );
        }

        // Validação de schema
        $schema_error = self::validate_schema( $payload );
        if ( $schema_error ) {
            return Vana_Utils::api_response( false, $schema_error, 422 );
        }

        // Processamento
        try {
            $result = self::process( $payload );
        } catch ( Exception $e ) {
            return Vana_Utils::api_response( false, 'Erro interno: ' . $e->getMessage(), 500 );
        }

        return Vana_Utils::api_response( true, 'Katha ingested successfully.', 200, $result );
    }

    // ──────────────────────────────────────────────────────────────
    // Validação
    // ──────────────────────────────────────────────────────────────

    private static function validate_schema( array $payload ): string {

        if ( ( $payload['schema_version'] ?? '' ) !== '3.2' ) {
            return 'schema_version deve ser "3.2". Recebido: ' . ( $payload['schema_version'] ?? 'ausente' );
        }

        $katha_ref = trim( $payload['context']['katha_ref'] ?? '' );
        if ( $katha_ref === '' ) {
            return 'context.katha_ref é obrigatório.';
        }

        if ( ! preg_match( '/^[a-z0-9][a-z0-9\-]{2,119}$/', $katha_ref ) ) {
            return 'context.katha_ref tem formato inválido. Use apenas letras minúsculas, números e hífens.';
        }

        if ( empty( $payload['context']['day_key'] ) ) {
            return 'context.day_key é obrigatório.';
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $payload['context']['day_key'] ) ) {
            return 'context.day_key deve estar no formato YYYY-MM-DD.';
        }

        if ( empty( trim( $payload['lecture']['title'] ?? '' ) ) ) {
            return 'lecture.title é obrigatório.';
        }

        if ( empty( $payload['passages'] ) || ! is_array( $payload['passages'] ) ) {
            return 'passages[] é obrigatório e deve ser um array não-vazio.';
        }

        foreach ( $payload['passages'] as $i => $p ) {
            if ( empty( $p['passage_ref'] ) ) {
                return "passages[$i].passage_ref é obrigatório.";
            }
            if ( ! isset( $p['index'] ) ) {
                return "passages[$i].index é obrigatório.";
            }
        }

        return '';
    }

    // ──────────────────────────────────────────────────────────────
    // Processamento principal
    // ──────────────────────────────────────────────────────────────

    private static function process( array $payload ): array {

        $context = $payload['context'];
        $lecture = $payload['lecture'];
        $katha_ref = sanitize_text_field( $context['katha_ref'] );

        // 1. Resolver visit_id
        $visit_id = self::resolve_visit_id( $context );

        // 2. Upsert do vana_katha
        [ $katha_id, $katha_created ] = self::upsert_katha(
            $katha_ref,
            $visit_id,
            $context,
            $lecture,
            $payload['lecture_taxonomies'] ?? [],
            $payload['verses_cited']       ?? [],
            $payload['glossary']           ?? [],
            $payload['notes']              ?? []
        );

        // 3. Upsert dos hk_passage
        $passages_result = self::upsert_passages(
            $katha_id,
            $katha_ref,
            $visit_id,
            $context,
            $payload['passages']
        );

        return [
            'katha_id'          => $katha_id,
            'katha_ref'         => $katha_ref,
            'katha_created'     => $katha_created,
            'visit_id'          => $visit_id,
            'passages_upserted' => $passages_result['upserted'],
            'passages_created'  => $passages_result['created'],
            'passages_updated'  => $passages_result['updated'],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Resolver visit_id
    // ──────────────────────────────────────────────────────────────

    private static function resolve_visit_id( array $context ): int {

        // Prioridade 1: visit_id direto no contexto
        if ( ! empty( $context['visit_id'] ) ) {
            return (int) $context['visit_id'];
        }

        // Prioridade 2: busca por visit_ref via meta
        $visit_ref = sanitize_text_field( $context['visit_ref'] ?? '' );
        if ( $visit_ref !== '' ) {
            $posts = get_posts( [
                'post_type'      => 'vana_visit',
                'post_status'    => [ 'publish', 'draft', 'private' ],
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'   => '_vana_origin_key',
                        'value' => $visit_ref,
                    ],
                ],
            ] );

            if ( ! empty( $posts ) ) {
                return (int) $posts[0];
            }
        }

        // Prioridade 3: busca por day_key
        $day_key = sanitize_text_field( $context['day_key'] ?? '' );
        if ( $day_key !== '' ) {
            $posts = get_posts( [
                'post_type'      => 'vana_visit',
                'post_status'    => [ 'publish', 'draft', 'private' ],
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'   => '_vana_visit_start_date',
                        'value' => $day_key,
                    ],
                ],
            ] );

            if ( ! empty( $posts ) ) {
                return (int) $posts[0];
            }
        }

        // Sem visita encontrada — katha fica "órfã" mas válida
        return 0;
    }

    // ──────────────────────────────────────────────────────────────
    // Upsert do vana_katha
    // ──────────────────────────────────────────────────────────────

    private static function upsert_katha(
        string $katha_ref,
        int    $visit_id,
        array  $context,
        array  $lecture,
        array  $taxonomies,
        array  $verses,
        array  $glossary,
        array  $notes
    ): array {

        // Verificar se já existe
        $existing = get_posts( [
            'post_type'      => 'vana_katha',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => '_vana_katha_katha_ref',
                    'value' => $katha_ref,
                ],
            ],
        ] );

        $created  = false;
        $title    = sanitize_text_field( $lecture['title'] ?? '' );
        $excerpt  = sanitize_textarea_field( $lecture['excerpt'] ?? '' );
        $summary  = wp_kses_post( $lecture['summary'] ?? '' );

        if ( ! empty( $existing ) ) {
            $katha_id = (int) $existing[0];

            wp_update_post( [
                'ID'           => $katha_id,
                'post_title'   => $title,
                'post_excerpt' => $excerpt,
                'post_content' => $summary,
            ] );

        } else {
            $katha_id = wp_insert_post( [
                'post_type'    => 'vana_katha',
                'post_status'  => 'draft',
                'post_title'   => $title,
                'post_excerpt' => $excerpt,
                'post_content' => $summary,
                'post_parent'  => $visit_id ?: 0,
                'post_name'    => sanitize_title( $katha_ref ),
            ], true );

            if ( is_wp_error( $katha_id ) ) {
                throw new Exception( 'Falha ao criar vana_katha: ' . $katha_id->get_error_message() );
            }

            $created = true;
        }

        // Metas de contexto
        self::update_katha_meta( $katha_id, $visit_id, $context, $lecture );

        // JSON blobs
        update_post_meta( $katha_id, '_vana_katha_lecture_taxonomies_json', wp_json_encode( $taxonomies, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $katha_id, '_vana_katha_verses_json',  wp_json_encode( $verses,   JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $katha_id, '_vana_katha_glossary_json', wp_json_encode( $glossary, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $katha_id, '_vana_katha_notes_json',   wp_json_encode( $notes,    JSON_UNESCAPED_UNICODE ) );

        return [ $katha_id, $created ];
    }

    // ──────────────────────────────────────────────────────────────
    // Persistir metas do vana_katha
    // ──────────────────────────────────────────────────────────────

    private static function update_katha_meta(
        int   $katha_id,
        int   $visit_id,
        array $context,
        array $lecture
    ): void {

        $s = 'sanitize_text_field';

        $meta_map = [
            '_vana_katha_schema_version'   => '3.2',
            '_vana_katha_katha_ref'        => $s( $context['katha_ref']       ?? '' ),
            '_vana_katha_visit_ref'        => $s( $context['visit_ref']       ?? '' ),
            '_vana_katha_day_key'          => $s( $context['day_key']         ?? '' ),
            '_vana_katha_event_key'        => $s( $context['event_key']       ?? '' ),
            '_vana_katha_source_url'       => esc_url_raw( $context['source_url'] ?? '' ),
            '_vana_katha_source_platform'  => $s( $context['source_platform'] ?? '' ),
            '_vana_katha_clip_start'       => $s( $context['clip_start']      ?? '' ),
            '_vana_katha_clip_end'         => $s( $context['clip_end']        ?? '' ),
            '_vana_katha_vod_ref'          => $s( $context['vod_id']          ?? '' ),
            '_vana_katha_vod_platform'     => $s( $context['vod_platform']    ?? '' ),
            '_vana_katha_vod_url'          => esc_url_raw( $context['vod_url'] ?? '' ),

            '_vana_katha_source_language'  => $s( $lecture['source_language'] ?? '' ),
            '_vana_katha_event_date'       => $s( $lecture['event_date']       ?? '' ),
            '_vana_katha_location'         => sanitize_textarea_field( $lecture['location'] ?? '' ),
            '_vana_katha_duration'         => $s( $lecture['duration']         ?? '' ),
            '_vana_katha_media_potential'  => $s( $lecture['media_potential']  ?? '' ),

            '_vana_katha_output_languages_json' => wp_json_encode(
                $lecture['output_languages'] ?? [], JSON_UNESCAPED_UNICODE
            ),

            '_vana_katha_title_en'   => $s( $lecture['title_en']   ?? '' ),
            '_vana_katha_excerpt_en' => sanitize_textarea_field( $lecture['excerpt_en'] ?? '' ),
            '_vana_katha_summary_en' => wp_kses_post( $lecture['summary_en'] ?? '' ),
        ];

        foreach ( $meta_map as $key => $value ) {
            update_post_meta( $katha_id, $key, $value );
        }

        // visit_id como inteiro
        if ( $visit_id > 0 ) {
            update_post_meta( $katha_id, '_vana_katha_visit_id', $visit_id );
        }

        // Período a partir da event_key ou lecture se disponível
        $period = self::resolve_period( $context );
        if ( $period ) {
            update_post_meta( $katha_id, '_vana_katha_period', $period );
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Resolver período a partir do contexto
    // ──────────────────────────────────────────────────────────────

    private static function resolve_period( array $context ): string {

        $event_key = strtolower( $context['event_key'] ?? '' );
        $allowed   = [ 'morning', 'midday', 'night', 'other' ];

        foreach ( $allowed as $period ) {
            if ( str_contains( $event_key, $period ) ) {
                return $period;
            }
        }

        // Fallback para "other"
        return $event_key !== '' ? 'other' : '';
    }

    // ──────────────────────────────────────────────────────────────
    // Upsert dos hk_passage
    // ──────────────────────────────────────────────────────────────

    private static function upsert_passages(
        int    $katha_id,
        string $katha_ref,
        int    $visit_id,
        array  $context,
        array  $passages
    ): array {

        $upserted = 0;
        $created  = 0;
        $updated  = 0;

        foreach ( $passages as $p ) {

            $passage_ref = sanitize_text_field( $p['passage_ref'] ?? '' );
            if ( $passage_ref === '' ) {
                continue;
            }

            // Verificar se já existe
            $existing = get_posts( [
                'post_type'      => 'hk_passage',
                'post_status'    => 'any',
                'post_parent'    => $katha_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'   => '_hk_passage_ref',
                        'value' => $passage_ref,
                    ],
                ],
            ] );

            $hook      = sanitize_text_field( $p['hook']      ?? '' );
            $content   = wp_kses_post( $p['content']          ?? '' );
            $key_quote = sanitize_textarea_field( $p['key_quote'] ?? '' );
            $slug_hint = sanitize_title( $p['slug_hint']      ?? $passage_ref );

            if ( ! empty( $existing ) ) {
                $passage_id = (int) $existing[0];

                wp_update_post( [
                    'ID'           => $passage_id,
                    'post_title'   => $hook,
                    'post_content' => $content,
                    'post_excerpt' => $key_quote,
                ] );

                $updated++;

            } else {
                $passage_id = wp_insert_post( [
                    'post_type'    => 'hk_passage',
                    'post_status'  => 'draft',
                    'post_title'   => $hook,
                    'post_content' => $content,
                    'post_excerpt' => $key_quote,
                    'post_parent'  => $katha_id,
                    'post_name'    => $slug_hint,
                    'menu_order'   => (int) ( $p['index'] ?? 0 ),
                ], true );

                if ( is_wp_error( $passage_id ) ) {
                    continue; // log e segue
                }

                $created++;
            }

            // Metas do passage
            self::update_passage_meta( $passage_id, $katha_id, $katha_ref, $visit_id, $context, $p );

            $upserted++;
        }

        return compact( 'upserted', 'created', 'updated' );
    }

    // ──────────────────────────────────────────────────────────────
    // Persistir metas do hk_passage
    // ──────────────────────────────────────────────────────────────

    private static function update_passage_meta(
        int    $passage_id,
        int    $katha_id,
        string $katha_ref,
        int    $visit_id,
        array  $context,
        array  $p
    ): void {

        $s = 'sanitize_text_field';

        $meta_map = [
            '_hk_katha_id'      => $katha_id,
            '_hk_visit_id'      => $visit_id,
            '_hk_visit_ref'     => $s( $context['visit_ref'] ?? '' ),
            '_hk_day_key'       => $s( $context['day_key']   ?? '' ),
            '_hk_katha_ref'     => $s( $katha_ref ),

            '_hk_passage_ref'   => $s( $p['passage_ref']   ?? '' ),
            '_hk_index'         => (int) ( $p['index']     ?? 0 ),
            '_hk_t_start'       => $s( $p['t_start']       ?? '' ),
            '_hk_t_end'         => $s( $p['t_end']         ?? '' ),

            '_hk_passage_kind'  => $s( $p['passage_kind']  ?? '' ),
            '_hk_passage_type'  => $s( $p['passage_type']  ?? '' ),
            '_hk_primary_type'  => $s( $p['type']          ?? '' ),
            '_hk_audience'      => is_array( $p['audience'] ?? null )
                                   ? implode( ',', array_map( 'sanitize_text_field', $p['audience'] ) )
                                   : $s( $p['audience'] ?? '' ),

            '_hk_hook_pt'       => $s( $p['hook']          ?? '' ),
            '_hk_key_quote_pt'  => sanitize_textarea_field( $p['key_quote'] ?? '' ),
            '_hk_content_pt'    => wp_kses_post( $p['content'] ?? '' ),
            
            '_hk_hook_en'       => $s( $p['hook_en']        ?? '' ),
            '_hk_key_quote_en'  => sanitize_textarea_field( $p['key_quote_en'] ?? '' ),
            '_hk_content_en'    => wp_kses_post( $p['content_en'] ?? '' ),

            '_hk_media_potential' => $s( $p['media_potential'] ?? '' ),
            '_hk_review_status'   => 'pending',
        ];

        foreach ( $meta_map as $key => $value ) {
            update_post_meta( $passage_id, $key, $value );
        }

        // Boolean
        update_post_meta( $passage_id, '_hk_reel_worthy',
            ! empty( $p['reel_worthy'] ) ? 1 : 0 );

        update_post_meta( $passage_id, '_hk_contains_confidential_content',
            ! empty( $p['contains_confidential_content'] ) ? 1 : 0 );

        // Arrays como JSON
        update_post_meta( $passage_id, '_hk_review_flags_json',
            wp_json_encode( $p['review_flags'] ?? [], JSON_UNESCAPED_UNICODE ) );

        update_post_meta( $passage_id, '_hk_notes_json',
            wp_json_encode( $p['notes'] ?? [], JSON_UNESCAPED_UNICODE ) );

        // Taxonomias: themes
        if ( ! empty( $p['themes'] ) && is_array( $p['themes'] ) ) {
            $terms = array_map( 'sanitize_text_field', $p['themes'] );
            wp_set_object_terms( $passage_id, $terms, 'vana_theme', false );
        }

        // Taxonomias: emotion
        $emotions = is_array( $p['emotion'] ?? null )
            ? $p['emotion']
            : [ $p['emotion'] ?? '' ];

        $emotions = array_filter( array_map( 'sanitize_text_field', $emotions ) );
        if ( ! empty( $emotions ) ) {
            wp_set_object_terms( $passage_id, $emotions, 'vana_emotion', false );
        }
    }
}
