<?php
/**
 * Vana Query API — CRUD Read + Delete
 * GET    /vana/v1/visits
 * GET    /vana/v1/visits/{id}
 * DELETE /vana/v1/visits/{id}
 * GET    /vana/v1/tours
 * GET    /vana/v1/tours/{id}
 * DELETE /vana/v1/tours/{id}
 *
 * Auth: mesmo HMAC do /ingest (vana_timestamp + vana_nonce + vana_signature)
 */
defined('ABSPATH') || exit;

final class Vana_Query_API {

    public static function register(): void {

        // ── VISITS ────────────────────────────────────────────────
        register_rest_route('vana/v1', '/visits', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'list_visits'],
            'permission_callback' => [__CLASS__, 'check_hmac'],
            'args' => [
                'tour'     => ['sanitize_callback' => 'sanitize_text_field', 'default' => ''],
                'status'   => ['sanitize_callback' => 'sanitize_text_field', 'default' => 'publish'],
                'per_page' => ['sanitize_callback' => 'absint',              'default' => 20],
                'page'     => ['sanitize_callback' => 'absint',              'default' => 1],
            ],
        ]);

        register_rest_route('vana/v1', '/visits/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get_visit'],
                'permission_callback' => [__CLASS__, 'check_hmac'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'delete_visit'],
                'permission_callback' => [__CLASS__, 'check_hmac'],
                'args' => [
                    'force' => ['sanitize_callback' => 'absint', 'default' => 0],
                ],
            ],
        ]);

        // ── TOURS ─────────────────────────────────────────────────
        register_rest_route('vana/v1', '/tours', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'list_tours'],
            'permission_callback' => [__CLASS__, 'check_hmac'],
            'args' => [
                'status'   => ['sanitize_callback' => 'sanitize_text_field', 'default' => 'publish'],
                'per_page' => ['sanitize_callback' => 'absint',              'default' => 20],
                'page'     => ['sanitize_callback' => 'absint',              'default' => 1],
            ],
        ]);

        register_rest_route('vana/v1', '/tours/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get_tour'],
                'permission_callback' => [__CLASS__, 'check_hmac'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'delete_tour'],
                'permission_callback' => [__CLASS__, 'check_hmac'],
                'args' => [
                    'force' => ['sanitize_callback' => 'absint', 'default' => 0],
                ],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // AUTH — mesmo algoritmo HMAC do /ingest
    // GET/DELETE: body é vazio → assina string vazia
    // ══════════════════════════════════════════════════════════════
    public static function check_hmac(WP_REST_Request $request) {
        $secret = defined('VANA_INGEST_SECRET') ? VANA_INGEST_SECRET : '';
        if (empty($secret)) {
            return new WP_Error('rest_forbidden', 'VANA_INGEST_SECRET não configurado.', ['status' => 401]);
        }

        $sig = (string) $request->get_param('vana_signature');
        $ts  = (string) $request->get_param('vana_timestamp');
        $non = (string) $request->get_param('vana_nonce');

        if (empty($sig) || empty($ts) || empty($non)) {
            return new WP_Error('rest_forbidden', 'Parâmetros HMAC ausentes.', ['status' => 401]);
        }

        if (abs(time() - (int)$ts) > 300) {
            return new WP_Error('rest_forbidden', 'Timestamp expirado.', ['status' => 401]);
        }

        // GET/DELETE: body vazio — mensagem = "ts\nnonce\n"
        $body     = (string) $request->get_body();
        $message  = $ts . "\n" . $non . "\n" . $body;
        $expected = hash_hmac('sha256', $message, $secret);

        if (!hash_equals($expected, $sig)) {
            return new WP_Error('rest_forbidden', 'Assinatura inválida.', ['status' => 401]);
        }

        return true;
    }

    // ══════════════════════════════════════════════════════════════
    // VISITS — LIST
    // ══════════════════════════════════════════════════════════════
    public static function list_visits(WP_REST_Request $req): WP_REST_Response {
        $status   = sanitize_text_field($req->get_param('status') ?: 'publish');
        $per_page = min(absint($req->get_param('per_page') ?: 20), 100);
        $page     = max(absint($req->get_param('page') ?: 1), 1);
        $tour_key = sanitize_text_field($req->get_param('tour') ?: '');

        $args = [
            'post_type'      => 'vana_visit',
            'post_status'    => $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
        ];

        if ($tour_key !== '') {
            $args['meta_query'] = [[
                'key'     => '_vana_parent_tour_origin_key',
                'value'   => $tour_key,
                'compare' => '=',
            ]];
        }

        $q     = new WP_Query($args);
        $items = [];
        foreach ($q->posts as $post) {
            $items[] = self::format_visit($post);
        }

        return Vana_Utils::api_response(true, 'OK', 200, [
            'items'       => $items,
            'total'       => (int) $q->found_posts,
            'total_pages' => (int) $q->max_num_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // VISITS — GET single
    // ══════════════════════════════════════════════════════════════
    public static function get_visit(WP_REST_Request $req): WP_REST_Response {
        $id   = (int) $req->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'vana_visit') {
            return Vana_Utils::api_response(false, 'Visita não encontrada.', 404);
        }

        $data           = self::format_visit($post);
        $json           = get_post_meta($id, '_vana_visit_timeline_json', true);
        $data['timeline'] = $json ? json_decode($json, true) : null;

        return Vana_Utils::api_response(true, 'OK', 200, $data);
    }

    // ══════════════════════════════════════════════════════════════
    // VISITS — DELETE
    // ══════════════════════════════════════════════════════════════
    public static function delete_visit(WP_REST_Request $req): WP_REST_Response {
        $id    = (int) $req->get_param('id');
        $post  = get_post($id);

        if (!$post || $post->post_type !== 'vana_visit') {
            return Vana_Utils::api_response(false, 'Visita não encontrada.', 404);
        }

        $force  = (bool) absint($req->get_param('force'));
        $result = wp_delete_post($id, $force);

        if (!$result) {
            return Vana_Utils::api_response(false, 'Falha ao excluir a visita.', 500);
        }

        delete_transient('vana_chronological_sequence');

        return Vana_Utils::api_response(true,
            $force ? 'Visita excluída permanentemente.' : 'Visita movida para lixeira.',
            200,
            ['visit_id' => $id, 'action' => $force ? 'deleted' : 'trashed']
        );
    }

    // ══════════════════════════════════════════════════════════════
    // TOURS — LIST
    // ══════════════════════════════════════════════════════════════
    public static function list_tours(WP_REST_Request $req): WP_REST_Response {
        $status   = sanitize_text_field($req->get_param('status') ?: 'publish');
        $per_page = min(absint($req->get_param('per_page') ?: 20), 100);
        $page     = max(absint($req->get_param('page') ?: 1), 1);

        $q = new WP_Query([
            'post_type'      => 'vana_tour',
            'post_status'    => $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => false,
        ]);

        $items = [];
        foreach ($q->posts as $post) {
            $items[] = self::format_tour($post);
        }

        return Vana_Utils::api_response(true, 'OK', 200, [
            'items'       => $items,
            'total'       => (int) $q->found_posts,
            'total_pages' => (int) $q->max_num_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // TOURS — GET single (inclui visitas vinculadas)
    // ══════════════════════════════════════════════════════════════
    public static function get_tour(WP_REST_Request $req): WP_REST_Response {
        $id   = (int) $req->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'vana_tour') {
            return Vana_Utils::api_response(false, 'Tour não encontrada.', 404);
        }

        $data       = self::format_tour($post);
        $origin_key = get_post_meta($id, '_vana_origin_key', true);

        $vq = new WP_Query([
            'post_type'      => 'vana_visit',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [[
                'key'     => '_vana_parent_tour_origin_key',
                'value'   => $origin_key,
                'compare' => '=',
            ]],
        ]);

        $data['visits'] = array_map(
            fn($vid) => self::format_visit(get_post($vid)),
            $vq->posts
        );

        return Vana_Utils::api_response(true, 'OK', 200, $data);
    }

    // ══════════════════════════════════════════════════════════════
    // TOURS — DELETE
    // ══════════════════════════════════════════════════════════════
    public static function delete_tour(WP_REST_Request $req): WP_REST_Response {
        $id   = (int) $req->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'vana_tour') {
            return Vana_Utils::api_response(false, 'Tour não encontrada.', 404);
        }

        $force  = (bool) absint($req->get_param('force'));
        $result = wp_delete_post($id, $force);

        if (!$result) {
            return Vana_Utils::api_response(false, 'Falha ao excluir a tour.', 500);
        }

        return Vana_Utils::api_response(true,
            $force ? 'Tour excluída permanentemente.' : 'Tour movida para lixeira.',
            200,
            ['tour_id' => $id, 'action' => $force ? 'deleted' : 'trashed']
        );
    }

    // ══════════════════════════════════════════════════════════════
    // FORMATTERS
    // ══════════════════════════════════════════════════════════════
    private static function format_visit(WP_Post $post): array {
        $id = (int) $post->ID;
        return [
            'id'          => $id,
            'origin_key'  => (string) get_post_meta($id, '_vana_origin_key',             true),
            'tour_key'    => (string) get_post_meta($id, '_vana_parent_tour_origin_key', true),
            'title'       => $post->post_title,
            'status'      => $post->post_status,
            'slug'        => $post->post_name,
            'permalink'   => (string) get_permalink($id),
            'start_date'  => (string) get_post_meta($id, '_vana_start_date',             true),
            'end_date'    => (string) get_post_meta($id, '_vana_end_date',               true),
            'timezone'    => (string) get_post_meta($id, '_vana_tz',                     true),
            'cover_url'   => (string) get_post_meta($id, '_vana_cover_url',              true),
            'schema_ver'  => (string) get_post_meta($id, '_vana_timeline_schema_version',true),
            'updated_at'  => (string) get_post_meta($id, '_vana_timeline_updated_at',    true),
            'hash'        => (string) get_post_meta($id, '_vana_timeline_hash',          true),
            'created_at'  => $post->post_date,
        ];
    }

    private static function format_tour(WP_Post $post): array {
        $id = (int) $post->ID;
        return [
            'id'               => $id,
            'origin_key'       => (string) get_post_meta($id, '_vana_origin_key',          true),
            'title'            => $post->post_title,
            'status'           => $post->post_status,
            'slug'             => $post->post_name,
            'permalink'        => (string) get_permalink($id),
            'is_current'       => (bool)   get_post_meta($id, '_tour_is_current',           true),
            'last_visit_id'    => (int)    get_post_meta($id, '_vana_last_visit_id',        true),
            'current_visit_id' => (int)    get_post_meta($id, '_vana_current_visit_id',     true),
            'cover_url'        => (string) get_post_meta($id, '_vana_cover_url',            true),
            'created_at'       => $post->post_date,
        ];
    }
}
