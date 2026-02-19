<?php
/**
 * Plugin Name: Vana Mission Control
 * Plugin URI: https://vanamadhuryam.org
 * Description: Sistema de gestão automatizada de Tours, Visits e Hari-katha para a missão de Śrīla Gurudeva
 * Version: 4.2.4
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Vana Madhuryam Bhakti Chakor
 * Author URI: https://vanamadhuryam.org
 * License: GPL v2 or later
 * Text Domain: vana-mission-control
 * @package Vana_Mission_Control
 */

defined('ABSPATH') || exit;

// ========== CONSTANTES ==========
define('VANA_MC_VERSION', '4.2.4');
define('VANA_MC_DB_VERSION', 2);
define('VANA_MC_PATH', plugin_dir_path(__FILE__));
define('VANA_MC_URL', plugin_dir_url(__FILE__));
define('VANA_MC_FILE', __FILE__);
define('VANA_MC_BASENAME', plugin_basename(__FILE__));

// ========== VERIFICAÇÃO DE REQUISITOS ==========
function vana_mc_check_requirements(): bool {
    $errors = [];
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        $errors[] = sprintf('Requer PHP 8.0+. Atual: %s', PHP_VERSION);
    }
    global $wp_version;
    if (version_compare($wp_version, '6.0', '<')) {
        $errors[] = sprintf('Requer WordPress 6.0+. Atual: %s', $wp_version);
    }

    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Vana Mission Control:</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        });
        return false;
    }
    return true;
}

if (!vana_mc_check_requirements()) {
    return;
}

// ========== CARREGAMENTO DE CLASSES ==========
require_once VANA_MC_PATH . 'includes/class-vana-utils.php';
require_once VANA_MC_PATH . 'includes/class-vana-index.php';
require_once VANA_MC_PATH . 'includes/class-vana-hmac.php';
require_once VANA_MC_PATH . 'includes/class-vana-contract.php';
require_once VANA_MC_PATH . 'includes/class-vana-store.php';
require_once VANA_MC_PATH . 'includes/class-vana-tour-cpt.php';
require_once VANA_MC_PATH . 'includes/class-vana-visit-cpt.php';
require_once VANA_MC_PATH . 'includes/class-vana-submission-cpt.php';
require_once VANA_MC_PATH . 'api/class-vana-checkin-api.php';
require_once VANA_MC_PATH . 'api/class-vana-ingest-api.php';
require_once VANA_MC_PATH . 'includes/class-vana-visit-materializer.php';
require_once VANA_MC_PATH . 'includes/cli/class-vana-cli-backfill.php';
require_once VANA_MC_PATH . 'includes/rest/class-vana-rest-backfill.php';

// ========== CLASSE PRINCIPAL ==========
if (!class_exists('Vana_Mission_Control')) :

final class Vana_Mission_Control {

    private static ?self $instance = null;

    public static function instance(): self {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('plugins_loaded', [$this, 'init_components'], 10);
        Vana_Visit_CPT::init();
        Vana_Submission_CPT::init();
        add_action('init', [$this, 'register_cpts'], 10);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_head', [$this, 'inject_css'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('admin_init', [$this, 'maybe_upgrade_db']);
        
        // Hook para as Meta Boxes das Oferendas
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
    }

    public function init_components(): void {
        Vana_Index::init();
    }

    public function register_cpts(): void {
        Vana_Tour_CPT::register();
    }

    public function register_rest_routes(): void {
        Vana_Ingest_API::register();
        Vana_Checkin_API::register();
    }

    public function register_meta_boxes(): void {
            add_meta_box(
                'vana_submission_details',
                'Conteúdo da Oferenda',
                [$this, 'render_submission_box'],
                'vana_submission',
                'side', // Mudamos de 'normal' para 'side'
                'high'
            );
    }

    public function render_submission_box($post): void {
        $name = get_post_meta($post->ID, '_sender_display_name', true) ?: 'Anônimo';
        $msg  = get_post_meta($post->ID, '_message', true);
        $img  = get_post_meta($post->ID, '_image_url', true);
        $ext  = get_post_meta($post->ID, '_external_url', true);
        $time = get_post_meta($post->ID, '_submitted_at', true);
        $date_str = $time ? wp_date('d/m/Y \à\s H:i', $time) : 'Desconhecida';

        echo '<div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">';
        echo '<p><strong>Data de Envio:</strong> ' . esc_html($date_str) . '</p>';
        echo '<p><strong>Devoto(a):</strong> ' . esc_html($name) . '</p>';
        if ($msg) {
            echo '<hr style="margin: 15px 0; border: 0; border-top: 1px solid #cbd5e1;">';
            echo '<p><strong>Mensagem:</strong></p>';
            echo '<div style="background:#fff; padding:15px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; line-height:1.6;">' . nl2br(esc_html($msg)) . '</div>';
        }
        if ($img) {
            echo '<hr style="margin: 15px 0; border: 0; border-top: 1px solid #cbd5e1;">';
            echo '<img src="' . esc_url($img) . '" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">';
        }
        if ($ext) {
            echo '<hr style="margin: 15px 0; border: 0; border-top: 1px solid #cbd5e1;">';
            echo '<p><strong>Link de Vídeo:</strong> <a href="' . esc_url($ext) . '" target="_blank">' . esc_html($ext) . '</a></p>';
        }
        echo '</div>';
    }

    public function inject_css(): void {
        ?>
        <link rel='stylesheet' id='vana-fonts-css' href='https://fonts.googleapis.com/css2?family=Syne:wght@700&family=Questrial&display=swap' type='text/css' media='all' />
        <link rel='stylesheet' id='vana-ui-tokens-css' href='<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.tokens.css'); ?>?ver=<?php echo esc_attr(VANA_MC_VERSION); ?>' type='text/css' media='all' />
        <link rel='stylesheet' id='vana-ui-components-css' href='<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.components.css'); ?>?ver=<?php echo esc_attr(VANA_MC_VERSION); ?>' type='text/css' media='all' />
        <link rel='stylesheet' id='vana-ui-hierarchy-css' href='<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.hierarchy.css'); ?>?ver=<?php echo esc_attr(VANA_MC_VERSION); ?>' type='text/css' media='all' />
        <?php if ($this->is_astra_active()) : ?>
            <link rel='stylesheet' id='vana-ui-astra-bridge-css' href='<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.astra-bridge.css'); ?>?ver=<?php echo esc_attr(VANA_MC_VERSION); ?>' type='text/css' media='all' />
        <?php endif; ?>

        <style id="vana-force-styles">
            body { font-family: 'Questrial', system-ui, sans-serif !important; }
            h1, h2, h3, h4, h5, h6 { font-family: 'Syne', system-ui, sans-serif !important; font-weight: 700 !important; }
            .page-header h1, .vana-archive-header h1 { color: var(--vana-gold, #FFD700) !important; font-size: clamp(2rem, 5vw, 3.5rem) !important; text-align: center; margin-bottom: 1.5rem; }
            .wp-block-post, article.post, .vana-card { background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); margin-bottom: 2rem; }
            .wp-block-post h2, article.post h2, .vana-card h2 { color: var(--vana-blue, #4AA3FF) !important; font-size: 1.75rem; }
            .vana-btn, .wp-block-button__link, .button, a.button { background: linear-gradient(135deg, #FFD700, #D4AF37) !important; color: #1A202C !important; border-radius: 8px !important; padding: 1rem 2rem !important; font-family: 'Syne', sans-serif !important; font-weight: 700 !important; text-decoration: none !important; display: inline-block; transition: transform 0.2s; }
            .vana-btn:hover, .button:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(253, 214, 128, 0.4); }
            .vana-badge, .badge, .status-badge { background: #FFD700 !important; color: #1A202C !important; padding: 0.5rem 1rem; border-radius: 4px; font-size: 0.875rem; font-weight: 700; text-transform: uppercase; }
        </style>
        <?php
    }

    public function enqueue_frontend_scripts(): void {
        if (is_singular('vana_visit')) {
            wp_enqueue_style('vana-ui-visit-hub', VANA_MC_URL . 'assets/css/vana-ui.visit-hub.css', [], VANA_MC_VERSION);
        }
    }

    public function admin_enqueue_scripts($hook): void {
        if (!$this->is_vana_admin_page((string) $hook)) return;
        wp_enqueue_style('vana-admin', VANA_MC_URL . 'assets/css/admin.css', [], VANA_MC_VERSION);
    }

    public function maybe_upgrade_db(): void {
        $current_db_version = (int) get_option('vana_mc_db_version', 0);
        if ($current_db_version < VANA_MC_DB_VERSION) {
            if ($current_db_version < 1) {
                Vana_Index::create_table();
            }
            update_option('vana_mc_db_version', VANA_MC_DB_VERSION);
        }
    }

    private function is_vana_admin_page(string $hook): bool {
        $admin_pages = ['post.php', 'post-new.php', 'edit.php'];
        if (!in_array($hook, $admin_pages, true)) return false;
        global $post, $typenow;
        $pt = $typenow ?: ($post->post_type ?? '');
        return in_array($pt, ['vana_tour', 'vana_visit', 'vana_submission'], true);
    }

    private function is_astra_active(): bool {
        return class_exists('Astra_Theme_Options') || function_exists('astra_get_option');
    }
}

endif;

function vana_mission_control(): Vana_Mission_Control {
    return Vana_Mission_Control::instance();
}

vana_mission_control();

// Desativa o Gutenberg apenas para o post type de oferendas
add_filter('use_block_editor_for_post_type', function($use, $post_type) {
    if ($post_type === 'vana_submission') return false;
    return $use;
}, 10, 2);

// ========== ROTEADOR DE TEMPLATES ==========
add_filter('template_include', function($template) {
    if (is_singular('vana_tour')) {
        $custom_template = VANA_MC_PATH . 'templates/single-vana_tour.php';
        if (file_exists($custom_template)) return $custom_template;
    }
    if (is_post_type_archive('vana_tour')) {
        $custom_template = VANA_MC_PATH . 'templates/archive-vana_tour.php';
        if (file_exists($custom_template)) return $custom_template;
    }
    if (is_singular('vana_visit')) {
        $theme_tpl = locate_template(['single-vana_visit.php']);
        if ($theme_tpl) return $theme_tpl;
        $plugin_tpl = VANA_MC_PATH . 'templates/single-vana_visit.php';
        if (file_exists($plugin_tpl)) return $plugin_tpl;
    }
    if (is_post_type_archive('vana_visit')) {
    $theme_tpl = locate_template(['archive-vana_visit.php']);
    if ($theme_tpl) return $theme_tpl;

    $plugin_tpl = VANA_MC_PATH . 'templates/archive-vana_visit.php';
    if (file_exists($plugin_tpl)) return $plugin_tpl;
    }
    return $template;
}, 99);

// ========== ATIVAÇÃO ==========
register_activation_hook(__FILE__, function() {
    Vana_Index::init();
    Vana_Index::create_table();
    Vana_Tour_CPT::register();
    if (class_exists('Vana_Visit_CPT')) {
        Vana_Visit_CPT::register();
        Vana_Visit_CPT::register_meta();
    }
    flush_rewrite_rules();
    add_option('vana_mc_db_version', VANA_MC_DB_VERSION);
});