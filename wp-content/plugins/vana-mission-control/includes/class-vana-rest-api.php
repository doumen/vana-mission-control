<?php
/**
 * REST API — Fase 1 — GET endpoints
 *
 * Rota    | Path                     | Params
 * --------|--------------------------|----------------------------
 * GET     | /vana/v1/media           | event_key, type
 * GET     | /vana/v1/sangha          | event_key
 * GET     | /vana/v1/revista         | visit_id
 *
 * EXCLUÍDAS desta classe (não duplicar):
 *   /vana/v1/kathas  → já existe em Vana_Hari_Katha_API (api/class-vana-hari-katha-api.php)
 *                      Integrar suporte a event_key naquela classe conforme Fase 2.
 *
 * POST /vana/v1/react   → removido desta fase (adendo-fase1.md C1 + resp1.md Gap 1)
 * POST /vana/v1/sangha  → Fase 2
 *
 * @since 6.0.0
 */
defined( 'ABSPATH' ) || exit;

final class Vana_REST_API {

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		$ns = 'vana/v1';

		// GET /vana/v1/media?event_key=&type=photo
		register_rest_route( $ns, '/media', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_media' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'event_key' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Chave única do evento (event_key)',
				],
				'type' => [
					'type'              => 'string',
					'default'           => 'photo',
					'sanitize_callback' => 'sanitize_key',
					'description'       => 'Tipo: photo | video',
				],
			],
		] );

		// GET /vana/v1/sangha?event_key=
		register_rest_route( $ns, '/sangha', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_sangha' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'event_key' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => 'Chave única do evento (event_key)',
				],
			],
		] );

		// GET /vana/v1/revista?visit_id=
		register_rest_route( $ns, '/revista', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_revista' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'visit_id' => [
					'type'              => 'integer',
					'required'          => false,
					'sanitize_callback' => 'absint',
					'description'       => 'ID do post vana_visit',
				],
			],
		] );

		/*
		 * Stubs NOT registrados (consultar antes de implementar):
		 *
		 * POST /vana/v1/react  → Fase 2 (adendo-fase1.md + resp1.md Gap 1)
		 *   register_rest_route( $ns, '/react', [
		 *     'methods'  => 'POST',
		 *     'callback' => [ __CLASS__, 'post_react' ],
		 *     ...
		 *   ] );
		 */
	}

	// ══════════════════════════════════════════════════════════════
	// GET /vana/v1/media
	// ══════════════════════════════════════════════════════════════

	public static function get_media( WP_REST_Request $req ): WP_REST_Response {
		// Implementar conforme CPT de foto/vídeo disponível — Fase 2
		return rest_ensure_response( [ 'items' => [], 'total' => 0 ] );
	}

	// ══════════════════════════════════════════════════════════════
	// GET /vana/v1/sangha
	// ══════════════════════════════════════════════════════════════

	public static function get_sangha( WP_REST_Request $req ): WP_REST_Response {
		// Implementar conforme CPT vana_sangha disponível — Fase 2
		return rest_ensure_response( [ 'items' => [], 'total' => 0 ] );
	}

	// ══════════════════════════════════════════════════════════════
	// GET /vana/v1/revista
	// ══════════════════════════════════════════════════════════════

	public static function get_revista( WP_REST_Request $req ): WP_REST_Response {
		// Implementar conforme CPT vana_revista disponível — Fase 2
		return rest_ensure_response( [ 'items' => [], 'total' => 0 ] );
	}
}
