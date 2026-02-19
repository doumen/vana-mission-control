<?php
/**
 * REST: POST /vana/v1/ingest-visit
 * Auth: Vana_Ingest_API::check_permission (HMAC em query params)
 * Response: Vana_Utils::api_response (contrato legado)
 */
defined('ABSPATH') || exit;

final class Vana_Ingest_Visit_API {

    public static function register(): void {
        register_rest_route('vana/v1', '/ingest-visit', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle'],
            'permission_callback' => ['Vana_Ingest_API', 'check_permission'],
        ]);
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response {
        try {
            $raw = (string) $request->get_body();

            // Guardrail: 3MB
            if (strlen($raw) > 3 * 1024 * 1024) {
                return Vana_Utils::api_response(false, null, 'Payload excede 3MB', 413);
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json decode error';
                return Vana_Utils::api_response(false, ['errors' => [$err]], 'JSON malformado', 400);
            }

            // Envelope (barreira sanitária)
            $kind       = strtolower(trim((string)($payload['kind'] ?? '')));
            $origin_key = Vana_Utils::sanitize_origin_key((string)($payload['origin_key'] ?? ''));
            $parent_key = Vana_Utils::sanitize_origin_key((string)($payload['parent_origin_key'] ?? ''));
            $data       = $payload['data'] ?? null;

            if ($kind !== 'visit' || $origin_key === '' || $parent_key === '' || !is_array($data)) {
                return Vana_Utils::api_response(
                    false,
                    ['errors' => ['Envelope exige kind=visit, origin_key, parent_origin_key e data(object)']],
                    'Envelope inválido',
                    422
                );
            }

            // Guardrail: prefixos (evita colisões futuras)
            if (strpos($origin_key, 'visit:') !== 0) {
                return Vana_Utils::api_response(
                    false,
                    ['errors' => ['origin_key deve começar com "visit:"']],
                    'Envelope inválido',
                    422
                );
            }
            if (strpos($parent_key, 'tour:') !== 0) {
                return Vana_Utils::api_response(
                    false,
                    ['errors' => ['parent_origin_key deve começar com "tour:"']],
                    'Envelope inválido',
                    422
                );
            }

            $handler = plugin_dir_path(__FILE__) . 'handlers/class-vana-ingest-visit.php';
            if (!file_exists($handler)) {
                return Vana_Utils::api_response(false, null, 'Handler ingest-visit ausente no servidor', 500);
            }

            require_once $handler;
            return Vana_Ingest_Visit::upsert($payload);

        } catch (Throwable $e) {
            Vana_Utils::log('INTERNAL_ERROR ingest-visit', 'error', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return Vana_Utils::api_response(false, null, 'Erro interno no processamento', 500);
        }
    }
}
