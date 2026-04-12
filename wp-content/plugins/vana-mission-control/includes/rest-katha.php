<?php
/**
 * REST Endpoint: /vana/v1/katha/{ref}
 *
 * Serve a estrutura completa de uma Hari-kathā para o VanaStageController.
 *
 * @package VanaMissionControl
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function () {

    // GET /vana/v1/katha/<ref>  (aceita ID numérico ou katha_ref slug)
    register_rest_route( 'vana/v1', '/katha/(?P<ref>[a-zA-Z0-9_\-]+)', [
        'methods'             => 'GET',
        'callback'            => 'vana_rest_get_katha',
        'permission_callback' => '__return_true', // público (leitura)
        'args' => [
            'ref' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'lang' => [
                'default'           => 'pt',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ( $v ) {
                    return in_array( $v, [ 'pt', 'en' ], true );
                },
            ],
            'page' => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default'           => 50,
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );
} );


/**
 * Handler principal.
 */
function vana_rest_get_katha( WP_REST_Request $request ) {

    $ref      = $request->get_param( 'ref' );
    $lang     = $request->get_param( 'lang' );
    $page     = max( 1, $request->get_param( 'page' ) );
    $per_page = min( 100, max( 1, $request->get_param( 'per_page' ) ) );
    $suffix   = ( $lang === 'en' ) ? '_en' : '_pt';

    // ── 1. Resolver a katha (por ID ou por katha_ref) ────────
    $katha_post = null;

    if ( is_numeric( $ref ) ) {
        $katha_post = get_post( intval( $ref ) );
        if ( $katha_post && $katha_post->post_type !== 'vana_katha' ) {
            $katha_post = null;
        }
    }

    if ( ! $katha_post ) {
        // Busca por _vana_katha_katha_ref
        $query = new WP_Query( [
            'post_type'      => 'vana_katha',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_key'       => '_vana_katha_katha_ref',
            'meta_value'     => $ref,
            'no_found_rows'  => true,
        ] );
        if ( $query->have_posts() ) {
            $katha_post = $query->posts[0];
        }
    }

    if ( ! $katha_post ) {
        return new WP_Error(
            'katha_not_found',
            "Katha '{$ref}' não encontrada.",
            [ 'status' => 404 ]
        );
    }

    $katha_id = $katha_post->ID;

    // ── 2. Meta da katha ─────────────────────────────────────
    $m = function ( $key ) use ( $katha_id ) {
        return get_post_meta( $katha_id, $key, true );
    };

    $json_decode = function ( $key ) use ( $m ) {
        $raw = $m( $key );
        if ( empty( $raw ) ) return [];
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    };

    $katha_data = [
        'katha_id'        => $katha_id,
        'katha_ref'       => $m( '_vana_katha_katha_ref' ),
        'title'           => $m( '_vana_katha_title' . $suffix )
                             ?: $katha_post->post_title,
        'scripture'       => $m( '_vana_katha_scripture' ),
        'source_language' => $m( '_vana_katha_source_language' ),
        'location'        => $m( '_vana_katha_location' ),
        'day_key'         => $m( '_vana_katha_day_key' ),
        'event_key'       => $m( '_vana_katha_event_key' ),
        'visit_ref'       => $m( '_vana_katha_visit_ref' ),
        'schema_version'  => $m( '_vana_katha_schema_version' ),
        'summary'         => $m( '_vana_katha_summary' . $suffix )
                             ?: $katha_post->post_content,
        'excerpt'         => $m( '_vana_katha_excerpt' . $suffix )
                             ?: $katha_post->post_excerpt,
        'sources'         => $json_decode( '_vana_katha_sources_json' ),
    ];

    // ── 3. Passages (paginados) ──────────────────────────────
    $passages_query = new WP_Query( [
        'post_type'      => 'hk_passage',
        'post_status'    => 'publish',
        'post_parent'    => $katha_id,
        'orderby'        => 'meta_value_num',
        'meta_key'       => '_hk_index',
        'order'          => 'ASC',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    ] );

    $passages = [];

    foreach ( $passages_query->posts as $p ) {
        $pm = function ( $key ) use ( $p ) {
            return get_post_meta( $p->ID, $key, true );
        };

        $passages[] = [
            'passage_id'      => $p->ID,
            'passage_ref'     => $pm( '_hk_passage_ref' ),
            'index'           => intval( $pm( '_hk_index' ) ),
            'hook'            => $pm( '_hk_hook' . $suffix )
                                 ?: $p->post_title,
            'key_quote'       => $pm( '_hk_key_quote' . $suffix ),
            'content'         => $pm( '_hk_content' . $suffix )
                                 ?: $p->post_content,
            'timestamp_start' => $pm( '_hk_ts_start' )
                                 ? intval( $pm( '_hk_ts_start' ) )
                                 : null,
            'timestamp_end'   => $pm( '_hk_ts_end' )
                                 ? intval( $pm( '_hk_ts_end' ) )
                                 : null,
            'timecode_start'  => $pm( '_hk_t_start' ),
            'timecode_end'    => $pm( '_hk_t_end' ),
            'vod_key'         => $pm( '_hk_source_vod_key' ),
            'segment_id'      => $pm( '_hk_source_segment_id' ),
            'kind'            => $pm( '_hk_passage_kind' ),
            'type'            => $pm( '_hk_passage_type' ),
            'media_potential'  => $pm( '_hk_media_potential' ),
            'review_status'   => $pm( '_hk_review_status' ),
        ];
    }

    $total_passages = $passages_query->found_posts;

    // ── 4. Versos e Glossário (da katha) ─────────────────────
    $verses   = $json_decode( '_vana_katha_verses_json' );
    $glossary = $json_decode( '_vana_katha_glossary_json' );

    // Filtrar campos por idioma no glossário
    $glossary_filtered = array_map( function ( $term ) use ( $suffix ) {
        $lang_key = ( $suffix === '_en' ) ? '_en' : '';
        return [
            'term'             => $term['term'] ?? '',
            'devanagari'       => $term['term_devanagari'] ?? '',
            'transliteration'  => $term['term_transliteration'] ?? '',
            'definition_short' => $term[ 'definition_short' . $lang_key ] ?? $term['definition_short'] ?? '',
            'definition_full'  => $term[ 'definition_full' . $lang_key ] ?? $term['definition_full'] ?? '',
            'category'         => $term['category'] ?? '',
            'related_terms'    => $term['related_terms'] ?? [],
        ];
    }, $glossary );

    // ── 5. Response ──────────────────────────────────────────
    $response = [
        'katha'     => $katha_data,
        'passages'  => $passages,
        'verses'    => $verses,
        'glossary'  => $glossary_filtered,
        'notes'     => $json_decode( '_vana_katha_notes_json' ),
        'pagination' => [
            'page'      => $page,
            'per_page'  => $per_page,
            'total'     => $total_passages,
            'pages'     => ceil( $total_passages / $per_page ),
        ],
        'lang'      => $lang,
        '_links'    => [
            'self' => rest_url( "vana/v1/katha/{$ref}?lang={$lang}" ),
        ],
    ];

    return rest_ensure_response( $response );
}
