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
                    'default'           => '',
                    'validate_callback' => function ($v, WP_REST_Request $request) {
                        $type = (string) $request->get_param('item_type');
                        if ( $type === 'event' ) {
                            return is_string($v) || is_numeric($v);
                        }
                        // vod: aceita video_id string (11 chars) OU post ID inteiro
                        return strlen(trim((string)$v)) > 0;
                    },
                    'sanitize_callback' => function ($v, WP_REST_Request $request) {
                        $type = (string) $request->get_param('item_type');
                        if ( $type === 'event' || ! is_numeric($v) ) {
                            return sanitize_text_field((string) $v);
                        }
                        return (string) absint($v);
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
        // Prevent LiteSpeed from serving cached HTML for this REST route.
        // This is critical because LiteSpeed may cache /wp-json responses
        // and serve stale fragment HTML. Ensure the endpoint always executes.
        if ( function_exists( 'do_action' ) ) {
            do_action( 'litespeed_control_set_nocache', 'vana_stage_fragment' );
        }
        // Standard no-cache headers for other caches and proxies
        if ( function_exists( 'nocache_headers' ) ) {
            nocache_headers();
        }
        header( 'X-LiteSpeed-Cache-Control: no-cache' );

        $visit_id  = (int)    $request->get_param('visit_id');
        $item_id   = (string) $request->get_param('item_id');
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
        if (empty($item_id)) {
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

        // ── Resolve vod_data (video_id string OU post ID int) ──────
        $vod_data = [];

        // Caso 1: video_id do YouTube (string não-numérica)
        if ( ! is_numeric( $item_id ) || strlen( (string) $item_id ) === 11 ) {
            // Tenta encontrar post com meta _video_id
            $posts = get_posts([
                'post_type'      => [ 'vana_katha', 'any' ],
                'meta_query'     => [[
                    'key'     => '_video_id',
                    'value'   => $item_id,
                    'compare' => '=',
                ]],
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]);
            if ( ! empty( $posts ) ) {
                $katha_data = get_post_meta( $posts[0], '_vana_katha_data', true );
                $vod_data   = is_array( $katha_data ) ? $katha_data : [];
            }

            // Fallback: busca no timeline da visita
            if ( empty( $vod_data ) ) {
                $timeline = json_decode( (string) get_post_meta( $visit_id, '_vana_visit_timeline_json', true ), true ) ?? [];
                $timeline = is_array( $timeline ) ? $timeline : [];
                $days = $timeline['days'] ?? $timeline;
                foreach ( $days as $day ) {
                    foreach ( $day['events'] ?? [] as $event ) {
                        foreach ( $event['vods'] ?? [] as $vod ) {
                            if ( ( $vod['video_id'] ?? '' ) === $item_id ) {
                                $vod_data = [
                                    'video_id' => $vod['video_id'],
                                    'provider' => $vod['provider'] ?? 'youtube',
                                    'title'    => [
                                        'pt' => $vod['title_pt'] ?? $vod['title']['pt'] ?? '',
                                        'en' => $vod['title_en'] ?? $vod['title']['en'] ?? '',
                                    ],
                                    'segments' => $vod['segments'] ?? [],
                                ];
                                break 3;
                            }
                        }
                    }
                }
            }

            // Último fallback: monta mínimo para o player funcionar
            if ( empty( $vod_data ) ) {
                $vod_data = [
                    'video_id' => (string) $item_id,
                    'provider' => 'youtube',
                    'title'    => [ 'pt' => '', 'en' => '' ],
                    'segments' => [],
                ];
            }
        }

        // Caso 2: post ID numérico (comportamento original)
        if ( empty( $vod_data ) && is_numeric( $item_id ) && (int) $item_id > 0 ) {
            $katha_data = get_post_meta( (int) $item_id, '_vana_katha_data', true );
            $vod_data   = is_array( $katha_data ) ? $katha_data : [];
        }

        // ── Renderiza via ob_start + extract (sem poluir $_GET) ──
        ob_start();
        extract( [
            'visit_id'  => $visit_id,
            'item_id'   => $item_id,
            'item_type' => $item_type,
            'lang'      => $lang,
            'vod_data'  => $vod_data,
        ] );
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
        // Prefer canonical bootstrap to find and resolve the event when safe
        require_once __DIR__ . '/../visit-stage-bootstrap.php';
        $bootstrap = vana_visit_stage_bootstrap( $visit_id, [ 'requested_event_key' => $event_key, 'lang' => $lang ] );

        $timeline = $bootstrap['timeline'] ?? [];
        if ( empty( $timeline ) || ! is_array( $timeline ) || empty( $timeline['days'] ?? [] ) ) {
            return '<div class="vana-stage-fragment-error">Timeline inválida.</div>';
        }

        $found_event = $bootstrap['active_event'] ?? null;
        $active_day = $bootstrap['active_day'] ?? null;

        if ( empty( $found_event ) || ! is_array( $found_event ) ) {
            return '<div class="vana-stage-fragment-error">Evento "' . esc_html($event_key) . '" não encontrado.</div>';
        }

        // 4. Normaliza evento para schema 5.1
        $event = function_exists('vana_normalize_event') ? vana_normalize_event($found_event) : $found_event;
        // expose normalized event to template to align with SSR
        $active_event = $event;
        
        // 5. Resolve conteúdo (VOD → Gallery → Sangha → Placeholder)
        $stage_content = vana_get_stage_content($event);

        // 6. Monta variáveis para include (use helper canonical values)
        $visit_tz = (string) ( $bootstrap['visit_tz'] ?? 'UTC' );
        $visit_status = (string) ( $bootstrap['visit_status'] ?? ( $timeline['visit_status'] ?? 'scheduled' ) );
        $visit_city_ref = (string) ( $bootstrap['visit_city_ref'] ?? ( $timeline['location_meta']['city_ref'] ?? '' ) ?: '' );
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
            'active_event', 'active_vod', 'vod_list'
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
		if ( ! file_exists( $stage_path ) ) {
			return '';
		}

		require_once __DIR__ . '/../visit-stage-bootstrap.php';
		$bootstrap = vana_visit_stage_bootstrap( $visit_id, [ 'lang' => $lang ] );

		$payload = get_post_meta( $visit_id, '_vana_visit_data', true );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		$visit_tz = isset( $bootstrap['visit_tz'] ) && is_string( $bootstrap['visit_tz'] ) && $bootstrap['visit_tz'] !== ''
			? $bootstrap['visit_tz']
			: 'UTC';

		$visit_status    = $bootstrap['visit_status'] ?? '';
		$visit_city_ref  = $bootstrap['visit_city_ref'] ?? 0;
		$active_day      = $bootstrap['active_day'] ?? null;
		$active_day_date = $bootstrap['active_day_date'] ?? '';
		$active_event    = $bootstrap['active_event'] ?? null;

        // Reconstrói vod_list a partir do active_event (schema 5.1 canônico)
        // item_id = video_id string (enviado pelo schedule.php via hx-vals)
        // vod_key = vod_id_single legado

        $_rest_active_event = $bootstrap['active_event'] ?? null;
        $vod_list = [];

        if ( is_array( $_rest_active_event ) ) {
            $_ek = (string) ( $_rest_active_event['event_key'] ?? $bootstrap['active_day_date'] ?? '' );
            foreach ( (array) ( $_rest_active_event['media']['vods'] ?? [] ) as $_v ) {
                if ( is_array( $_v ) ) {
                    $_v['_event_key'] = $_ek;
                    $vod_list[] = $_v;
                }
            }
            unset( $_ek, $_v );
        }

        // Fallback legado: payload traz vod_list pré-montada
        if ( empty( $vod_list ) && isset( $payload['vod_list'] ) && is_array( $payload['vod_list'] ) ) {
            $vod_list = $payload['vod_list'];
        }

        $vod_count = count( $vod_list );

        // Resolve índice: item_id (P1) > vod_key (P2) > 0 (P3)
        $_req_item = (string) ( $payload['item_id']  ?? '' );
        $_req_key  = (string) ( $payload['vod_key']  ?? '' );

        $active_vod_index = 0;

        if ( $_req_item !== '' ) {
            foreach ( $vod_list as $_vi => $_vod ) {
                if (
                    ( (string) ( $_vod['video_id'] ?? '' ) === $_req_item ) ||
                    ( (string) ( $_vod['url']      ?? '' ) === $_req_item )
                ) {
                    $active_vod_index = $_vi;
                    break;
                }
            }
        } elseif ( $_req_key !== '' ) {
            foreach ( $vod_list as $_vi => $_vod ) {
                if ( (string) ( $_vod['video_id'] ?? '' ) === $_req_key ) {
                    $active_vod_index = $_vi;
                    break;
                }
            }
        }
        unset( $_rest_active_event, $_req_item, $_req_key, $_vi, $_vod );

        $active_vod = ( $vod_count > 0 && isset( $vod_list[ $active_vod_index ] ) )
            ? $vod_list[ $active_vod_index ]
            : ( is_array( $payload['active_vod'] ?? null ) ? $payload['active_vod'] : [] );

		// phpcs:disable WordPress.PHP.DontExtract
		extract( compact(
			'lang',
			'visit_id',
			'visit_tz',
			'visit_city_ref',
			'visit_status',
			'active_day',
			'active_day_date',
			'active_event',
			'active_vod',
			'vod_list',
			'vod_count',
			'active_vod_index'
		) );
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
