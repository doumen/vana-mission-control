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
                return Vana_Utils::api_response(false, 'Payload excede 3MB', 413, null);
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json decode error';
                return Vana_Utils::api_response(false, 'JSON malformado', 400, ['errors' => [$err]]);
            }

            // Envelope (barreira sanitária)
            $kind       = strtolower(trim((string)($payload['kind'] ?? '')));
            $origin_key = Vana_Utils::sanitize_origin_key((string)($payload['origin_key'] ?? ''));
            $parent_key = Vana_Utils::sanitize_origin_key((string)($payload['parent_origin_key'] ?? ''));
            $data       = $payload['data'] ?? null;

            if ($kind !== 'visit' || $origin_key === '' || $parent_key === '' || !is_array($data)) {
                return Vana_Utils::api_response(
                    false,
                    'Envelope inválido',
                    422,
                    ['errors' => ['Envelope exige kind=visit, origin_key, parent_origin_key e data(object)']]
                );
            }

            // Guardrail: prefixos (evita colisões futuras)
            if (strpos($origin_key, 'visit:') !== 0) {
                return Vana_Utils::api_response(
                    false,
                    'Envelope inválido',
                    422,
                    ['errors' => ['origin_key deve começar com "visit:"']]
                );
            }
            if (strpos($parent_key, 'tour:') !== 0) {
                return Vana_Utils::api_response(
                    false,
                    'Envelope inválido',
                    422,
                    ['errors' => ['parent_origin_key deve começar com "tour:"']]
                );
            }

            $handler = plugin_dir_path(__FILE__) . 'handlers/class-vana-ingest-visit.php';
            if (!file_exists($handler)) {
                return Vana_Utils::api_response(false, 'Handler ingest-visit ausente no servidor', 500, null);
            }

            require_once $handler;
            return Vana_Ingest_Visit::upsert($payload);

        } catch (Throwable $e) {
            Vana_Utils::log([
                'code'    => 'INTERNAL_ERROR ingest-visit',
                'level'   => 'error',
                'context' => [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);
            return Vana_Utils::api_response(false, 'Erro interno no processamento', 500, null);
        }
    }
}

