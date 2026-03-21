<?php
/**
 * Store - Persistência Idempotente
 */

class Vana_Store {

    public static function upsert(array $data, string $post_type): int|WP_Error {

        $origin_key = Vana_Utils::sanitize_origin_key($data['origin_key'] ?? '');
        if (!Vana_Utils::validate_origin_key($origin_key)) {
            return new WP_Error('invalid_origin_key', 'Origin key inválido');
        }

        $index = Vana_Index::find_by_origin_key($origin_key);
        $post_id = $index ? (int) $index->post_id : 0;

        $norm_data = self::normalize($data, $post_type);
        $new_hash = Vana_Utils::generate_hash($norm_data);

        if ($index && (string) $index->content_hash === $new_hash) {
            Vana_Utils::log('Skip (hash igual)', 'debug', ['origin_key' => $origin_key, 'post_id' => $post_id]);
            return $post_id;
        }

        $auto_publish = (bool) get_option('vana_auto_publish', false);

        // Patch B: nunca rebaixa publish
        $new_status = 'draft';
        if ($post_id) {
            $current_post = get_post($post_id);
            $new_status = ($current_post && !is_wp_error($current_post) && !empty($current_post->post_status))
                ? $current_post->post_status
                : 'draft';
        } elseif ($auto_publish) {
            $new_status = 'publish';
        }

        $post_args = [
            'post_title'  => sanitize_text_field($data['title'] ?? 'Sem Título'),
            'post_name'   => sanitize_title($data['slug'] ?? ''),
            'post_type'   => $post_type,
            'post_status' => $new_status,
            'meta_input'  => [
                '_vana_origin_key'       => $origin_key,
                '_vana_raw_json'         => wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                '_vana_norm_json'        => wp_json_encode($norm_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                '_vana_content_hash'     => $new_hash,
                '_vana_contract_version' => $data['contract_version'] ?? '3.0',
                '_vana_request_id'       => sanitize_text_field($data['meta']['request_id'] ?? ''),
                '_vana_last_updated'     => current_time('mysql'),
            ],
        ];

        if ($post_id) {
            $post_args['ID'] = $post_id;

            $count = (int) get_post_meta($post_id, '_vana_update_count', true);
            $post_args['meta_input']['_vana_update_count'] = $count + 1;
        } else {
            $post_args['meta_input']['_vana_update_count'] = 0;
        }

        $result_id = wp_insert_post($post_args, true);
        if (is_wp_error($result_id)) {
            return $result_id;
        }

        Vana_Index::upsert(
            $origin_key,
            (int) $result_id,
            $post_type,
            $new_hash,
            sanitize_text_field($data['meta']['source'] ?? 'youtube')
        );

        self::save_type_metas((int) $result_id, $norm_data, $post_type);

        Vana_Utils::log(
            $post_id ? 'Post atualizado' : 'Post criado',
            'info',
            ['post_id' => (int) $result_id, 'origin_key' => $origin_key, 'status' => $new_status]
        );

        return (int) $result_id;
    }

    private static function normalize(array $data, string $post_type): array {
        $normalized = $data;

        if ($post_type === 'vana_tour') {
            $normalized['is_current'] = (bool) ($normalized['is_current'] ?? false);
            $normalized['dates_label'] = (string) ($normalized['dates_label'] ?? '');
            $normalized['theme'] = (string) ($normalized['theme'] ?? 'latam');
        }

        return $normalized;
    }

    private static function save_type_metas(int $post_id, array $data, string $post_type): void {
        if ($post_type === 'vana_tour') {
            update_post_meta($post_id, '_tour_theme', sanitize_text_field($data['theme'] ?? 'latam'));
            update_post_meta($post_id, '_tour_dates_label', sanitize_text_field($data['dates_label'] ?? ''));
            update_post_meta($post_id, '_tour_is_current', (bool) ($data['is_current'] ?? false));
        }
    }
}
