<?php
/**
 * REST Route: GET /wp-json/vana/v1/katha/{katha_ref}
 *
 * Endpoint de detalhe canônico de uma vana_katha.
 * Expõe passages paginados com estrutura expansível
 * para a dimensão temática (Fase 2+).
 *
 * Coexiste com:
 *   GET /vana/v1/kathas    → lista por visit_id + day (Vana_Hari_Katha_API)
 *   GET /vana/v1/passages  → lista flat por katha_id  (Vana_Hari_Katha_API)
 *
 * @package    Vana_Mission_Control
 * @since      7.0.0
 * @schema     6.1 + 3.2 (backward compatible)
 */
defined( 'ABSPATH' ) || exit;

final class Vana_REST_Katha {

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    private const REST_NAMESPACE  = 'vana/v1';
    private const ROUTE           = '/katha/(?P<katha_ref>[a-zA-Z0-9_\-]+)';
    private const CACHE_GROUP     = 'vana_katha';
    private const CACHE_TTL       = 300; // 5 minutos
    private const DEFAULT_PER_PAGE = 10;
    private const MAX_PER_PAGE     = 50;

    // ─────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────

    public static function register(): void {
        add_action( 'rest_api_init',    [ __CLASS__, 'register_routes' ] );
        add_action( 'save_post_vana_katha',  [ __CLASS__, 'purge_cache' ], 10, 1 );
        add_action( 'save_post_hk_passage',  [ __CLASS__, 'purge_cache_by_passage' ], 10, 1 );
    }

    // ─────────────────────────────────────────────────────────────
    // Rotas
    // ─────────────────────────────────────────────────────────────

    public static function register_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            self::ROUTE,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'handle' ],
                'permission_callback' => '__return_true',
                'args'                => self::get_args(),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Argumentos da rota
    // ─────────────────────────────────────────────────────────────

    private static function get_args(): array {
        return [
            'katha_ref' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static function ( $value ): bool {
                    return (bool) preg_match( '/^[a-zA-Z0-9_\-]{1,120}$/', (string) $value );
                },
                'description' => 'katha_ref canônico, WP post ID ou slug do post.',
            ],
            'lang' => [
                'required'          => false,
                'default'           => 'pt',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => static function ( $value ): bool {
                    return in_array( $value, [ 'pt', 'en' ], true );
                },
                'description' => 'Idioma de resposta: pt | en.',
            ],
            'page' => [
                'required'          => false,
                'default'           => 1,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ( $value ): bool {
                    return absint( $value ) >= 1;
                },
                'description' => 'Página de passages (base 1).',
            ],
            'per_page' => [
                'required'          => false,
                'default'           => self::DEFAULT_PER_PAGE,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ( $value ): bool {
                    $v = absint( $value );
                    return $v >= 1 && $v <= self::MAX_PER_PAGE;
                },
                'description' => 'Itens por página (1–' . self::MAX_PER_PAGE . ').',
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Handler principal
    // ─────────────────────────────────────────────────────────────

    public static function handle( WP_REST_Request $request ): WP_REST_Response {

        $katha_ref = (string) $request->get_param( 'katha_ref' );
        $lang      = (string) $request->get_param( 'lang' );
        $page      = max( 1, (int) $request->get_param( 'page' ) );
        $per_page  = max( 1, min( self::MAX_PER_PAGE, (int) $request->get_param( 'per_page' ) ) );

        // ── Cache ────────────────────────────────────────────────
        $cache_key = self::build_cache_key( $katha_ref, $lang, $page, $per_page );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( $cached !== false ) {
            return Vana_Utils::api_response( true, 'OK', 200, $cached );
        }

        // ── Resolver post ────────────────────────────────────────
        $post = self::resolve_post( $katha_ref );

        if ( ! $post ) {
            return Vana_Utils::api_response(
                false,
                sprintf( "Katha '%s' não encontrada.", $katha_ref ),
                404
            );
        }

        // ── Montar payload ───────────────────────────────────────
        $katha    = self::build_katha( $post, $lang );
        $passages = self::build_passages( $post->ID, $lang, $page, $per_page );

        $data = [
            'katha'      => $katha,
            'passages'   => $passages['items'],
            'pagination' => $passages['pagination'],
        ];

        wp_cache_set( $cache_key, $data, self::CACHE_GROUP, self::CACHE_TTL );

        return Vana_Utils::api_response( true, 'OK', 200, $data );
    }

    // ─────────────────────────────────────────────────────────────
    // Resolver post por katha_ref | post_id | slug
    // ─────────────────────────────────────────────────────────────

    private static function resolve_post( string $katha_ref ): ?WP_Post {

        // P1: post ID numérico
        if ( ctype_digit( $katha_ref ) ) {
            $post = get_post( (int) $katha_ref );
            if ( $post && 'vana_katha' === $post->post_type ) {
                return $post;
            }
        }

        // P2: meta _vana_katha_katha_ref (canônico)
        $ids = get_posts( [
            'post_type'      => 'vana_katha',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ [
                'key'     => '_vana_katha_katha_ref',
                'value'   => $katha_ref,
                'compare' => '=',
            ] ],
        ] );

        if ( ! empty( $ids ) ) {
            return get_post( $ids[0] );
        }

        // P3: post_name (slug WP)
        $by_slug = get_page_by_path( $katha_ref, OBJECT, 'vana_katha' );
        if ( $by_slug ) {
            return $by_slug;
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Build: katha
    // ─────────────────────────────────────────────────────────────

    private static function build_katha( WP_Post $post, string $lang ): array {

        $id = $post->ID;

        // ── Metas canônicas ──────────────────────────────────────
        $katha_ref      = (string) get_post_meta( $id, '_vana_katha_katha_ref',      true );
        $visit_id       = (int)    get_post_meta( $id, '_vana_katha_visit_id',        true );
        $visit_ref      = (string) get_post_meta( $id, '_vana_katha_visit_ref',       true );
        $day_key        = (string) get_post_meta( $id, '_vana_katha_day_key',         true );
        $event_key      = (string) get_post_meta( $id, '_vana_katha_event_key',       true );
        $title_pt       = (string) get_post_meta( $id, '_vana_katha_title_pt',        true );
        $title_en       = (string) get_post_meta( $id, '_vana_katha_title_en',        true );
        $scripture      = (string) get_post_meta( $id, '_vana_katha_scripture',       true );
        $language       = (string) get_post_meta( $id, '_vana_katha_source_language', true );
        $period         = (string) get_post_meta( $id, '_vana_katha_period',          true );
        $t_start        = (string) get_post_meta( $id, '_vana_katha_t_start',         true );
        $t_end          = (string) get_post_meta( $id, '_vana_katha_t_end',           true );
        $review_status  = (string) get_post_meta( $id, '_vana_katha_review_status',   true );
        $schema_version = (string) get_post_meta( $id, '_vana_katha_schema_version',  true );
        $media_potential= (string) get_post_meta( $id, '_vana_katha_media_potential', true );

        // ── Fontes Schema 6.1 ────────────────────────────────────
        $sources_raw = get_post_meta( $id, '_vana_katha_sources_json', true );
        $sources     = json_decode( $sources_raw ?: '[]', true );
        $sources     = is_array( $sources ) ? $sources : [];

        // ── passage_count real ───────────────────────────────────
        $passage_q = new WP_Query( [
            'post_type'      => 'hk_passage',
            'post_status'    => 'publish',
            'post_parent'    => $id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ] );
        $passage_count = (int) $passage_q->found_posts;

        // ── Taxonomias temáticas (Fase 2 — já prontas no CPT) ────
        $topics  = self::get_term_slugs( $id, 'hk_topic' );
        $persons = self::get_term_slugs( $id, 'hk_person' );
        $places  = self::get_term_slugs( $id, 'hk_place' );

        // ── Título por idioma ────────────────────────────────────
        $title_resolved = 'en' === $lang
            ? ( $title_en ?: $title_pt ?: get_the_title( $post ) )
            : ( $title_pt ?: get_the_title( $post ) );

        return [
            'id'         => $id,
            'katha_ref'  => $katha_ref ?: (string) $id,
            'permalink'  => get_permalink( $id ),

            'context' => [
                'visit_id'  => $visit_id  ?: null,
                'visit_ref' => $visit_ref ?: null,
                'day_key'   => $day_key   ?: null,
                'event_key' => $event_key ?: null,
            ],

            'content' => [
                'title'     => $title_resolved,
                'title_pt'  => $title_pt  ?: null,
                'title_en'  => $title_en  ?: null,
                'scripture' => $scripture ?: null,
                'language'  => $language  ?: null,
                'period'    => $period    ?: null,
                't_start'   => $t_start   ?: null,
                't_end'     => $t_end     ?: null,
            ],

            'editorial' => [
                'review_status'   => $review_status   ?: 'draft',
                'schema_version'  => $schema_version  ?: null,
                'media_potential' => $media_potential ?: null,
                'passage_count'   => $passage_count,
            ],

            // ── Dimensão temática — expansível na Fase 2 ─────────
            'thematic' => [
                '_phase'   => 'temporal',
                '_note'    => 'Campos temáticos populados na Fase 2.',
                'topics'   => $topics,
                'persons'  => $persons,
                'places'   => $places,
                'sources'  => $sources,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Build: passages paginados
    // ─────────────────────────────────────────────────────────────

    private static function build_passages(
        int    $katha_id,
        string $lang,
        int    $page,
        int    $per_page
    ): array {

        // ── Detectar ordenação por _hk_index (igual a get_passages()) ──
        $has_index = get_posts( [
            'post_type'      => 'hk_passage',
            'post_status'    => 'publish',
            'post_parent'    => $katha_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ [
                'key'     => '_hk_index',
                'compare' => 'EXISTS',
            ] ],
        ] );

        $query_args = [
            'post_type'      => 'hk_passage',
            'post_status'    => 'publish',
            'post_parent'    => $katha_id,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'no_found_rows'  => false,
        ];

        if ( ! empty( $has_index ) ) {
            $query_args['orderby']  = 'meta_value_num';
            $query_args['meta_key'] = '_hk_index';
            $query_args['order']    = 'ASC';
        } else {
            $query_args['orderby'] = 'date';
            $query_args['order']   = 'ASC';
        }

        $query = new WP_Query( $query_args );
        $total = (int) $query->found_posts;

        $items = array_map(
            static fn( WP_Post $p ) => self::build_passage( $p, $lang ),
            $query->posts
        );

        return [
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
                'has_more'    => ( $page * $per_page ) < $total,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Build: passage individual
    // ─────────────────────────────────────────────────────────────

    private static function build_passage( WP_Post $post, string $lang ): array {

        $id = $post->ID;

        // ── Metas estruturais ────────────────────────────────────
        $passage_ref  = (string) get_post_meta( $id, '_hk_passage_ref',   true );
        $katha_id     = (int)    get_post_meta( $id, '_hk_katha_id',      true );
        $index        = (int)    get_post_meta( $id, '_hk_index',         true );
        $title_pt     = (string) get_post_meta( $id, '_hk_title_pt',      true );
        $title_en     = (string) get_post_meta( $id, '_hk_title_en',      true );
        $hook_pt      = (string) get_post_meta( $id, '_hk_hook_pt',       true );
        $hook_en      = (string) get_post_meta( $id, '_hk_hook_en',       true );
        $key_quote_pt = (string) get_post_meta( $id, '_hk_key_quote_pt',  true );
        $key_quote_en = (string) get_post_meta( $id, '_hk_key_quote_en',  true );
        $content_pt   = (string) get_post_meta( $id, '_hk_content_pt',    true );
        $content_en   = (string) get_post_meta( $id, '_hk_content_en',    true );

        // ── Metas temporais ──────────────────────────────────────
        $t_start    = (string) get_post_meta( $id, '_hk_t_start',          true );
        $t_end      = (string) get_post_meta( $id, '_hk_t_end',            true );
        $ts_start   = (int)    get_post_meta( $id, '_hk_ts_start',         true );
        $ts_end     = (int)    get_post_meta( $id, '_hk_ts_end',           true );
        $vod_key    = (string) get_post_meta( $id, '_hk_source_vod_key',   true );
        $segment_id = (string) get_post_meta( $id, '_hk_source_segment_id',true );

        // ── Metas editoriais ─────────────────────────────────────
        $review_status   = (string) get_post_meta( $id, '_hk_review_status',    true );
        $reel_worthy     = (bool)   get_post_meta( $id, '_hk_reel_worthy',      true );
        $media_potential = (string) get_post_meta( $id, '_hk_media_potential',  true );
        $passage_kind    = (string) get_post_meta( $id, '_hk_passage_kind',     true );

        // ── Taxonomias temáticas (já registradas no CPT) ─────────
        $topics   = self::get_term_slugs( $id, 'hk_topic' );
        $persons  = self::get_term_slugs( $id, 'hk_person' );
        $places   = self::get_term_slugs( $id, 'hk_place' );
        $events_t = self::get_term_slugs( $id, 'hk_event' );

        // ── Conteúdo por idioma ──────────────────────────────────
        $is_en         = 'en' === $lang;
        $title_lang    = $is_en ? ( $title_en    ?: $title_pt    ) : $title_pt;
        $hook_lang     = $is_en ? ( $hook_en     ?: $hook_pt     ) : $hook_pt;
        $quote_lang    = $is_en ? ( $key_quote_en?: $key_quote_pt) : $key_quote_pt;
        $content_lang  = $is_en
            ? wp_kses_post( $content_en ?: $content_pt )
            : wp_kses_post( $content_pt );

        return [
            'id'          => $id,
            'passage_ref' => $passage_ref ?: (string) $id,
            'katha_id'    => $katha_id    ?: $post->post_parent,
            'index'       => $index,
            'permalink'   => get_permalink( $id ),

            'content' => [
                'title'     => $title_lang    ?: null,
                'title_pt'  => $title_pt      ?: null,
                'title_en'  => $title_en      ?: null,
                'hook'      => $hook_lang     ?: null,
                'hook_pt'   => $hook_pt       ?: null,
                'hook_en'   => $hook_en       ?: null,
                'key_quote' => $quote_lang    ?: null,
                'content'   => $content_lang  ?: null,
            ],

            'temporal' => [
                't_start'   => $t_start   ?: null,
                't_end'     => $t_end     ?: null,
                'ts_start'  => $ts_start  ?: null,
                'ts_end'    => $ts_end    ?: null,
                'source_ref' => [
                    'vod_key'    => $vod_key    ?: null,
                    'segment_id' => $segment_id ?: null,
                    'ts_start'   => $ts_start   ?: null,
                    'ts_end'     => $ts_end     ?: null,
                ],
            ],

            // ── Dimensão temática — expansível na Fase 2 ─────────
            'thematic' => [
                '_phase'         => 'temporal',
                '_note'          => 'source_units[] disponíveis na Fase 2 (hk_source_unit CPT).',
                'topics'         => $topics,
                'persons'        => $persons,
                'places'         => $places,
                'events'         => $events_t,
                'source_units'   => [],
            ],

            'editorial' => [
                'review_status'   => $review_status   ?: 'draft',
                'reel_worthy'     => $reel_worthy,
                'media_potential' => $media_potential ?: null,
                'passage_kind'    => $passage_kind    ?: null,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: taxonomias → slugs
    // ─────────────────────────────────────────────────────────────

    private static function get_term_slugs( int $post_id, string $taxonomy ): array {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return [];
        }
        return array_values(
            array_map( static fn( WP_Term $t ) => $t->slug, $terms )
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Cache — build key
    // ─────────────────────────────────────────────────────────────

    private static function build_cache_key(
        string $katha_ref,
        string $lang,
        int    $page,
        int    $per_page
    ): string {
        return 'vana_katha_' . md5( $katha_ref . $lang . $page . $per_page );
    }

    // ─────────────────────────────────────────────────────────────
    // Cache — invalidação por katha
    // ─────────────────────────────────────────────────────────────

    public static function purge_cache( int $post_id ): void {
        $katha_ref = (string) get_post_meta( $post_id, '_vana_katha_katha_ref', true );
        if ( $katha_ref !== '' ) {
            self::purge_cache_keys( [ $katha_ref, (string) $post_id ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Cache — invalidação por passage (sobe para a katha pai)
    // ─────────────────────────────────────────────────────────────

    public static function purge_cache_by_passage( int $passage_id ): void {
        $post = get_post( $passage_id );
        if ( ! $post || ! $post->post_parent ) {
            return;
        }
        self::purge_cache( $post->post_parent );
    }

    // ─────────────────────────────────────────────────────────────
    // Cache — limpa todas as variações de uma katha
    // ─────────────────────────────────────────────────────────────

    private static function purge_cache_keys( array $refs ): void {
        $langs     = [ 'pt', 'en' ];
        $pages     = range( 1, 5 );
        $per_pages = [ 10, 20, 50 ];

        foreach ( $refs as $ref ) {
            foreach ( $langs as $lang ) {
                foreach ( $pages as $page ) {
                    foreach ( $per_pages as $per_page ) {
                        wp_cache_delete(
                            self::build_cache_key( $ref, $lang, $page, $per_page ),
                            self::CACHE_GROUP
                        );
                    }
                }
            }
        }
    }
}

Vana_REST_Katha::register();
