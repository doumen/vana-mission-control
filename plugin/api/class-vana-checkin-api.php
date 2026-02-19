<?php
defined('ABSPATH') || exit;

final class Vana_Checkin_API {

    public static function register(): void {
        register_rest_route('vana/v1', '/checkin', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle'],
            'permission_callback' => '__return_true', // Público, protegido no handler
        ]);
    }

    private static function client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return preg_replace('/[^0-9a-fA-F\:\.]/', '', (string)$ip);
    }

    private static function rate_limit_ok(int $visit_id): bool {
        $ip = self::client_ip();
        if ($ip === '') return true;

        $key = 'vana_rl_checkin_' . md5($ip . '|' . $visit_id);
        $count = (int) get_transient($key);

        if ($count >= 6) return false;

        set_transient($key, $count + 1, 30 * MINUTE_IN_SECONDS);
        return true;
    }

    private static function normalize_https_url(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $url = esc_url_raw($url);
        if (!$url) return '';
        $p = wp_parse_url($url);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https') return '';
        return $url;
    }

    private static function host_allowed(string $url): bool {
        $p = wp_parse_url($url);
        $host = strtolower((string)($p['host'] ?? ''));
        
        // Remove o 'www.' para facilitar a comparação
        $host = preg_replace('/^www\./', '', $host);

        // Lista de domínios permitidos
        $allowed_hosts = [
            'youtu.be',
            'youtube.com',
            'drive.google.com',
            'facebook.com',
            'fb.watch'
        ];

        // Se for um dos domínios exatos
        if (in_array($host, $allowed_hosts, true)) {
            return true;
        }

        // Caso o host termine com algum dos domínios (ex: br.youtube.com ou web.facebook.com)
        foreach ($allowed_hosts as $allowed) {
            if (str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    private static function looks_like_drive_folder(string $url): bool {
        $p = wp_parse_url($url);
        $path = (string)($p['path'] ?? '');
        return (bool) preg_match('~/(drive/)?folders/~', $path);
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response {
        try {
            if (!post_type_exists('vana_submission')) {
                return Vana_Utils::api_response(false, 'Sistema de oferendas offline.', 503);
            }

            // Honeypot
            $website = (string) $request->get_param('website');
            if (trim($website) !== '') return Vana_Utils::api_response(false, 'Falha anti-spam.', 400);

            $visit_id = absint($request->get_param('visit_id'));
            if (!$visit_id || get_post_type($visit_id) !== 'vana_visit') return Vana_Utils::api_response(false, 'Visita inválida.', 400);

            if (!self::rate_limit_ok($visit_id)) return Vana_Utils::api_response(false, 'Muitos envios em pouco tempo. Tente mais tarde.', 429);

            $consent = (int) $request->get_param('consent_publish');
            if ($consent !== 1) return Vana_Utils::api_response(false, 'É necessário autorizar a publicação.', 422);

            $name     = sanitize_text_field((string) $request->get_param('sender_name'));
            $message  = sanitize_textarea_field((string) $request->get_param('message'));
            $external = self::normalize_https_url((string) $request->get_param('external_url'));

            if ($external !== '') {
                if (!self::host_allowed($external)) return Vana_Utils::api_response(false, 'Use link do YouTube, Drive ou Facebook.', 422);
                if (self::looks_like_drive_folder($external)) return Vana_Utils::api_response(false, 'Link parece ser de PASTA. Use link de ARQUIVO.', 422);
            }

            // Upload
            $image_url = '';
            if (!empty($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) return Vana_Utils::api_response(false, 'Falha no upload da imagem.', 400);
                if ($_FILES['image']['size'] > 5 * 1024 * 1024) return Vana_Utils::api_response(false, 'A imagem excede 5MB.', 413);

                $check = wp_check_filetype_and_ext($_FILES['image']['tmp_name'], $_FILES['image']['name']);
                if (!in_array((string)($check['type'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    return Vana_Utils::api_response(false, 'Apenas imagens JPG, PNG ou WEBP.', 415);
                }

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                $attachment_id = media_handle_upload('image', 0);
                if (is_wp_error($attachment_id)) return Vana_Utils::api_response(false, 'Erro ao salvar a imagem.', 500);
                $image_url = (string) wp_get_attachment_url($attachment_id);
            }

            if ($image_url === '' && $external === '' && trim($message) === '') {
                return Vana_Utils::api_response(false, 'Envie mensagem, foto ou link de vídeo.', 422);
            }

            $post_id = wp_insert_post([
                'post_title'  => $name ? "Oferenda de {$name}" : "Oferenda",
                'post_type'   => 'vana_submission',
                'post_status' => 'pending',
            ], true);

            if (is_wp_error($post_id)) return Vana_Utils::api_response(false, 'Erro ao registrar.', 500);

            update_post_meta($post_id, '_visit_id', $visit_id);
            update_post_meta($post_id, '_sender_display_name', $name);
            update_post_meta($post_id, '_message', $message);
            update_post_meta($post_id, '_consent_publish', 1);
            update_post_meta($post_id, '_submitted_at', time());
            if ($image_url) update_post_meta($post_id, '_image_url', $image_url);
            if ($external)  update_post_meta($post_id, '_external_url', $external);

            return Vana_Utils::api_response(
                    true, 
                    'Oferenda enviada com sucesso! Aguardando moderação.', 
                    201, 
                    ['submission_id' => (int) $post_id]
                );

        } catch (Throwable $e) {
            Vana_Utils::log('INTERNAL_ERROR checkin: ' . $e->getMessage());
            return Vana_Utils::api_response(false, 'Erro interno no processamento.', 500);
        }
    }
}