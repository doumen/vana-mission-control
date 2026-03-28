<?php
/**
 * Helpers de template — Vana Mission Control
 * Arquivo: templates/visit/vana-utils.php
 *
 * Carregado pelo _bootstrap.php antes de qualquer partial.
 * Funções utilitárias compartilhadas entre hero-header, day-tabs, etc.
 */
defined('ABSPATH') || exit;

// ── URL canônica de visita ────────────────────────────────────────────────────
if ( ! function_exists( 'vana_visit_url' ) ) {
    /**
     * Gera a URL de uma visita com parâmetros opcionais.
     *
     * @param int    $post_id  ID da visita (CPT vana_visit)
     * @param string $v_day    Data YYYY-MM-DD (opcional)
     * @param int    $vod      Índice do VOD, -1 para omitir
     * @param string $lang     'pt' | 'en'
     */
    function vana_visit_url( int $post_id, string $v_day = '', int $vod = -1, string $lang = 'pt' ): string {
        $url = get_permalink( $post_id ) ?: '';
        if ( $v_day )      $url = add_query_arg( 'day',  $v_day, $url );
        if ( $vod >= 0 )   $url = add_query_arg( 'vod',  $vod,   $url );
        if ( $lang !== 'pt' ) $url = add_query_arg( 'lang', $lang,  $url );
        return $url;
    }
}
