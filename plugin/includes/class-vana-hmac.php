<?php
defined('ABSPATH') || exit;

final class Vana_HMAC {

    /**
     * Verifica HMAC-SHA256 com mensagem canônica:
     * "{timestamp}\n{nonce}\n" + raw_body_bytes
     *
     * Espera query params:
     * - vana_timestamp (unix epoch, segundos)
     * - vana_nonce (hex 32 chars)
     * - vana_signature (hex 64 chars, sha256)
     */
    public static function verify_request(WP_REST_Request $request): bool {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = method_exists($request, 'get_route') ? (string) $request->get_route() : 'unknown';

        if (!defined('VANA_INGEST_SECRET') || !is_string(VANA_INGEST_SECRET) || VANA_INGEST_SECRET === '') {
            Vana_Utils::log('HMAC_SECRET_MISSING', 'error', compact('ip', 'uri'));
            return false;
        }

        $timestamp = (string) $request->get_param('vana_timestamp');
        $nonce     = (string) $request->get_param('vana_nonce');
        $signature = (string) $request->get_param('vana_signature');

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            Vana_Utils::log('HMAC_QUERY_PARAMS_MISSING', 'warning', compact('ip', 'uri'));
            return false;
        }

        if (!ctype_digit($timestamp)) {
            Vana_Utils::log('HMAC_TIMESTAMP_INVALID', 'warning', compact('ip', 'uri', 'timestamp'));
            return false;
        }

        // Nonce: hex 32 chars
        if (!preg_match('/^[0-9a-f]{32}$/', $nonce)) {
            Vana_Utils::log('HMAC_NONCE_INVALID', 'warning', compact('ip', 'uri'));
            return false;
        }

        // Signature: sha256 hex 64 chars
        if (!preg_match('/^[0-9a-f]{64}$/', $signature)) {
            Vana_Utils::log('HMAC_SIGNATURE_INVALID', 'warning', compact('ip', 'uri'));
            return false;
        }

        $now      = time();
        $req_time = (int) $timestamp;
        $window   = 300; // 5 min

        if (abs($now - $req_time) > $window) {
            Vana_Utils::log('HMAC_TIMESTAMP_OUT_OF_WINDOW', 'warning', [
                'ip'  => $ip,
                'uri' => $uri,
                'req' => $req_time,
                'now' => $now,
            ]);
            return false;
        }

        // Anti-replay: queima nonce (depois de autenticar com sucesso)
        $nonce_key = 'vana_nonce_' . hash('sha256', $nonce);
        if (get_transient($nonce_key)) {
            Vana_Utils::log('HMAC_REPLAY_DETECTED', 'warning', compact('ip', 'uri'));
            return false;
        }

        $raw_body = (string) $request->get_body(); // bytes/raw string
        $message  = sprintf("%s\n%s\n", $timestamp, $nonce) . $raw_body;

        $expected = hash_hmac('sha256', $message, VANA_INGEST_SECRET);

        if (!hash_equals($expected, $signature)) {
            Vana_Utils::log('HMAC_SIGNATURE_MISMATCH', 'error', [
                'ip'  => $ip,
                'uri' => $uri,
                'len' => strlen($raw_body),
            ]);
            return false;
        }

        // Agora sim: queima nonce por tempo > window
        set_transient($nonce_key, 1, $window * 2);

        return true;
    }
}
