<?php
/**
 * Handler: upsert de Visit v3.1
 */
defined('ABSPATH') || exit;

final class Vana_Ingest_Visit {

    public static function upsert(array $payload): WP_REST_Response {
        // Defesa: CPT registrado?
        if (!post_type_exists('vana_visit')) {
            return Vana_Utils::api_response(false, 'CPT vana_visit não está registado no sistema.', 500);
        }

        $origin_key = Vana_Utils::sanitize_origin_key((string)($payload['origin_key'] ?? ''));
        $parent_key = Vana_Utils::sanitize_origin_key((string)($payload['parent_origin_key'] ?? ''));
        $title      = sanitize_text_field((string)($payload['title'] ?? ('Visita: ' . $origin_key)));
        $slug       = sanitize_title((string)($payload['slug_suggestion'] ?? $title));

        // Defesa em profundidade (mesmo que o endpoint já valide)
        if ($origin_key === '' || strpos($origin_key, 'visit:') !== 0) {
            return Vana_Utils::api_response(false, 'Envelope inválido: origin_key inválido (esperado prefixo visit:)', 422);
        }
        if ($parent_key === '' || strpos($parent_key, 'tour:') !== 0) {
            return Vana_Utils::api_response(false, 'Envelope inválido: parent_origin_key inválido (esperado prefixo tour:)', 422);
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            return Vana_Utils::api_response(false, 'Schema inválido: data deve ser um objeto', 422);
        }

        // Schema guardrails
        $schema_version = sanitize_text_field((string)($data['schema_version'] ?? ''));
        if ($schema_version !== '3.1') {
            return Vana_Utils::api_response(false, 'Schema não suportado: schema_version deve ser 3.1', 422);
        }

        $days = $data['days'] ?? null;
        if (!is_array($days) || count($days) > 400) {
            return Vana_Utils::api_response(false, 'Schema inválido: days inválido ou excede o limite de 400', 422);
        }

        $updated_at  = sanitize_text_field((string)($data['updated_at'] ?? ''));
        $incoming_ts = $updated_at !== '' ? strtotime($updated_at) : false;
        if ($updated_at !== '' && !$incoming_ts) {
            Vana_Utils::log('VISIT_UPDATED_AT_INVALID', 'warning', [
                'origin_key' => $origin_key,
                'updated_at' => $updated_at,
            ]);
        }

        // Lock por origin_key
        $lock_key = 'vana_lock_visit_' . md5($origin_key);
        if (get_transient($lock_key)) {
            return Vana_Utils::api_response(false, 'Requisição concorrente em processamento. Tente novamente.', 409);
        }
        set_transient($lock_key, 1, 15);

        try {
            // Lookup por origin_key (idempotência)
            $q = new WP_Query([
                'post_type'      => 'vana_visit',
                'post_status'    => 'any',
                'meta_key'       => '_vana_origin_key',
                'meta_value'     => $origin_key,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            $existing_id = (int)($q->posts[0] ?? 0);
            $is_new = $existing_id <= 0;

            // JSON + hash (antes de gravar para permitir "noop" em reprocesso)
            $timeline_json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($timeline_json) || $timeline_json === '' || $timeline_json === 'null') {
                return Vana_Utils::api_response(false, 'Falha ao serializar timeline JSON', 500);
            }
            $hash = hash('sha256', $timeline_json);

            // Se já existe e nada mudou, evita writes desnecessários
            if (!$is_new) {
                $old_hash = (string) get_post_meta($existing_id, '_vana_timeline_hash', true);
                if ($old_hash !== '' && hash_equals($old_hash, $hash)) {
                    return Vana_Utils::api_response(
                        true,
                        'Sem mudanças (hash idêntico). Ação ignorada.',
                        200,
                        [
                            'visit_id'     => (int) $existing_id,
                            'origin_key'   => $origin_key,
                            'action'       => 'noop',
                            'permalink'    => get_permalink($existing_id),
                            'hash'         => $hash,
                            'tour_updated' => false,
                            'tour_id'      => null,
                        ]
                    );
                }
            }

            $post_args = [
                'post_type'   => 'vana_visit',
                'post_title'  => $title,
                'post_status' => 'publish',
            ];

            if ($is_new) {
                $post_args['post_name'] = $slug; // slug só no create
                $visit_id = wp_insert_post($post_args, true);
            } else {
                $post_args['ID'] = $existing_id;
                $visit_id = wp_update_post($post_args, true);
            }

            if (is_wp_error($visit_id) || empty($visit_id)) {
                return Vana_Utils::api_response(false, 'Falha ao gravar a visita no banco de dados', 500);
            }

            update_post_meta($visit_id, '_vana_origin_key', $origin_key);
            update_post_meta($visit_id, '_vana_parent_tour_origin_key', $parent_key);
            update_post_meta($visit_id, '_vana_timeline_schema_version', $schema_versio);
            update_post_meta($visit_id, '_vana_timeline_updated_at', $updated_at);
            update_post_meta($visit_id, '_vana_visit_timeline_json', $timeline_json);
            // ==========================================
            // Materialização Automática (Cria a Ficha Resumo)
            // ==========================================
            try {
                $derived = Vana_Visit_Materializer::derive_from_timeline_json($timeline_json);
                Vana_Visit_Materializer::apply_to_post((int) $visit_id, $derived);
            } catch (Throwable $e) {
                if (class_exists('Vana_Utils')) {
                    Vana_Utils::log('VISIT_MATERIALIZE_ERROR', 'error', [
                        'visit_id'   => (int) $visit_id,
                        'origin_key' => $origin_key,
                        'msg'        => $e->getMessage(),
                    ]);
                }
            }
            delete_transient('vana_chronological_sequence');
            update_post_meta($visit_id, '_vana_timeline_hash', $hash);

            // Atualiza Tour pai (monótono)
            $tour_id = self::find_tour_id_by_origin_key($parent_key);
            $tour_updated = false;

            if ($tour_id > 0) {
                $current_last_visit_id = (int) get_post_meta($tour_id, '_vana_last_visit_id', true);

                // Defesa: last_visit_id aponta para post válido e tipo correto?
                if ($current_last_visit_id > 0) {
                    $p = get_post($current_last_visit_id);
                    if (!$p || $p->post_type !== 'vana_visit') {
                        $current_last_visit_id = 0;
                    }
                }

                $should_update_last = true;

                if ($current_last_visit_id > 0 && $incoming_ts) {
                    $existing_updated_at = (string) get_post_meta($current_last_visit_id, '_vana_timeline_updated_at', true);
                    $existing_ts = $existing_updated_at !== '' ? strtotime($existing_updated_at) : false;

                    if ($existing_ts && $incoming_ts < $existing_ts) {
                        $should_update_last = false;
                    }
                }

                if ($should_update_last) {
                    update_post_meta($tour_id, '_vana_last_visit_id', (int)$visit_id);
                }

                // current somente se tour marcada como "current"
                $is_current = (bool) get_post_meta($tour_id, '_tour_is_current', true);
                if ($is_current) {
                    update_post_meta($tour_id, '_vana_current_visit_id', (int)$visit_id);
                }

                $tour_updated = true;
            }
            // LIMPEZA DA CACHE: Adicione esta linha aqui!
            // Isto força o WordPress a recalcular a ordem cronológica na próxima visita à Home.
            delete_transient('vana_chronological_sequence');
            return Vana_Utils::api_response(
                true,
                'Visita processada e ingerida com sucesso!',
                $is_new ? 201 : 200,
                [
                    'visit_id'     => (int) $visit_id,
                    'origin_key'   => $origin_key,
                    'action'       => $is_new ? 'created' : 'updated',
                    'permalink'    => get_permalink($visit_id),
                    'hash'         => $hash,
                    'tour_updated' => $tour_updated,
                    'tour_id'      => $tour_id > 0 ? (int) $tour_id : null,
                ]
            );

        } finally {
            delete_transient($lock_key);
        }
    }

    private static function find_tour_id_by_origin_key(string $origin_key): int {
        $q = new WP_Query([
            'post_type'      => 'vana_tour',
            'post_status'    => 'any',
            'meta_key'       => '_vana_origin_key',
            'meta_value'     => $origin_key,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        return (int)($q->posts[0] ?? 0);
    }
}