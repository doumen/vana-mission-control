<?php
/**
 * Bootstrap do PHPUnit para testes unitários puros.
 * Define ABSPATH para que o guard WP não bloqueie o carregamento.
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// ── Stubs WP ──────────────────────────────────────────────────────────────────
// Funções WP usadas pelas classes de produção.
// Cada teste sobrescreve o comportamento via $_GET / $GLOBALS diretamente.

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
        return $GLOBALS['_vana_meta_stub'][ $post_id ][ $key ] ?? '';
    }
}

if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID(): int|false {
        return $GLOBALS['_vana_post_id_stub'] ?? false;
    }
}

if ( ! function_exists( 'get_post_type' ) ) {
    function get_post_type( int $post_id ): string|false {
        return $GLOBALS['_vana_post_type_stub'][ $post_id ] ?? false;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $str ): string {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( string $key ): string {
        return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( mixed $value ): mixed {
        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( string $message = '', string $title = '', array $args = [] ): never {
        throw new \RuntimeException( $message );
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return htmlspecialchars( $text, ENT_QUOTES );
    }
}

// ── Classes de produção ───────────────────────────────────────────────────────

require_once dirname( __DIR__ ) . '/includes/class-visit-event-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-visit-stage-view-model.php';
require_once dirname( __DIR__ ) . '/includes/class-visit-stage-resolver.php';
