<?php
/**
 * REST Endpoint: Stage Fragment
 * Arquivo: includes/rest/class-vana-rest-stage-fragment.php
 * Version: 1.0.0
 *
 * GET /wp-json/vana/v1/stage-fragment
 *   ?visit_id=123
 *   &item_id=456
 *   &item_type=vod|gallery|sangha|event|restore
 *   &lang=pt|en
 *
 * Retorna HTML puro (text/html) para consumo HTMX.
 */
defined('ABSPATH') || exit;

class Vana_REST_Stage_Fragment {

    private const RAW_HTML_HEADER = 'X-Vana-Raw-HTML';

    public static function register(): void {
        add_filter('rest_pre_serve_request', [__CLASS__, 'serve_raw_html'], 10, 4);

        register_rest_route('vana/v1', '/stage-fragment', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle'],
            'permission_callback' => '__return_true',
            'args'                => [
                'visit_id' => [
                    'required'          => true,
                    'validate_callback' => fn($v) => is_numeric($v) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
                'item_id' => [
                    'required'          => false,
                    'default'           => 0,
                    'validate_callback' => function ($v, WP_REST_Request $request) {
                        if ((string) $request->get_param('item_type') === 'event') {
                            return is_string($v) || is_numeric($v);
                        }
                        return is_numeric($v);
                    },
                    'sanitize_callback' => function ($v, WP_REST_Request $request) {
                        if ((string) $request->get_param('item_type') === 'event') {
                            return sanitize_text_field((string) $v);
                        }
                        return absint($v);
                    },
                ],
                'item_type' => [
                    'required'          => false,
                    'default'           => 'vod',
                    'validate_callback' => fn($v) => in_array(
                        $v, ['vod','gallery','sangha','event','restore'], true
                    ),
                    'sanitize_callback' => 'sanitize_key',
                ],
                'lang' => [
                    'required'          => false,
                    'default'           => 'pt',
                    'validate_callback' => fn($v) => in_array($v, ['pt','en'], true),
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    public static function serve_raw_html(bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server): bool {
        if ($served) {
            return true;
        }

        if (! $result instanceof WP_REST_Response) {
            return false;
        }

        if ('1' !== (string) ($result->get_headers()[self::RAW_HTML_HEADER] ?? '')) {
            return false;
        }

        status_header($result->get_status());
        $server->send_headers($result->get_headers());
        echo (string) $result->get_data();

        return true;
    }

    // ─────────────────────────────────────────────────────────
    public static function handle(WP_REST_Request $request): WP_REST_Response {
        $visit_id  = (int)    $request->get_param('visit_id');
        $item_id   = (int)    $request->get_param('item_id');
        $item_type = (string) $request->get_param('item_type');
        $lang      = (string) $request->get_param('lang');

        // ── Valida visita ────────────────────────────────────
        $visit_post = get_post($visit_id);
        if (! $visit_post || $visit_post->post_status !== 'publish') {
            return self::html_response(
                '<div class="vana-stage-fragment-error">'
                . esc_html__('Visita não encontrada.', 'vana-mc')
                . '</div>',
                404
            );
        }

        // ── Restore — devolve stage.php original ─────────────
        if ($item_type === 'restore') {
            return self::html_response(
                self::render_restore($visit_id, $lang)
            );
        }

        // ── Event — resolve evento do timeline para Fase 2 ────
        if ($item_type === 'event') {
            // item_id é usado como string (event_key) para eventos
            $event_key = (string) $request->get_param('item_id');
            if (empty($event_key)) {
                return self::html_response(
                    '<div class="vana-stage-fragment-error">event_key vazio.</div>',
                    400
                );
            }
            return self::html_response(
                self::render_event_stage($visit_id, $event_key, $lang)
            );
        }

        // ── Valida item_id obrigatório para outros tipos ──────
        if ($item_id <= 0) {
            return self::html_response(
                '<div class="vana-stage-fragment-error">item_id inválido.</div>',
                400
            );
        }

        // ── Renderiza fragment ────────────────────────────────
        $fragment_path = VANA_MC_PATH . 'templates/visit/parts/stage-fragment.php';

        if (! file_exists($fragment_path)) {
            return self::html_response(
                '<div class="vana-stage-fragment-error">Fragment não encontrado.</div>',
                500
            );
        }

        // Expõe params via $_GET para o template PHP puro
        // (pattern idêntico ao _bootstrap.php)
        $_GET['visit_id']  = $visit_id;
        $_GET['item_id']   = $item_id;
        $_GET['item_type'] = $item_type;
        $_GET['lang']      = $lang;

        ob_start();
        include $fragment_path;
        $html = (string) ob_get_clean();

        return self::html_response($html);
    }

    /**
     * Event: renderiza stage para um evento específico da timeline
     * Busca o evento pelo event_key, normaliza via schema 5.1 e renderiza
     *
     * @param int $visit_id ID do post vana_visit
     * @param string $event_key Chave única do evento na timeline
     * @param string $lang Código de idioma (pt, en, etc)
     * @return string HTML renderizado
     * @since 4.3.0
     */
    private static function render_event_stage(int $visit_id, string $event_key, string $lang): string {
        // 1. Busca timeline JSON do post meta
        $timeline_json = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
        if (empty($timeline_json)) {
            return '<div class="vana-stage-fragment-error">Timeline não encontrada.</div>';
        }

        // 2. Decodifica e valida estrutura
        $timeline = json_decode($timeline_json, true);
        if (empty($timeline) || !is_array($timeline) || empty($timeline['days'])) {
            return '<div class="vana-stage-fragment-error">Timeline inválida.</div>';
        }

        // 3. Busca o evento pelos dias
        $found_event = null;
        $active_day = null;
        foreach ($timeline['days'] as $day) {
            if (empty($day['active_events']) || !is_array($day['active_events'])) {
                continue;
            }
            foreach ($day['active_events'] as $event) {
                // Tenta event_key ou key como fallback
                $check_key = $event['event_key'] ?? $event['key'] ?? null;
                if ($check_key === $event_key) {
                    $found_event = $event;
                    $active_day = $day;
                    break 2;
                }
            }
        }

        if (empty($found_event)) {
            return '<div class="vana-stage-fragment-error">Evento "' . esc_html($event_key) . '" não encontrado.</div>';
        }

        // 4. Normaliza evento para schema 5.1
        $event = vana_normalize_event($found_event);
        
        // 5. Resolve conteúdo (VOD → Gallery → Sangha → Placeholder)
        $stage_content = vana_get_stage_content($event);

        // 6. Monta variáveis para include
        $visit_tz = get_post_meta($visit_id, '_vana_visit_timezone', true) ?: 'UTC';
        $visit_status = $timeline['visit_status'] ?? 'scheduled';
        $visit_city_ref = (string) (($timeline['location_meta']['city_ref'] ?? '') ?: '');
        $active_day_date = (string) ($active_day['date_local'] ?? $active_day['date'] ?? '');
        $active_vod = $stage_content;
        $vod_list = $event['vod_list'] ?? [];
        
        // 7. Renderiza template
        $stage_path = VANA_MC_PATH . 'templates/visit/parts/stage.php';
        if (! file_exists($stage_path)) {
            return '<div class="vana-stage-fragment-error">stage.php não encontrado.</div>';
        }

        // phpcs:disable WordPress.PHP.DontExtract
        extract(compact(
            'lang', 'visit_id', 'visit_tz',
            'visit_city_ref', 'visit_status',
            'active_day', 'active_day_date',
            'active_vod', 'vod_list'
        ));
        // phpcs:enable

        ob_start();
        include $stage_path;
        return (string) ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────
    // Restore: re-renderiza o stage.php original da visita
    // ─────────────────────────────────────────────────────────
    private static function render_restore(int $visit_id, string $lang): string {
        $stage_path = VANA_MC_PATH . 'templates/visit/parts/stage.php';
        if (! file_exists($stage_path)) return '';

        // Bootstrap mínimo — espelha o que o _bootstrap_shim.php faz
        $visit_meta = get_post_meta($visit_id, '_vana_visit_data', true);
        $visit_data = is_array($visit_meta) ? $visit_meta : [];

        // Pega o primeiro dia como default
        $days       = is_array($visit_data['days'] ?? null) ? $visit_data['days'] : [];
        $active_day = ! empty($days) ? $days[0] : [];

        $active_day_date  = (string) ($active_day['date'] ?? '');
        $visit_tz         = (string) ($visit_data['timezone'] ?? 'America/Sao_Paulo');
        $active_vod       = [];
        $vod_list         = [];
        $vod_count        = 0;
        $active_vod_index = 0;

        // phpcs:disable WordPress.PHP.DontExtract
        extract(compact(
            'lang', 'visit_id', 'visit_tz',
            'active_day', 'active_day_date',
            'active_vod', 'vod_list', 'vod_count', 'active_vod_index'
        ));
        // phpcs:enable

        ob_start();
        include $stage_path;
        return (string) ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────
    // Helper — WP_REST_Response com Content-Type: text/html
    // ─────────────────────────────────────────────────────────
    private static function html_response(string $html, int $status = 200): WP_REST_Response {
        $response = new WP_REST_Response($html, $status);
        $response->header('Content-Type', 'text/html; charset=UTF-8');
        $response->header('X-Vana-Fragment', '1');
        $response->header(self::RAW_HTML_HEADER, '1');
        $response->header('Cache-Control', 'no-store');
        return $response;
    }
}
