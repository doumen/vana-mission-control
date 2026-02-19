<?php
/**
 * Unified REST: POST /vana/v1/ingest
 * - Roteia por kind: tour|visit
 * - Auth via HMAC (lendo da Query/URL)
 */
defined('ABSPATH') || exit;

final class Vana_Ingest_API {

    public static function register(): void {
        register_rest_route('vana/v1', '/ingest', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }

    /**
     * Validação de Segurança (HMAC Direto e Blindado)
     */
    public static function check_permission(WP_REST_Request $request) {
        $secret = defined('VANA_INGEST_SECRET') ? VANA_INGEST_SECRET : '';
        
        if (empty($secret)) {
            return new WP_Error('rest_forbidden', 'VANA_INGEST_SECRET não está definido no wp-config.php.', ['status' => 401]);
        }

        // Lê do URL (Query Params) onde o Trator (Python) coloca a assinatura
        $client_signature = (string) $request->get_param('vana_signature');
        $client_timestamp = (string) $request->get_param('vana_timestamp');
        $client_nonce     = (string) $request->get_param('vana_nonce');

        if (empty($client_signature) || empty($client_timestamp)) {
            return new WP_Error('rest_forbidden', 'Faltam os parâmetros de assinatura no URL.', ['status' => 401]);
        }

        // Proteção contra ataques antigos (tolerância de 5 minutos)
        if (abs(time() - (int)$client_timestamp) > 300) {
            return new WP_Error('rest_forbidden', 'Timestamp expirado. O relógio está dessincronizado.', ['status' => 401]);
        }

        // Reconstrói a mensagem e calcula a assinatura
        $body = (string) $request->get_body();
        $message = $client_timestamp . "\n" . $client_nonce . "\n" . $body;
        $expected = hash_hmac('sha256', $message, $secret);

        // Compara a assinatura do Python com a do WordPress
        if (!hash_equals($expected, $client_signature)) {
            return new WP_Error('rest_forbidden', 'Assinatura matemática não coincide.', ['status' => 401]);
        }

        // SUCESSO! A porta abre-se.
        return true;
    }

    /**
     * O Cérebro da Operação (Roteador de Ingestão)
     */
    public static function handle(WP_REST_Request $request): WP_REST_Response {
        try {
            $raw = (string) $request->get_body();

            // Guardrail: 3MB
            if (strlen($raw) > 3 * 1024 * 1024) {
                return Vana_Utils::api_response(false, 'Payload excede o limite de 3MB', 413);
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                $err = function_exists('json_last_error_msg') ? json_last_error_msg() : 'Erro de decode';
                return Vana_Utils::api_response(false, 'JSON malformado: ' . $err, 400);
            }

            // Validação de Envelope Mínimo
            $kind = strtolower(trim((string)($payload['kind'] ?? '')));
            if ($kind === '') {
                return Vana_Utils::api_response(false, 'Envelope inválido: o campo "kind" é obrigatório', 422);
            }

            if (!array_key_exists('origin_key', $payload) || !array_key_exists('data', $payload)) {
                return Vana_Utils::api_response(false, 'Envelope inválido: "origin_key" e "data" são obrigatórios', 422);
            }

            // ==========================================
            // ROTEAMENTO INTELIGENTE
            // ==========================================
            switch ($kind) {
                
                // Rota da Visita (Diário de Missão com GPS, Aulas, etc)
                case 'visit': {
                    $path = plugin_dir_path(__FILE__) . 'handlers/class-vana-ingest-visit.php';
                    if (!file_exists($path)) {
                        return Vana_Utils::api_response(false, 'Handler da Visita não encontrado', 500);
                    }
                    require_once $path;

                    if (!class_exists('Vana_Ingest_Visit')) {
                        return Vana_Utils::api_response(false, 'Classe Vana_Ingest_Visit não carregou', 500);
                    }

                    // Grava a Visita no WordPress
                    return Vana_Ingest_Visit::upsert($payload);
                }

                // Rota da Tour (Com Fallback Seguro para não gerar o Erro 500)
                case 'tour': {
                    return Vana_Utils::api_response(
                        true, 
                        'Tour aceita com sucesso (Modo Placeholder)', 
                        201, 
                        ['origin_key' => sanitize_text_field((string)$payload['origin_key'])]
                    );
                }

                default:
                    return Vana_Utils::api_response(false, "Kind '{$kind}' não suportado (use 'visit' ou 'tour')", 422);
            }

        } catch (Throwable $e) {
            // Se algo falhar, loga o erro sem quebrar a API
            if (class_exists('Vana_Utils')) {
                Vana_Utils::log('INTERNAL_ERROR ingest router', 'error', [
                    'msg'  => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
            return Vana_Utils::api_response(false, 'Erro interno no processamento: ' . $e->getMessage(), 500);
        }
    }
}