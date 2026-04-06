<?php
/**
 * Class Vana_Assets
 * Enqueue de CSS/JS do plugin Vana Mission Control.
 *
 * @package VanaMissionControl
 */
if (!defined('ABSPATH')) exit;

class Vana_Assets
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts',    [self::class, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin']);
        // The agenda controller is enqueued with `in_footer` and `defer` below.
    }

    // -------------------------------------------------------------------------
    // FRONT-END
    // -------------------------------------------------------------------------

    public static function enqueue_frontend(): void
    {
        // ── Só carrega os assets de visita na página da visita ────────
        $is_visit_page = is_singular( 'vana_visit' );

        if ( $is_visit_page ) {
            // ── Remove CSS do Astra que conflita com os drawers ───────
            add_action( 'wp_print_styles', function() {
                // Remove estilos do Astra que sobrescrevem position/transform
                wp_dequeue_style( 'astra-theme-css' );
                wp_dequeue_style( 'astra-theme-dynamic-css' );
                // Remove qualquer versão antiga do vana-drawer
                wp_dequeue_style( 'vana-drawer' );
                wp_dequeue_style( 'vana-visit-drawer' );
            }, 99 );

            // CSS global de visita
            wp_enqueue_style(
                'vana-visit',
                VANA_MC_URL . 'assets/css/vana-visit.css',
                [],
                VANA_MC_VERSION
            );

            // CSS do visit hub
            wp_enqueue_style(
                'vana-visit-hub',
                VANA_MC_URL . 'assets/css/vana-ui.visit-hub.css',
                ['vana-visit'],
                VANA_MC_VERSION
            );
        }

        // ── JS: sempre carregado (pode ser necessário fora da visita) ─
        wp_enqueue_script(
            'vana-visit-controller',
            VANA_MC_URL . 'assets/js/VanaVisitController.js',
            [],
            VANA_MC_VERSION,
            true
        );

        wp_enqueue_script(
            'vana-agenda-controller',
            VANA_MC_URL . 'assets/js/VanaAgendaController.js',
            [],
            VANA_MC_VERSION,
            true
        );
        if ( function_exists( 'wp_script_add_data' ) ) {
            wp_script_add_data( 'vana-agenda-controller', 'defer', true );
        }

        wp_enqueue_script(
            'vana-chip-controller',
            VANA_MC_URL . 'assets/js/VanaChipController.js',
            [],
            VANA_MC_VERSION,
            true
        );

        wp_enqueue_script(
            'vana-event-controller',
            VANA_MC_URL . 'assets/js/VanaEventController.js',
            [],
            VANA_MC_VERSION,
            true
        );
        wp_localize_script(
            'vana-event-controller',
            'vana_rest_root',
            [ 'url' => rest_url( 'vana/v1' ) ]
        );

        wp_enqueue_script(
            'vana-day-selector',
            VANA_MC_URL . 'assets/js/vana-day-selector.js',
            [],
            VANA_MC_VERSION,
            true
        );

        wp_localize_script(
            'vana-visit-controller',
            'VanaData',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'vana_nonce' ),
                'lang'    => self::get_current_lang(),
                'siteUrl' => get_site_url(),
                'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // ADMIN
    // -------------------------------------------------------------------------

    public static function enqueue_admin(string $hook): void
    {
        $screens = ['post.php', 'post-new.php', 'edit.php'];
        if (!in_array($hook, $screens, true)) return;

        // Adicione aqui CSS/JS de admin quando necessário
    }

    // -------------------------------------------------------------------------
    // HELPER — idioma atual
    // -------------------------------------------------------------------------

    public static function get_current_lang(): string
    {
        if (function_exists('pll_current_language')) {
            return pll_current_language('slug') === 'en' ? 'en' : 'pt';
        }
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE === 'en' ? 'en' : 'pt';
        }
        return str_starts_with(get_locale(), 'en') ? 'en' : 'pt';
    }

    // NOTE: the agenda controller is enqueued with `in_footer` and marked `defer`
    // via `wp_script_add_data` in `enqueue_frontend()`; no manual script tag
    // filtering is required here.
}

add_action('plugins_loaded', function () {
    if (defined('VANA_MC_URL')) {
        Vana_Assets::register();
    }
}, 5);
