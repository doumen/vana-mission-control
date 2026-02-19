<?php
defined('ABSPATH') || exit;

final class Vana_REST_Backfill {
    public static function register(): void {
        add_action('rest_api_init', function () {
            register_rest_route('vana/v1', '/admin/backfill-visits', [
                'methods' => 'POST',
                'permission_callback' => function () { return current_user_can('manage_options'); },
                'callback' => [__CLASS__, 'handle'],
            ]);
        });
    }

    public static function handle(WP_REST_Request $req): WP_REST_Response {
        $nonce = $req->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) return new WP_REST_Response(['ok' => false, 'message' => 'Nonce inválido'], 403);

        $limit = max(1, min(300, (int)($req->get_param('limit') ?? 50)));
        $only_missing = (bool)($req->get_param('only_missing') ?? true);
        $require_hash = (bool)($req->get_param('require_hash') ?? true);

        $meta_query = [];
        if ($only_missing) $meta_query[] = ['key' => '_vana_start_date', 'compare' => 'NOT EXISTS'];
        if ($require_hash) {
            $meta_query[] = ['key' => '_vana_timeline_hash', 'compare' => 'EXISTS'];
            $meta_query[] = ['key' => '_vana_timeline_hash', 'value' => '', 'compare' => '!='];
        }

        $q_args = ['post_type' => 'vana_visit', 'post_status' => 'any', 'posts_per_page' => $limit, 'fields' => 'ids', 'no_found_rows' => true];
        if ($meta_query) $q_args['meta_query'] = $meta_query;

        $q = new WP_Query($q_args);
        $processed = 0; $updated = 0; $skipped = 0; $errors = 0;

        foreach ($q->posts as $visit_id) {
            $visit_id = (int)$visit_id; $processed++;
            $timeline_json = (string) get_post_meta($visit_id, '_vana_visit_timeline_json', true);
            if ($timeline_json === '') { $skipped++; continue; }

            try {
                $derived = Vana_Visit_Materializer::derive_from_timeline_json($timeline_json);
                if ($derived['start_date'] === '' && $derived['tz'] === '') { $skipped++; continue; }
                Vana_Visit_Materializer::apply_to_post($visit_id, $derived);
                $updated++;
            } catch (Throwable $e) { $errors++; }
        }

        delete_transient('vana_chronological_sequence');
        return new WP_REST_Response(['ok' => true, 'processed' => $processed, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors], 200);
    }
}
Vana_REST_Backfill::register();