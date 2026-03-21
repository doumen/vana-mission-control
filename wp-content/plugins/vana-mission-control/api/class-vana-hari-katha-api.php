<?php
defined('ABSPATH') || exit;

final class Vana_Hari_Katha_API {

    public static function register(): void {
        register_rest_route('vana/v1', '/kathas', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_kathas'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('vana/v1', '/passages', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_passages'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function get_kathas( WP_REST_Request $request ): WP_REST_Response {
    
        $visit_id = absint( $request->get_param( 'visit_id' ) );
        $day      = sanitize_text_field( (string) $request->get_param( 'day' ) );
    
        if ( ! $visit_id || $day === '' ) {
            return Vana_Utils::api_response( false, 'visit_id e day são obrigatórios.', 400 );
        }
    
        $posts = Vana_Hari_Katha::get_kathas_for_day( $visit_id, $day );
        $items = [];
    
        foreach ( $posts as $post ) {
    
            // ── Contagem real de passages ─────────────────────────────
            $passage_q = new WP_Query( [
                'post_type'      => 'hk_passage',
                'post_status'    => 'publish',
                'post_parent'    => $post->ID,
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ] );
            $passage_count = (int) $passage_q->found_posts;
            // ─────────────────────────────────────────────────────────
    
            $items[] = [
                'id'             => (int) $post->ID,
                'title_pt'       => get_post_meta( $post->ID, '_vana_katha_title_pt',  true )
                                    ?: get_the_title( $post->ID ),
                'title_en'       => get_post_meta( $post->ID, '_vana_katha_title_en',  true ) ?: null,
                'excerpt_pt'     => get_post_meta( $post->ID, '_vana_katha_excerpt_pt', true )
                                    ?: get_post_field( 'post_excerpt', $post->ID ),
                'excerpt_en'     => get_post_meta( $post->ID, '_vana_katha_excerpt_en', true ) ?: null,
                'period'         => get_post_meta( $post->ID, '_vana_katha_period',    true ),
                't_start'        => get_post_meta( $post->ID, '_vana_katha_t_start',   true ),
                't_end'          => get_post_meta( $post->ID, '_vana_katha_t_end',     true ),
                'review_status'  => get_post_meta( $post->ID, '_vana_katha_review_status',  true ),
                'public_notice'  => (bool) get_post_meta( $post->ID, '_vana_katha_public_notice', true ),
                'passage_count'  => $passage_count,  // ← real agora
                'permalink'      => get_permalink( $post->ID ),
            ];
        }
    
        return Vana_Utils::api_response( true, 'OK', 200, [
            'items' => $items,
            'total' => count( $items ),
        ] );
    }

    public static function get_passages( WP_REST_Request $request ): WP_REST_Response {
    
        $katha_id = absint( $request->get_param( 'katha_id' ) );
        $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = max( 1, min( 20, absint( $request->get_param( 'per_page' ) ?: 10 ) ) );
    
        if ( ! $katha_id ) {
            return Vana_Utils::api_response( false, 'katha_id é obrigatório.', 400 );
        }
    
        // ── Detectar se há passages com _hk_index definido ───────────
        $has_index = get_posts( [
            'post_type'      => 'hk_passage',
            'post_status'    => 'publish',
            'post_parent'    => $katha_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => '_hk_index',
                    'compare' => 'EXISTS',
                ],
            ],
        ] );
        // ─────────────────────────────────────────────────────────────
    
        $query_args = [
            'post_type'      => 'hk_passage',
            'post_status'    => 'publish',
            'post_parent'    => $katha_id,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'no_found_rows'  => false,
        ];
    
        // ── Ordenação com fallback ────────────────────────────────────
        if ( ! empty( $has_index ) ) {
            $query_args['orderby']  = 'meta_value_num';
            $query_args['meta_key'] = '_hk_index';
            $query_args['order']    = 'ASC';
        } else {
            $query_args['orderby'] = 'date';
            $query_args['order']   = 'ASC';
        }
        // ─────────────────────────────────────────────────────────────
    
        $query = new WP_Query( $query_args );
        $posts = $query->posts;
        $total = (int) $query->found_posts;
    
        $items = array_map( static function ( WP_Post $post ): array {
            return [
                'id'                           => (int) $post->ID,
                'index'                        => (int) get_post_meta( $post->ID, '_hk_index',        true ),
                'passage_ref'                  => (string) get_post_meta( $post->ID, '_hk_passage_ref', true ),
                'passage_kind'                 => (string) get_post_meta( $post->ID, '_hk_passage_kind', true ),
                't_start'                      => (string) get_post_meta( $post->ID, '_hk_t_start',    true ),
                't_end'                        => (string) get_post_meta( $post->ID, '_hk_t_end',      true ),
                'hook_pt'                      => (string) get_post_meta( $post->ID, '_hk_hook_pt',    true ),
                'hook_en'                      => get_post_meta( $post->ID, '_hk_hook_en', true ) ?: null,
                'key_quote_pt'                 => (string) get_post_meta( $post->ID, '_hk_key_quote_pt', true ),
                'key_quote_en'                 => get_post_meta( $post->ID, '_hk_key_quote_en', true ) ?: null,
                'content_pt'                   => wp_kses_post( (string) get_post_meta( $post->ID, '_hk_content_pt', true ) ),
                'content_en'                   => wp_kses_post( get_post_meta( $post->ID, '_hk_content_en', true ) ?: '' ) ?: null,
                'reel_worthy'                  => (bool) get_post_meta( $post->ID, '_hk_reel_worthy',  true ),
                'media_potential'              => (string) get_post_meta( $post->ID, '_hk_media_potential', true ),
                'contains_confidential_content'=> (bool) get_post_meta( $post->ID, '_hk_contains_confidential_content', true ),
                'review_status'                => (string) get_post_meta( $post->ID, '_hk_review_status', true ),
                'review_flags'                 => json_decode( get_post_meta( $post->ID, '_hk_review_flags_json', true ) ?: '[]', true ),
                'permalink'                    => get_permalink( $post->ID ),
            ];
        }, $posts );
    
        return Vana_Utils::api_response( true, 'OK', 200, [
            'items'       => $items,
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => (int) ceil( $total / $per_page ),
            'has_more'    => ( $page * $per_page ) < $total,
        ] );
    }
}
