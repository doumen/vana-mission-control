<?php
/**
 * Cliente Cloudflare R2 — Vana Submission
 * Arquivo: includes/class-vana-r2-client.php
 * Version: 1.0.0
 *
 * Responsabilidades:
 *  1. Autenticação via Access Key + Secret (protocolo S3-compatível)
 *  2. Upload de arquivo local para o bucket R2
 *  3. Deleção de objeto no bucket
 *  4. Geração de URL pública
 *
 * Configuração em wp-config.php:
 *   define('VANA_R2_ACCOUNT_ID', 'seu_account_id');
 *   define('VANA_R2_ACCESS_KEY', 'sua_access_key');
 *   define('VANA_R2_SECRET_KEY', 'sua_secret_key');
 *   define('VANA_R2_BUCKET',     'vana-submissions');
 *   define('VANA_R2_PUBLIC_URL', 'https://cdn.seudominio.com');
 *
 * Estrutura de objetos no bucket:
 *   submissions/{visit_id}/{uniqid}-{hash8}.webp
 *
 * Uso:
 *   $client = Vana_R2_Client::instance();
 *
 *   $result = $client->upload(
 *       '/tmp/vana_abc123.webp',   // path local
 *       42,                         // visit_id
 *       'gurudeva'                  // subtype (para path/log)
 *   );
 *
 *   if (is_wp_error($result)) { ... }
 *
 *   // $result = [
 *   //   'url'    => 'https://cdn.seudominio.com/submissions/42/xxx.webp',
 *   //   'key'    => 'submissions/42/xxx.webp',
 *   //   'size'   => 243871,
 *   //   'etag'   => '"abc123..."',
 *   // ]
 *
 *   $client->delete('submissions/42/xxx.webp');
 */
defined('ABSPATH') || exit;

final class Vana_R2_Client {

    // ── Configuração ──────────────────────────────────────────
    private string $account_id;
    private string $access_key;
    private string $secret_key;
    private string $bucket;
    private string $public_url;
    private string $endpoint;

    // ── Singleton ─────────────────────────────────────────────
    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->account_id = (string) (VANA_R2_ACCOUNT_ID ?? '');
        $this->access_key = (string) (VANA_R2_ACCESS_KEY ?? '');
        $this->secret_key = (string) (VANA_R2_SECRET_KEY ?? '');
        $this->bucket     = (string) (VANA_R2_BUCKET     ?? '');
        $this->public_url = rtrim((string) (VANA_R2_PUBLIC_URL ?? ''), '/');

        // Endpoint S3-compatível do R2
        $this->endpoint   = sprintf(
            'https://%s.r2.cloudflarestorage.com',
            $this->account_id
        );
    }

    // ════════════════════════════════════════════════════════════
    //  UPLOAD
    // ════════════════════════════════════════════════════════════

    /**
     * Faz upload de arquivo local para o R2.
     *
     * @param  string         $local_path  Path do arquivo temporário
     * @param  int            $visit_id    ID da visita (para organizar no bucket)
     * @param  string         $subtype     'devotee' | 'gurudeva'
     * @return array|WP_Error
     */
    public function upload(
        string $local_path,
        int    $visit_id,
        string $subtype = 'devotee'
    ): array|WP_Error {
    
        // Valida arquivo
        if (!is_readable($local_path)) {
            return new WP_Error('r2_file_not_readable', 'Arquivo local não encontrado.');
        }
    
        $file_size = filesize($local_path);
        if (!$file_size) {
            return new WP_Error('r2_file_empty', 'Arquivo local está vazio.');
        }
    
        // Gera object key (mantém mesma lógica original)
        $object_key = $this->generate_key($visit_id, $local_path, $subtype);
    
        $body = file_get_contents($local_path);
        if ($body === false) {
            return new WP_Error('r2_read_failed', 'Não foi possível ler o arquivo.');
        }
    
        // ── Envia via Worker (evita TLS direto ao R2) ──────────
        if (!defined('VANA_WORKER_URL') || !defined('VANA_WORKER_SECRET')) {
            return new WP_Error('r2_config_missing', 'VANA_WORKER_URL ou VANA_WORKER_SECRET não definidos.');
        }
    
        $response = wp_remote_post(VANA_WORKER_URL, [
            'timeout'   => 60,
            'sslverify' => true,
            'headers'   => [
                'X-Vana-Token'    => VANA_WORKER_SECRET,
                'X-R2-Key'        => $object_key,
                'Content-Type'    => 'image/webp',
                'X-Cache-Control' => 'public, max-age=31536000, immutable',
            ],
            'body' => $body,
        ]);
    
        if (is_wp_error($response)) {
            Vana_Utils::log('R2_REQUEST_ERROR PUT ' . $object_key . ': ' . $response->get_error_message());
            return new WP_Error('r2_request_failed', 'Falha na comunicação com o Worker: ' . $response->get_error_message());
        }
    
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            $body_resp = wp_remote_retrieve_body($response);
            Vana_Utils::log('R2_HTTP_ERROR PUT ' . $object_key . ' status=' . $status . ' body=' . substr($body_resp, 0, 300));
            return new WP_Error('r2_http_error', 'Worker retornou status inesperado: ' . $status);
        }
    
        $result = json_decode(wp_remote_retrieve_body($response), true);
    
        // Monta URL pública
        $public_base = defined('VANA_PUBLIC_URL') ? rtrim(VANA_PUBLIC_URL, '/') : $this->public_url;
        $public_url  = $public_base . '/' . $object_key;
    
        Vana_Utils::log('R2_UPLOAD_OK ' . $object_key . ' size=' . $file_size);
    
        return [
            'url'  => $public_url,
            'key'  => $object_key,
            'size' => $file_size,
            'etag' => $result['etag'] ?? '',
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  DELETE
    // ════════════════════════════════════════════════════════════

    /**
     * Remove objeto do bucket R2.
     *
     * @param  string         $object_key  Ex: 'submissions/42/xxx.webp'
     * @return true|WP_Error
     */
    public function delete(string $object_key): true|WP_Error {
    
        if (!defined('VANA_WORKER_URL') || !defined('VANA_WORKER_SECRET')) {
            return new WP_Error('r2_config_missing', 'VANA_WORKER_URL ou VANA_WORKER_SECRET não definidos.');
        }
    
        $response = wp_remote_request(VANA_WORKER_URL, [
            'method'    => 'DELETE',
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => [
                'X-Vana-Token' => VANA_WORKER_SECRET,
                'X-R2-Key'     => $object_key,
            ],
        ]);
    
        if (is_wp_error($response)) {
            Vana_Utils::log('R2_DELETE_ERROR ' . $object_key . ': ' . $response->get_error_message());
            return new WP_Error('r2_delete_failed', 'Falha ao deletar do storage: ' . $response->get_error_message());
        }
    
        $status = wp_remote_retrieve_response_code($response);
        if (!in_array((int) $status, [200, 204], true)) {
            Vana_Utils::log('R2_DELETE_HTTP_ERROR ' . $object_key . ' status=' . $status);
            return new WP_Error('r2_http_error', 'Worker DELETE retornou status: ' . $status);
        }
    
        Vana_Utils::log('R2_DELETE_OK ' . $object_key);
        return true;
    }

    // ════════════════════════════════════════════════════════════
    //  ASSINATURA AWS Signature V4
    // ════════════════════════════════════════════════════════════

    /**
     * Executa request HTTP autenticado via AWS Signature V4.
     *
     * @return array|WP_Error  ['status' => 200, 'etag' => '...'] ou WP_Error
     */
    private function signed_request(
        string $method,
        string $object_key,
        string $body,
        string $content_type   = '',
        array  $extra_headers  = []
    ): array|WP_Error {

        $region    = 'auto';   // R2 usa 'auto'
        $service   = 's3';
        $host      = parse_url($this->endpoint, PHP_URL_HOST);
        $uri       = '/' . $this->bucket . '/' . ltrim($object_key, '/');

        // Timestamps
        $now        = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $amz_date   = $now->format('Ymd\THis\Z'); // 20240101T120000Z
        $date_stamp = $now->format('Ymd');         // 20240101

        // Hash do body
        $payload_hash = hash('sha256', $body);

        // ── 1. Canonical Request ──────────────────────────────
        $canonical_headers_map = array_merge([
            'content-type' => $content_type,
            'host'         => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'   => $amz_date,
        ], array_change_key_case($extra_headers, CASE_LOWER));

        // Ordena headers alfabeticamente (requisito AWS)
        ksort($canonical_headers_map);

        $canonical_headers = '';
        $signed_headers    = '';
        foreach ($canonical_headers_map as $k => $v) {
            $canonical_headers .= strtolower($k) . ':' . trim($v) . "\n";
            $signed_headers    .= strtolower($k) . ';';
        }
        $signed_headers = rtrim($signed_headers, ';');

        $canonical_request = implode("\n", [
            $method,
            $uri,
            '',              // query string vazia
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ]);

        // ── 2. String to Sign ─────────────────────────────────
        $credential_scope = implode('/', [
            $date_stamp,
            $region,
            $service,
            'aws4_request',
        ]);

        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request),
        ]);

        // ── 3. Signing Key ────────────────────────────────────
        $signing_key = $this->derive_signing_key(
            $date_stamp,
            $region,
            $service
        );

        // ── 4. Signature ──────────────────────────────────────
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // ── 5. Authorization Header ───────────────────────────
        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->access_key,
            $credential_scope,
            $signed_headers,
            $signature
        );

        // ── 6. Monta headers HTTP finais ──────────────────────
        $http_headers = [];
        foreach ($canonical_headers_map as $k => $v) {
            $http_headers[] = $k . ': ' . $v;
        }
        $http_headers[] = 'Authorization: ' . $authorization;

        // ── 7. Executa via wp_remote_request ─────────────────
        $url = $this->endpoint . $uri;

        $response = wp_remote_request($url, [
            'method'  => $method,
            'headers' => $http_headers,
            'body'    => $body,
            'timeout' => 60,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            Vana_Utils::log(
                'R2_REQUEST_ERROR ' . $method . ' ' . $object_key
                . ': ' . $response->get_error_message()
            );
            return new WP_Error(
                'r2_request_failed',
                'Falha na comunicação com o storage: '
                . $response->get_error_message()
            );
        }

        $status = wp_remote_retrieve_response_code($response);

        // PUT → 200 OK, DELETE → 204 No Content
        $expected = $method === 'DELETE' ? [204, 200] : [200];

        if (!in_array((int) $status, $expected, true)) {
            $body_resp = wp_remote_retrieve_body($response);
            Vana_Utils::log(
                'R2_HTTP_ERROR ' . $method . ' ' . $object_key
                . ' status=' . $status
                . ' body=' . substr($body_resp, 0, 500)
            );
            return new WP_Error(
                'r2_http_error',
                'R2 retornou status inesperado: ' . $status
            );
        }

        return [
            'status' => (int) $status,
            'etag'   => trim(
                wp_remote_retrieve_header($response, 'etag'),
                '"'
            ),
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════════════════════

    /**
     * Deriva a signing key via HMAC encadeado (AWS SigV4).
     */
    private function derive_signing_key(
        string $date_stamp,
        string $region,
        string $service
    ): string {
        $k_date    = hash_hmac('sha256', $date_stamp, 'AWS4' . $this->secret_key, true);
        $k_region  = hash_hmac('sha256', $region,     $k_date,    true);
        $k_service = hash_hmac('sha256', $service,    $k_region,  true);
        return       hash_hmac('sha256', 'aws4_request', $k_service, true);
    }

    /**
     * Gera object key único e organizado.
     *
     * Formato: submissions/{visit_id}/{uniqid}-{hash8}.webp
     * Exemplo: submissions/42/686a3f1c-a3b9f21d.webp
     *
     * O hash8 é derivado do conteúdo do arquivo →
     * mesmo arquivo enviado duas vezes = mesmo hash →
     * deduplicação natural no bucket.
     */
    private function generate_key(
        int    $visit_id,
        string $local_path,
        string $subtype
    ): string {
        $hash8  = substr(hash_file('sha256', $local_path), 0, 8);
        $uid    = substr(uniqid('', true), -8);
        $prefix = $subtype === 'gurudeva' ? 'g' : 'd';

        return sprintf(
            'submissions/%d/%s-%s-%s.webp',
            $visit_id,
            $prefix,      // d = devotee, g = gurudeva
            $uid,
            $hash8
        );
    }

    /**
     * Valida que todas as constantes necessárias estão definidas.
     *
     * @return true|WP_Error
     */
    private function validate_config(): true|WP_Error {
        $missing = [];

        if ($this->account_id === '') $missing[] = 'VANA_R2_ACCOUNT_ID';
        if ($this->access_key === '') $missing[] = 'VANA_R2_ACCESS_KEY';
        if ($this->secret_key === '') $missing[] = 'VANA_R2_SECRET_KEY';
        if ($this->bucket     === '') $missing[] = 'VANA_R2_BUCKET';
        if ($this->public_url === '') $missing[] = 'VANA_R2_PUBLIC_URL';

        if (!empty($missing)) {
            return new WP_Error(
                'r2_config_missing',
                'Configuração R2 incompleta. Faltam: '
                . implode(', ', $missing)
            );
        }

        return true;
    }

    /**
     * Extrai object key de uma URL pública do R2.
     * Útil para deletar ao rejeitar uma foto.
     *
     * Ex: 'https://cdn.seudominio.com/submissions/42/xxx.webp'
     *   → 'submissions/42/xxx.webp'
     */
    public function key_from_url(string $url): string {
        $prefix = $this->public_url . '/';
        if (str_starts_with($url, $prefix)) {
            return substr($url, strlen($prefix));
        }
        return '';
    }
}
