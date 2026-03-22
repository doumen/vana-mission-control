<?php
/**
 * REST Route: /wp-json/vana/v1/stage/{event_key}
 *
 * Endpoint semantico para stage por event_key.
 * /stage-fragment permanece ativo para backward compatibility.
 *
 * @since 5.4.0
 */
defined('ABSPATH') || exit;

class Vana_REST_Stage {

    private const REST_NAMESPACE = 'vana/v1';
    private const ROUTE = '/stage/(?P<event_key>[a-zA-Z0-9_-]+)';
    private const RAW_HTML_HEADER = 'X-Vana-Raw-HTML';

    public function register_routes(): void {
        add_filter('rest_pre_serve_request', [$this, 'serve_raw_html'], 10, 4);

        register_rest_route(
            self::REST_NAMESPACE,
            self::ROUTE,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handle'],
                'permission_callback' => '__return_true',
                'args'                => $this->get_args(),
            ]
        );
    }

    public function serve_raw_html(bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server): bool {
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

    private function get_args(): array {
        return [
            'event_key' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static function ($value) {
                    return (bool) preg_match('/^[a-zA-Z0-9_-]{3,80}$/', (string) $value);
                },
                'description' => 'Chave unica do evento (event_key)',
            ],
            'visit_id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static function ($value) {
                    return absint($value) > 0;
                },
                'description' => 'ID do post vana_visit',
            ],
            'lang' => [
                'required'          => false,
                'default'           => 'pt',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => static function ($value) {
                    return in_array($value, ['pt', 'en'], true);
                },
                'description' => 'Idioma do fragmento (pt|en)',
            ],
        ];
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $event_key = (string) $request->get_param('event_key');
        $visit_id  = (int) $request->get_param('visit_id');
        $lang      = (string) ($request->get_param('lang') ?: 'pt');

        $post = get_post($visit_id);
        if (! $post || 'vana_visit' !== $post->post_type) {
            return $this->error(
                'invalid_visit',
                'Visit ID invalido ou post type incorreto.',
                404
            );
        }

        $raw = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
        $timeline = $raw ? json_decode((string) $raw, true) : null;

        if (! is_array($timeline)) {
            return $this->error(
                'no_timeline',
                'Este visit nao possui timeline configurada.',
                404
            );
        }

        $match = $this->find_event($timeline, $event_key);
        if (! $match) {
            return $this->error(
                'event_not_found',
                sprintf("Evento '%s' nao encontrado nesta timeline.", $event_key),
                404
            );
        }

        $event = $match['event'];
        $active_day = $match['day'];

        if (function_exists('vana_normalize_event')) {
            $event = vana_normalize_event($event);
        }

        $stage_content = function_exists('vana_get_stage_content')
            ? vana_get_stage_content($event)
            : [];

        $visit_tz         = (string) (get_post_meta($visit_id, '_vana_visit_timezone', true) ?: 'UTC');
        $visit_status     = (string) ($timeline['visit_status'] ?? 'scheduled');
        $active_vod       = $stage_content;
        $vod_list         = is_array($event['vod_list'] ?? null) ? $event['vod_list'] : [];
        $active_day_date  = (string) ($active_day['date'] ?? $active_day['date_local'] ?? '');
        $visit_city_ref   = (string) ($timeline['visit_city_ref'] ?? '');

        $html = $this->render_stage(compact(
            'lang',
            'visit_id',
            'visit_tz',
            'visit_city_ref',
            'active_day',
            'active_day_date',
            'active_vod',
            'vod_list',
            'visit_status'
        ));

        if ($html === false) {
            return $this->error(
                'render_failed',
                'Falha ao renderizar o fragmento do stage.',
                500
            );
        }

        return $this->html_response($html);
    }

    /**
     * @param array<string,mixed> $timeline
     * @param string $event_key
     * @return array{event: array<string,mixed>, day: array<string,mixed>}|null
     */
    private function find_event(array $timeline, string $event_key): ?array {
        if (! empty($timeline['days']) && is_array($timeline['days'])) {
            foreach ($timeline['days'] as $day) {
                if (! is_array($day)) {
                    continue;
                }
                $events = [];
                if (! empty($day['active_events']) && is_array($day['active_events'])) {
                    $events = $day['active_events'];
                } elseif (! empty($day['events']) && is_array($day['events'])) {
                    $events = $day['events'];
                }

                foreach ($events as $ev) {
                    if (! is_array($ev)) {
                        continue;
                    }
                    $key = (string) ($ev['event_key'] ?? $ev['key'] ?? '');
                    if ($key === $event_key) {
                        return [
                            'event' => $ev,
                            'day'   => $day,
                        ];
                    }
                }
            }
        }

        if (! empty($timeline['events']) && is_array($timeline['events'])) {
            foreach ($timeline['events'] as $ev) {
                if (! is_array($ev)) {
                    continue;
                }
                $key = (string) ($ev['event_key'] ?? $ev['key'] ?? '');
                if ($key === $event_key) {
                    return [
                        'event' => $ev,
                        'day'   => [],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $vars
     * @return string|false
     */
    private function render_stage(array $vars) {
        $template = get_template_directory() . '/templates/visit/parts/stage.php';
        if (! file_exists($template)) {
            $template = VANA_MC_PATH . 'templates/visit/parts/stage.php';
        }
        if (! file_exists($template)) {
            return false;
        }

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($vars, EXTR_SKIP);

        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    private function html_response(string $html): WP_REST_Response {
        $response = new WP_REST_Response($html, 200);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->header('X-Vana-Endpoint', 'stage-v2');
        $response->header(self::RAW_HTML_HEADER, '1');
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    private function error(string $code, string $message, int $status): WP_REST_Response {
        $response = new WP_REST_Response(
            '<div class="vana-stage-error" data-error="' . esc_attr($code) . '">'
            . '<p>' . esc_html($message) . '</p>'
            . '</div>',
            $status
        );
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->header(self::RAW_HTML_HEADER, '1');
        return $response;
    }
}
