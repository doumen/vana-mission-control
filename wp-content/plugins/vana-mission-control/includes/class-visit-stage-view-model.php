<?php
/**
 * VisitStageViewModel
 *
 * Contrato de saída do VisitStageResolver.
 * SSR-first: to_template_vars() retorna array flat para extract().
 * REST-ready: to_json_response() para uso futuro em HTMX/REST.
 *
 * @package VanaMissionControl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class VisitStageViewModel {

    /**
     * @var array<string,mixed>
     */
    private array $data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct( array $data ) {
        $this->data = $data;
    }

    /**
     * Array flat para extract() no template SSR.
     *
     * Variáveis disponíveis após extract():
     *
     * — Identidade
     * $visit_id            int
     * $visit_ref           string
     *
     * — Timeline bruta
     * $timeline            array
     * $overrides           array
     *
     * — Contexto ativo da página
     * $active_day          array
     * $active_day_date     string  (YYYY-MM-DD)
     * $active_events       array
     * $active_event        array|null
     *
     * — Palco / hero
     * $hero                array   (type, source, kind, status, event_key, ...)
     * $stage_mode          string
     *
     * — Viewer state
     * $viewer_mode         string  (default: 'vod')
     * $viewer_event_key    string
     * $viewer_item_id      string
     *
     * — Editorial bruto (debug/inspeção)
     * $editorial_hero_type      string
     * $editorial_hero_event_key string
     * $editorial_hero_item_id   string
     *
     * — Metadados gerais
     * $visit_timezone      string
     * $visit_status        string
     *
     * @return array<string,mixed>
     */
    public function to_template_vars(): array {
        return $this->data;
    }

    /**
     * Payload serializado para REST / HTMX (uso futuro).
     *
     * @return array<string,mixed>
     */
    public function to_json_response(): array {
        return array_diff_key(
            $this->data,
            array_flip( [
                'timeline',   // não expõe estrutura bruta na API pública
                'overrides',
                'editorial_hero_type',
                'editorial_hero_event_key',
                'editorial_hero_item_id',
            ] )
        );
    }

    /**
     * Acesso direto a uma chave do ViewModel.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get( string $key, mixed $default = null ): mixed {
        return $this->data[ $key ] ?? $default;
    }

    /**
     * Acesso ao array completo.
     *
     * @return array<string,mixed>
     */
    public function all(): array {
        return $this->data;
    }
}
