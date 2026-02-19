<?php
// includes/class-vana-utils.php
if (!defined('ABSPATH')) exit;

final class Vana_Utils {
    
    /**
     * Regista logs de sistema no debug.log do WordPress de forma segura.
     */
    public static function log($message): void {
        $debug     = defined('WP_DEBUG') && WP_DEBUG;
        $debug_log = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        
        if (!$debug && !$debug_log) {
            return;
        }

        // Suporta strings planas ou arrays complexos sem quebrar
        error_log(is_scalar($message) ? (string) $message : print_r($message, true));
    }
    
    /**
     * Formata a resposta da API (JSON) para o Frontend.
     * Padrão oficial Vana: success, message, status_code, data (opcional).
     */
    public static function api_response(bool $success, string $message, int $status_code = 200, $data = null): WP_REST_Response {
        // Hardening: garante range HTTP válido
        if ($status_code < 100 || $status_code > 599) {
            $status_code = 200;
        }

        return new \WP_REST_Response([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $status_code);
    }
    
    /**
     * Limpa e valida o Origin Key (ex: visit:india_2026:vrindavan)
     */
    public static function sanitize_origin_key($key) {
        // Remove espaços e caracteres perigosos, mas mantém os dois pontos ":"
        return sanitize_text_field((string) $key);
    }
    
    /**
     * Extrai o texto correto de um objeto multilingue vindo do Trator JSON.
     */
    public static function pick_i18n($data, string $lang = 'pt'): string {
        if (is_string($data)) return $data;
        if (!is_array($data)) return '';
        return (string) ($data[$lang] ?? $data['pt'] ?? reset($data) ?? '');
    }
    
    /**
     * Pega i18n por sufixo: ex. title_pt/title_en, caption_pt/caption_en.
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

  public static function lang_from_request(): string {
    $lang = isset($_GET['lang']) ? strtolower((string)$_GET['lang']) : 'pt';
    return ($lang === 'en') ? 'en' : 'pt';
  }

  public static function t(string $key, string $lang = 'pt'): string {
    $dict = [
      'pt' => [
        'devotee_fallback' => 'Devoto(a)',
        'watch_link'       => 'Abrir link do vídeo',
        'close'            => 'Fechar',
        'embed_fail_title' => 'Não foi possível exibir o vídeo aqui.',
        'embed_fail_hint'  => 'Sem problema — você pode abrir no navegador.',
        'video_label'      => 'Vídeo',
        'photo_label'      => 'Foto',
        'offerings_title'  => 'Oferendas da Sangha',
        'share_prompt'     => 'Envie sua oferenda (foto, mensagem, vídeo).',
        'form_title'       => 'Enviar oferenda',
        'name_label'       => 'Nome',
        'message_label'    => 'Mensagem',
        'video_url_label'  => 'Link do vídeo (Drive recomendado)',
        'submit'           => 'Enviar',
        'consent'          => 'Eu autorizo a publicação desta oferenda nos canais oficiais da missão.',
        'privacy_note'     => 'Não publique dados pessoais sensíveis. Obrigado por servir a sangha.',
        'offerings_title' => 'Momentos da Sangha',
        'share_prompt'    => 'Partilhe os seus momentos e relatos desta visita.',
        'video_label'     => 'Vídeo',
        'photo_label'     => 'Foto',
        'close'           => 'Fechar',
        'watch_link'      => 'Abrir link',
      ],
      'en' => [
        'devotee_fallback' => 'Devotee',
        'watch_link'       => 'Open video link',
        'close'            => 'Close',
        'embed_fail_title' => 'We couldn’t display the video here.',
        'embed_fail_hint'  => 'No worries — you can open it in your browser.',
        'video_label'      => 'Video',
        'photo_label'      => 'Photo',
        'offerings_title'  => 'Sangha Offerings',
        'share_prompt'     => 'Send your offering (photo, message, video).',
        'form_title'       => 'Submit offering',
        'name_label'       => 'Name',
        'message_label'    => 'Message',
        'video_url_label'  => 'Video link (Drive recommended)',
        'submit'           => 'Submit',
        'consent'          => 'I authorize publishing this offering on the mission’s official channels.',
        'privacy_note'     => 'Please don’t include sensitive personal data. Thank you for serving the sangha.',
        'offerings_title' => 'Sangha Moments',
        'share_prompt'    => 'Share your moments and reflections of this visit.',
        'video_label'     => 'Video',
        'photo_label'     => 'Photo',
        'close'           => 'Close',
        'watch_link'      => 'Open link',
      ],
    ];

    return $dict[$lang][$key] ?? $dict['pt'][$key] ?? $key;
  }

  public static function esc_text(string $s): string {
    return esc_html($s);
  }

  public static function normalize_display_name(?string $name, string $lang = 'pt'): string {
    $name = trim((string)$name);
    if ($name === '') return self::t('devotee_fallback', $lang);
    return $name;
  }

  /**
   * Validate an external video URL:
   * - https required
   * - host allowlist: youtube.com, youtu.be, drive.google.com
   * - blocks Drive folders (/folders/ or /drive/folders/)
   * Returns sanitized URL string or WP_Error.
   */
  public static function validate_external_video_url(string $url, string $lang = 'pt') {
    $url = trim((string)$url);
    if ($url === '') return '';

    $parts = wp_parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($scheme !== 'https') {
      return new WP_Error('vana_url_https', ($lang==='en')
        ? 'Link must start with https://'
        : 'O link precisa começar com https://'
      );
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');

    $allowed = [
      'youtube.com','www.youtube.com',
      'youtu.be','www.youtu.be',
      'drive.google.com','www.drive.google.com',
    ];
    if (!in_array($host, $allowed, true)) {
      return new WP_Error('vana_url_host', ($lang==='en')
        ? 'Use a Google Drive (recommended) or YouTube (optional) link.'
        : 'Use um link do Google Drive (recomendado) ou YouTube (opcional).'
      );
    }

    if (str_contains($host, 'drive.google.com')) {
      if (str_contains($path, '/folders/') || str_contains($path, '/drive/folders/')) {
        return new WP_Error('vana_drive_folder', ($lang==='en')
          ? 'This looks like a Drive folder link. Please open the video file and copy the file link (/file/d/...).'
          : 'Esse link parece ser de pasta do Drive. Abra o arquivo do vídeo e copie o link do arquivo (/file/d/...).'
        );
      }
    }

    return esc_url_raw($url);
  }

  public static function safe_https_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    $parts = wp_parse_url($url);
    if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') return '';
    return esc_url_raw($url);
  }

  public static function is_video_url(string $url): bool {
    $u = self::safe_https_url($url);
    if ($u === '') return false;
    $parts = wp_parse_url($u);
    $host = strtolower((string)($parts['host'] ?? ''));
    return (
      str_contains($host, 'youtube.com') ||
      str_contains($host, 'youtu.be') ||
      str_contains($host, 'drive.google.com')
    );
  }
}
