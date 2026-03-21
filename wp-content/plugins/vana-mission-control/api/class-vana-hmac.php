<?php
/**
 * Validação criptográfica HMAC para a API de Ingestão do Vana Mission Control.
 * * Esta classe garante que:
 * 1. Apenas quem tem o VANA_INGEST_SECRET pode enviar dados.
 * 2. O payload (corpo do JSON) não foi alterado no meio do caminho.
 * 3. O pedido não é um "Replay Attack" (validação de timestamp de 5 minutos).
 */

defined('ABSPATH') || exit;

final class Vana_HMAC {

    /**
     * Verifica a assinatura de um request REST.
     * Lê os parâmetros de autenticação enviados via URL (Query Params) pelo cliente Python.
     *
     * @param WP_REST_Request $request O objeto do pedido WordPress.
     * @return bool Retorna true se a assinatura for perfeitamente válida.
     */
    public static function verify_request(WP_REST_Request $request): bool {
        
        // 1. Vai buscar a chave secreta ao wp-config.php
        $secret = defined('VANA_INGEST_SECRET') ? VANA_INGEST_SECRET : '';
        if (empty($secret)) {
            // Falha de segurança máxima: O site não tem a senha configurada.
            return false;
        }

        // 2. Extrai as credenciais do URL (enviadas pelo client.py)
        $client_signature = $request->get_param('vana_signature');
        $client_timestamp = $request->get_param('vana_timestamp');
        $client_nonce     = $request->get_param('vana_nonce');

        // Se faltar algum dos 3 parâmetros, recusa imediatamente
        if (empty($client_signature) || empty($client_timestamp) || empty($client_nonce)) {
            return false;
        }

        // 3. Proteção contra Replay Attack (5 minutos de tolerância)
        $server_ts = time();
        if (abs($server_ts - (int) $client_timestamp) > 300) {
            return false;
        }

        // 4. Reconstruir a mensagem exata que o Python assinou
        // O Python usou: f"{timestamp}\n{nonce}\n" + payload_bytes
        $body = (string) $request->get_body();
        $message = $client_timestamp . "\n" . $client_nonce . "\n" . $body;

        // 5. Calcular a assinatura matemática do lado do servidor (WordPress)
        $expected_signature = hash_hmac('sha256', $message, $secret);

        // 6. Comparação segura (hash_equals previne "Timing Attacks")
        return hash_equals($expected_signature, $client_signature);
    }
}