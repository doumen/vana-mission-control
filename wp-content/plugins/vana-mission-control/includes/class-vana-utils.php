<?php
// includes/class-vana-utils.php
if (!defined('ABSPATH')) exit;

final class Vana_Utils {

    // =========================================================================
    // 1. LOGGING
    // =========================================================================

    /**
     * Registra logs de sistema no debug.log do WordPress de forma segura.
     */
    public static function log(string $message, string $level = 'info', array $context = []): void {
        $debug     = defined('WP_DEBUG') && WP_DEBUG;
        $debug_log = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

        if (!$debug && !$debug_log) {
            return;
        }

        $prefix = strtoupper($level);
        $ctx    = !empty($context)
            ? ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE)
            : '';

        error_log("[Vana/{$prefix}] {$message}{$ctx}");
    }

    // =========================================================================
    // 2. API
    // =========================================================================

    /**
     * Formata a resposta da API (JSON) para o Frontend.
     * Padrão oficial Vana: success, message, status_code, data (opcional).
     */
    public static function api_response(bool $success, string $message, int $status_code = 200, $data = null): WP_REST_Response {
        if ($status_code < 100 || $status_code > 599) {
            $status_code = 200;
        }

        return new \WP_REST_Response([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $status_code);
    }

    // =========================================================================
    // 3. SANITIZAÇÃO
    // =========================================================================

    /**
     * Limpa e valida o Origin Key (ex: visit:india_2026:vrindavan)
     */
    public static function sanitize_origin_key($key): string {
        return sanitize_text_field((string) $key);
    }

    public static function esc_text(string $s): string {
        return esc_html($s);
    }

    // =========================================================================
    // 4. INTERNACIONALIZAÇÃO
    // =========================================================================

    /**
     * Detecta o idioma da request via ?lang=en.
     * Retorna 'en' ou 'pt' (default).
     */
    public static function lang_from_request(): string {
        $lang = isset($_GET['lang'])
            ? strtolower(sanitize_key((string) $_GET['lang']))
            : 'pt';
        return ($lang === 'en') ? 'en' : 'pt';
    }

    /**
     * Extrai texto de um objeto multilingue vindo do Trator JSON.
     * Formato: ['pt' => '...', 'en' => '...']
     */
    public static function pick_i18n($data, string $lang = 'pt'): string {
        if (is_string($data)) return $data;
        if (!is_array($data)) return '';
        return (string) ($data[$lang] ?? $data['pt'] ?? reset($data) ?? '');
    }

    /**
     * Extrai i18n por sufixo de chave.
     * Ex: pick_i18n_key($obj, 'title', 'en') → lê $obj['title_en']
     * Fallback: _pt → _en → ''
     */
    public static function pick_i18n_key(array $obj, string $base, string $lang = 'pt'): string {
        $k = $base . '_' . $lang;
        if (isset($obj[$k]) && is_string($obj[$k]) && $obj[$k] !== '') return $obj[$k];

        $pt = $base . '_pt';
        if (isset($obj[$pt]) && is_string($obj[$pt]) && $obj[$pt] !== '') return $obj[$pt];

        $en = $base . '_en';
        if (isset($obj[$en]) && is_string($obj[$en]) && $obj[$en] !== '') return $obj[$en];

        return '';
    }

    /**
     * Normaliza o nome de exibição do devoto.
     * Fallback para 'devotee_fallback' se vazio.
     */
    public static function normalize_display_name(?string $name, string $lang = 'pt'): string {
        $name = trim((string) $name);
        if ($name === '') return self::t('devotee_fallback', $lang);
        return $name;
    }

    /**
     * Dicionário central de strings de UI.
     * Fonte: EDITORIAL.md — sincronize sempre os dois arquivos juntos.
     *
     * Fallback: EN ausente → usa PT. Chave ausente → retorna a própria $key.
     */
    public static function t(string $key, string $lang = 'pt'): string {
        static $strings = null;

        if ($strings === null) {
            // ✅ plugin_dir_path, não get_template_directory
            $path = VANA_MC_PATH . 'i18n/strings.php';

            if (!file_exists($path)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    trigger_error(
                        '[Vana_Utils::t] i18n não encontrado: ' . $path,
                        E_USER_WARNING
                    );
                }
                return $key;
            }

            $strings = require $path;

            if (!is_array($strings)) {
                $strings = [];
            }
        }

        if (!isset($strings[$key])) {
            return $key;
        }

        $entry = $strings[$key];

        return (string) (
            $entry[$lang] ??
            $entry['en']  ??
            $entry['pt']  ??
            $key
        );
    }

    // =========================================================================
    // 5. VALIDAÇÃO DE URLs
    // =========================================================================

    /**
     * Valida URL externa de vídeo.
     * Allowlist: YouTube, Google Drive, Facebook.
     * Bloqueia links de pasta do Drive.
     * Retorna string sanitizada ou WP_Error.
     */
    public static function validate_external_video_url(string $url, string $lang = 'pt') {
        $url = trim((string) $url);
        if ($url === '') return '';

        $parts  = wp_parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'https') {
            return new WP_Error('vana_url_https', ($lang === 'en')
                ? 'Link must start with https://'
                : 'O link precisa começar com https://'
            );
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        $allowed = [
            'youtube.com', 'www.youtube.com',
            'youtu.be', 'www.youtu.be',
            'drive.google.com', 'www.drive.google.com',
            'facebook.com', 'www.facebook.com',
            'fb.watch',
        ];

        if (!in_array($host, $allowed, true)) {
            return new WP_Error('vana_url_host', ($lang === 'en')
                ? 'Use a Google Drive (recommended) or YouTube (optional) link.'
                : 'Use um link do Google Drive (recomendado) ou YouTube (opcional).'
            );
        }

        if (str_contains($host, 'drive.google.com')) {
            if (str_contains($path, '/folders/') || str_contains($path, '/drive/folders/')) {
                return new WP_Error('vana_drive_folder', ($lang === 'en')
                    ? 'This looks like a Drive folder link. Please open the video file and copy the file link (/file/d/...).'
                    : 'Esse link parece ser de pasta do Drive. Abra o arquivo do vídeo e copie o link do arquivo (/file/d/...).'
                );
            }
        }

        return esc_url_raw($url);
    }

    /**
     * Garante que a URL é https e retorna sanitizada.
     * Retorna '' se inválida.
     */
    public static function safe_https_url(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') return '';
        return esc_url_raw($url);
    }

    /**
     * Verifica se a URL é de um serviço de vídeo suportado.
     */
    public static function is_video_url(string $url): bool {
        $u = self::safe_https_url($url);
        if ($u === '') return false;
        $parts = wp_parse_url($u);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        return (
            str_contains($host, 'youtube.com')      ||
            str_contains($host, 'youtu.be')         ||
            str_contains($host, 'drive.google.com')
        );
    }
    // Adicionar em includes/class-vana-utils.php
    // =========================================================================
    // 6. URL DE EMBED
    // =========================================================================

    /**
     * Converte URL YouTube em URL de embed.
     * Suporta: watch?v=, youtu.be/, shorts/, embed/
     * Retorna '' se não for YouTube válido.
     */
    public static function maybe_embed_url(string $url): string {
        $url = trim($url);
        if ($url === '') return '';

        $video_id = '';

        if (preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        } elseif (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        } elseif (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        } elseif (preg_match('#youtube\.com/shorts/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        }

        if ($video_id === '') return '';

        return 'https://www.youtube-nocookie.com/embed/'
            . $video_id
            . '?rel=0&modestbranding=1';
    }

}

// =============================================================================
// ALIAS GLOBAL — vana_t()
// Resolve chamadas em templates beta que usam vana_t() em vez de Vana_Utils::t()
// =============================================================================
if (!function_exists('vana_t')) {
    function vana_t(string $key, string $lang = 'pt'): string {
        return Vana_Utils::t($key, $lang);
    }
}
