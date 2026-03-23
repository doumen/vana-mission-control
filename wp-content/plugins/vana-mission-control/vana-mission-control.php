<?php
/**
 * Plugin Name: Vana Mission Control
 * Plugin URI: https://vanamadhuryam.org
 * Description: Sistema de gestão automatizada de Tours, Visits e Hari-katha para a missão de Śrīla Gurudeva
 * Version: 4.3.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Vana Madhuryam Bhakti Chakor
 * Author URI: https://vanamadhuryam.org
 * License: GPL v2 or later
 * Text Domain: vana-mission-control
 * @package Vana_Mission_Control
 */

defined("ABSPATH") || exit;

// ═══════════════════════════════════════════════════════════
//  CONSTANTES
// ═══════════════════════════════════════════════════════════
define("VANA_MC_VERSION",    "4.3.0");
define("VANA_MC_DB_VERSION", 2);
define("VANA_MC_PATH",       plugin_dir_path(__FILE__));
define("VANA_MC_URL",        plugin_dir_url(__FILE__));
define("VANA_MC_FILE",       __FILE__);
define("VANA_MC_BASENAME",   plugin_basename(__FILE__));

// ═══════════════════════════════════════════════════════════
//  VERIFICAÇÃO DE REQUISITOS
// ═══════════════════════════════════════════════════════════
function vana_mc_check_requirements(): bool {
    $errors = [];

    if (version_compare(PHP_VERSION, "8.0", "<")) {
        $errors[] = sprintf("Requer PHP 8.0+. Atual: %s", PHP_VERSION);
    }
    global $wp_version;
    if (version_compare($wp_version, "6.0", "<")) {
        $errors[] = sprintf("Requer WordPress 6.0+. Atual: %s", $wp_version);
    }

    if (!empty($errors)) {
        add_action("admin_notices", function () use ($errors) {
            echo "<div class=\"notice notice-error is-dismissible\">";
            echo "<p><strong>Vana Mission Control:</strong></p><ul>";
            foreach ($errors as $error) {
                echo "<li>" . esc_html($error) . "</li>";
            }
            echo "</ul></div>";
        });
        return false;
    }
    return true;
}

if (!vana_mc_check_requirements()) {
    return;
}

// ═══════════════════════════════════════════════════════════
//  CARREGAMENTO DE CLASSES
//  Ordem importa: dependências primeiro
// ═══════════════════════════════════════════════════════════

// ── Core ──────────────────────────────────────────────────
require_once VANA_MC_PATH . "includes/class-vana-utils.php";
require_once VANA_MC_PATH . "includes/class-vana-index.php";
require_once VANA_MC_PATH . "includes/class-vana-hmac.php";
require_once VANA_MC_PATH . "includes/class-vana-contract.php";
require_once VANA_MC_PATH . "includes/class-vana-store.php";

// ── CPTs ──────────────────────────────────────────────────
require_once VANA_MC_PATH . "includes/class-vana-tour-cpt.php";
require_once VANA_MC_PATH . "includes/class-vana-visit-cpt.php";
require_once VANA_MC_PATH . "includes/class-vana-submission-cpt.php"; // ← ALLOWED_HOSTS, MAX_IMAGES
require_once VANA_MC_PATH . "includes/class-vana-gallery-metabox.php";
Vana_Gallery_Metabox::init();

// ── Storage / Media (v2) ──────────────────────────────────
require_once VANA_MC_PATH . "includes/class-vana-image-processor.php"; // ← NOVO
require_once VANA_MC_PATH . "includes/class-vana-r2-client.php";        // ← NOVO

// ── APIs ──────────────────────────────────────────────────
require_once VANA_MC_PATH . "includes/class-vana-checkin-api.php";      // ← v2 (includes/)
require_once VANA_MC_PATH . "api/class-vana-ingest-api.php";
require_once VANA_MC_PATH . "api/class-vana-ingest-visit-api.php";
require_once VANA_MC_PATH . "api/class-vana-query-api.php";
require_once VANA_MC_PATH . "api/class-vana-ingest-katha-api.php";

// ── Utilitários ───────────────────────────────────────────
require_once VANA_MC_PATH . "includes/class-vana-visit-materializer.php";
require_once VANA_MC_PATH . "includes/class-visit-stage-resolver.php"; // ← VisitStageResolver SSR
require_once VANA_MC_PATH . "includes/cli/class-vana-cli-backfill.php";
require_once VANA_MC_PATH . "includes/rest/class-vana-rest-backfill.php";
require_once VANA_MC_PATH . "includes/class-vana-manifest.php";
require_once VANA_MC_PATH . "includes/class-vana-sw-registrar.php";

// ── Vana Stage — Schema 5.1 ────────────────────────────────
// Requer: vana_stage_resolve_media() já declarada em class-visit-stage-resolver.php
require_once VANA_MC_PATH . "inc/vana-stage.php";

// ── Templates / Shortcodes ────────────────────────────────
require_once VANA_MC_PATH . "templates/oferenda-form.php";

// ── Vana Katha ────────────────────────────────
require_once VANA_MC_PATH . "includes/class-vana-katha-cpt.php";
require_once VANA_MC_PATH . "includes/class-vana-hk-passage-cpt.php";
require_once VANA_MC_PATH . "includes/class-vana-hari-katha.php";
require_once VANA_MC_PATH . "api/class-vana-hari-katha-api.php";

// ── Visit REST API — Fase 1 ───────────────────────────────
require_once VANA_MC_PATH . "includes/class-vana-rest-api.php";
Vana_REST_API::init();

// ═══════════════════════════════════════════════════════════
//  CLASSE PRINCIPAL
// ═══════════════════════════════════════════════════════════
if (!class_exists("Vana_Mission_Control")) :

final class Vana_Mission_Control {

    private static ?self $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        // ── Inicialização de componentes ──────────────────
        add_action("plugins_loaded", [$this, "init_components"], 10);
        add_action("plugins_loaded", function () {
            Vana_Visit_CPT::init();
            Vana_Submission_CPT::init();
            Vana_Katha_CPT::init();
            Vana_HK_Passage_CPT::init();
        }, 5);

        // ── CPTs — via hook correto ───────────────────────
        // FIX: removidos os calls diretos Vana_Visit_CPT::init()
        //      e Vana_Submission_CPT::init() do __construct.
        //      Ambos registram seus próprios add_action("init")
        //      internamente — basta chamar no momento certo.
        add_action("plugins_loaded", function () {
        }, 5); // prioridade 5 → antes do init_components (10)

        // ── CPTs adicionais ───────────────────────────────
        add_action("init", [$this, "register_cpts"], 10);

        // ── REST API ──────────────────────────────────────
        add_action("rest_api_init", [$this, "register_rest_routes"]);

        // ── Frontend ──────────────────────────────────────
        add_action("wp_head",             [$this, "inject_css"], 5);
        add_action("wp_enqueue_scripts",  [$this, "enqueue_frontend_scripts"]);

        // ── AJAX público da página de visita ──────────────
        add_action('wp_ajax_vana_get_tours',        [$this, 'ajax_get_tours']);
        add_action('wp_ajax_nopriv_vana_get_tours', [$this, 'ajax_get_tours']);
        add_action('wp_ajax_vana_get_tour_visits',        [$this, 'ajax_get_tour_visits']);
        add_action('wp_ajax_nopriv_vana_get_tour_visits', [$this, 'ajax_get_tour_visits']);

        // ── Admin ─────────────────────────────────────────
        add_action("admin_enqueue_scripts", [$this, "admin_enqueue_scripts"]);
        add_action("admin_init",            [$this, "maybe_upgrade_db"]);
        add_action("add_meta_boxes",        [$this, "register_meta_boxes"]);
    }

    public function init_components(): void {
        Vana_Index::init();
        Vana_Manifest::init();
        Vana_SW_Registrar::init();
    }

    public function register_cpts(): void {
        Vana_Tour_CPT::register();
    }

    public function register_rest_routes(): void {
        Vana_Ingest_API::register();
        Vana_Checkin_API::register();       // v2
        Vana_Ingest_Visit_API::register();
        Vana_Query_API::register();
        Vana_Hari_Katha_API::register();
        Vana_Ingest_Katha_API::register();
        // ── FASE 4 — Stage endpoint semantico ───────────────
        require_once VANA_MC_PATH . "includes/rest/class-vana-rest-stage.php";
        (new Vana_REST_Stage())->register_routes();
        // ── FASE 5 — Stage Fragment HTMX ──────────────────────
        require_once VANA_MC_PATH . "includes/rest/class-vana-rest-stage-fragment.php";
        Vana_REST_Stage_Fragment::register();
    }

    public function ajax_get_tours(): void {
        check_ajax_referer('vana_visit_drawer', '_wpnonce');

        $visit_id = absint($_POST['visit_id'] ?? 0);
        $current_tour_id = (int) wp_get_post_parent_id($visit_id);
        if (!$current_tour_id && $visit_id > 0) {
            $current_tour_id = (int) get_post_meta($visit_id, '_vana_tour_id', true);
        }

        $query = new \WP_Query([
            'post_type'      => 'vana_tour',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'all',
            'no_found_rows'  => true,
        ]);

        $items = [];
        foreach ($query->posts as $tour_post) {
            $tour_id = (int) $tour_post->ID;
            $origin_key = (string) get_post_meta($tour_id, '_vana_origin_key', true);

            $visit_query = new \WP_Query([
                'post_type'      => 'vana_visit',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [[
                    'key'     => '_vana_parent_tour_origin_key',
                    'value'   => $origin_key,
                    'compare' => '=',
                ]],
            ]);

            $items[] = [
                'id'          => $tour_id,
                'title'       => get_the_title($tour_id),
                'permalink'   => get_permalink($tour_id),
                'is_current'  => $tour_id === $current_tour_id,
                'visit_count' => count($visit_query->posts),
            ];
        }

        wp_send_json_success($items);
    }

    public function ajax_get_tour_visits(): void {
        // Limpar qualquer output anterior ou buffer sujo
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Log debug info
        error_log('[VANA-DRAWER-DEBUG] AJAX called. POST data: ' . print_r($_POST, true));
        
        $check_result = check_ajax_referer('vana_visit_drawer', '_wpnonce', false);
        error_log('[VANA-DRAWER-DEBUG] Nonce check result: ' . var_export($check_result, true));
        
        if (!$check_result) {
            error_log('[VANA-DRAWER-DEBUG] Nonce check FAILED');
            wp_send_json_error(['message' => 'Nonce inválida.'], 400);
        }

        $tour_id  = absint($_POST['tour_id'] ?? 0);
        $visit_id = absint($_POST['visit_id'] ?? 0);
        $lang     = sanitize_key((string) ($_POST['lang'] ?? 'pt'));
        $lang     = in_array($lang, ['pt', 'en'], true) ? $lang : 'pt';

        error_log('[VANA-DRAWER-DEBUG] Parameters - tour_id:' . $tour_id . ', visit_id:' . $visit_id . ', lang:' . $lang);

        if ($tour_id <= 0) {
            error_log('[VANA-DRAWER-DEBUG] Invalid tour_id');
            wp_send_json_error(['message' => 'Tour inválido.'], 400);
        }

        // ── TENTATIVA 1: Por origin_key com prefixo "tour:" ─────────────────────
        $origin_key = (string) get_post_meta($tour_id, '_vana_origin_key', true);
        error_log('[VANA-DRAWER-DEBUG] Tour origin_key: ' . $origin_key);
        
        $query_args = [
            'post_type'      => 'vana_visit',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_key'       => '_vana_start_date',
            'fields'         => 'all',
            'no_found_rows'  => true,
        ];

        // Tenta com prefixo "tour:"
        if ($origin_key !== '') {
            $query_args['meta_query'] = [[
                'key'     => '_vana_parent_tour_origin_key',
                'value'   => 'tour:' . $origin_key,
                'compare' => '=',
            ]];
            $query = new \WP_Query($query_args);
            error_log('[VANA-DRAWER-DEBUG] Fallback 0 (with "tour:" prefix) - Found: ' . count($query->posts) . ' visits');
        } else {
            $query = new \WP_Query($query_args);
        }

        // ── FALLBACK 1: Se sem prefixo não funcionar, tenta sem prefixo ──────────
        if (empty($query->posts) && $origin_key !== '') {
            $query_args['meta_query'] = [[
                'key'     => '_vana_parent_tour_origin_key',
                'value'   => $origin_key,
                'compare' => '=',
            ]];
            $query = new \WP_Query($query_args);
            error_log('[VANA-DRAWER-DEBUG] Fallback 1 (without "tour:" prefix) - Found: ' . count($query->posts) . ' visits');
        }

        // ── FALLBACK 2: Tenta por tour_id direto ──────────────────────────────
        if (empty($query->posts)) {
            $query_args['meta_query'] = [[
                'key'     => '_vana_tour_id',
                'value'   => $tour_id,
                'compare' => '=',
            ]];
            $query = new \WP_Query($query_args);
            error_log('[VANA-DRAWER-DEBUG] Fallback 2 (_vana_tour_id) - Found: ' . count($query->posts) . ' visits');
        }

        // ── FALLBACK 3: Tenta por post_parent ─────────────────────────────────
        if (empty($query->posts)) {
            unset($query_args['meta_query']);
            $query_args['post_parent'] = $tour_id;
            $query = new \WP_Query($query_args);
            error_log('[VANA-DRAWER-DEBUG] Fallback 3 (post_parent) - Found: ' . count($query->posts) . ' visits');
        }

        // ── FALLBACK 4 (FINAL): Se ainda sem resultados, retorna visita atual + irmãs ─
        if (empty($query->posts) && $visit_id > 0) {
            error_log('[VANA-DRAWER-DEBUG] Fallback 4 (FINAL) - trying with visit_id: ' . $visit_id);
            $current_parent = wp_get_post_parent_id($visit_id);
            error_log('[VANA-DRAWER-DEBUG] Current visit parent: ' . $current_parent);
            
            if ($current_parent > 0) {
                $query_args = [
                    'post_type'      => 'vana_visit',
                    'post_status'    => 'publish',
                    'post_parent'    => $current_parent,
                    'posts_per_page' => 100,
                    'orderby'        => 'meta_value',
                    'order'          => 'ASC',
                    'meta_key'       => '_vana_start_date',
                    'fields'         => 'all',
                    'no_found_rows'  => true,
                ];
                $query = new \WP_Query($query_args);
                error_log('[VANA-DRAWER-DEBUG] Fallback 4a (siblings) - Found: ' . count($query->posts) . ' visits');
            }
            
            if (empty($query->posts)) {
                $query_args = [
                    'post_type'      => 'vana_visit',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'post__in'       => [$visit_id],
                    'fields'         => 'all',
                    'no_found_rows'  => true,
                ];
                $query = new \WP_Query($query_args);
                error_log('[VANA-DRAWER-DEBUG] Fallback 4b (current visit only) - Found: ' . count($query->posts) . ' visits');
            }
        }

        $items = [];
        foreach ($query->posts as $visit_post) {
            $id = (int) $visit_post->ID;
            $json = get_post_meta($id, '_vana_visit_timeline_json', true);
            $data = $json ? json_decode($json, true) : [];
            $data = is_array($data) ? $data : [];

            $items[] = [
                'id'         => $id,
                'title'      => (string) ($data['title_' . $lang] ?? $data['title_pt'] ?? get_the_title($id)),
                'permalink'  => (string) get_permalink($id),
                'start_date' => (string) get_post_meta($id, '_vana_start_date', true),
                'is_current' => $id === $visit_id,
            ];
        }

        error_log('[VANA-DRAWER-DEBUG] Final items count: ' . count($items));
        error_log('[VANA-DRAWER-DEBUG] Items: ' . print_r($items, true));
        
        // Limpar output antes de enviar JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        wp_send_json_success($items);
    }

    // ── Meta Boxes ────────────────────────────────────────

    public function register_meta_boxes(): void {
        add_meta_box(
            "vana_submission_details",
            "Conteúdo da Oferenda",
            [$this, "render_submission_box"],
            "vana_submission",
            "normal",  // voltou para "normal" — mais espaço para o grid
            "high"
        );
    }

    /**
     * Meta box atualizada para schema v2 (_media_items)
     * com fallback para metas v1 (_image_url / _external_url).
     */
    public function render_submission_box(\WP_Post $post): void {
        $name     = get_post_meta($post->ID, "_sender_display_name", true) ?: "Anônimo";
        $msg      = (string) get_post_meta($post->ID, "_message",      true);
        $subtype  = (string) get_post_meta($post->ID, "_subtype",      true) ?: "devotee";
        $time     = (int)    get_post_meta($post->ID, "_submitted_at", true);
        $city     = (string) get_post_meta($post->ID, "_vana_public_user_city", true);
        $date_str = $time ? wp_date("d/m/Y \à\s H:i", $time) : "Desconhecida";

        // ── Lê media_items v2 com fallback v1 ────────────
        $media_items = [];
        $raw = get_post_meta($post->ID, "_media_items", true);

        if (!empty($raw)) {
            $media_items = is_array($raw)
                ? $raw
                : (json_decode($raw, true) ?? []);
        } else {
            // Fallback v1
            $img_v1 = (string) get_post_meta($post->ID, "_image_url",    true);
            $ext_v1 = (string) get_post_meta($post->ID, "_external_url", true);
            if ($img_v1) $media_items[] = ["type" => "image", "url" => $img_v1,  "subtype" => "devotee", "status" => "approved"];
            if ($ext_v1) $media_items[] = ["type" => "video", "url" => $ext_v1, "subtype" => "devotee", "status" => "approved"];
        }

        // ── Render ────────────────────────────────────────
        echo "<div style=\"background:#f8fafc;padding:20px;border-radius:8px;border:1px solid #e2e8f0;\">";

        // Cabeçalho
        echo "<p><strong>Data:</strong> "    . esc_html($date_str) . "</p>";
        echo "<p><strong>Devoto(a):</strong> " . esc_html($name)   . "</p>";
        echo "<p><strong>Subtype:</strong> "   . esc_html($subtype) . "</p>";
        if ($city) {
            echo "<p><strong>Cidade:</strong> " . esc_html($city) . "</p>";
        }

        // Mensagem
        if ($msg) {
            echo "<hr style=\"margin:15px 0;border:0;border-top:1px solid #cbd5e1;\">";
            echo "<p><strong>Mensagem:</strong></p>";
            echo "<div style=\"background:#fff;padding:15px;border:1px solid #cbd5e1;"
               . "border-radius:6px;font-size:14px;line-height:1.6;\">"
               . nl2br(esc_html($msg)) . "</div>";
        }

        // Media items (v2 — grid)
        if (!empty($media_items)) {
            echo "<hr style=\"margin:15px 0;border:0;border-top:1px solid #cbd5e1;\">";
            echo "<p><strong>Mídias (" . count($media_items) . "):</strong></p>";
            echo "<div style=\"display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;\">";

            foreach ($media_items as $i => $item) {
                $type    = $item["type"]    ?? "image";
                $url     = esc_url($item["url"] ?? "");
                $status  = $item["status"]  ?? "pending";
                $sub     = $item["subtype"] ?? "";

                $badge_color = match($status) {
                    "approved" => "#15803d",
                    "rejected" => "#b91c1c",
                    default    => "#d97706",
                };

                echo "<div style=\"border:1px solid #e2e8f0;border-radius:8px;"
                   . "overflow:hidden;background:#fff;position:relative;\">";

                if ($type === "image" && $url) {
                    echo "<img src=\"" . $url . "\" style=\"width:100%;height:80px;"
                       . "object-fit:cover;display:block;\" loading=\"lazy\">";
                } else {
                    echo "<div style=\"height:80px;display:flex;align-items:center;"
                       . "justify-content:center;background:#f1f5f9;font-size:24px;\">📹</div>";
                }

                echo "<div style=\"padding:4px 6px;font-size:11px;\">";
                echo "<span style=\"background:" . $badge_color . ";color:#fff;"
                   . "border-radius:4px;padding:1px 5px;font-size:10px;font-weight:700;\">"
                   . esc_html($status) . "</span>";
                if ($sub) echo " <small>" . esc_html($sub) . "</small>";
                echo "</div>";

                echo "</div>"; // item
            }

            echo "</div>"; // grid
        }

        echo "</div>"; // wrap
    }

    // ── Frontend ──────────────────────────────────────────

    public function inject_css(): void {
          $asset_css = static function (string $relative): string {
            return VANA_MC_PATH . ltrim($relative, '/\\');
          };
        ?>
        <link rel="stylesheet" id="vana-fonts-css"
              href="https://fonts.googleapis.com/css2?family=Syne:wght@700&family=Questrial&display=swap"
              type="text/css" media="all" />
          <?php if (file_exists($asset_css('assets/css/vana-ui.tokens.css'))) : ?>
        <link rel="stylesheet" id="vana-ui-tokens-css"
              href="<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.tokens.css'); ?>?ver=<?php echo VANA_MC_VERSION; ?>"
              type="text/css" media="all" />
          <?php endif; ?>
          <?php if (file_exists($asset_css('assets/css/vana-ui.components.css'))) : ?>
        <link rel="stylesheet" id="vana-ui-components-css"
              href="<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.components.css'); ?>?ver=<?php echo VANA_MC_VERSION; ?>"
              type="text/css" media="all" />
          <?php endif; ?>
          <?php if (file_exists($asset_css('assets/css/vana-ui.hierarchy.css'))) : ?>
        <link rel="stylesheet" id="vana-ui-hierarchy-css"
              href="<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.hierarchy.css'); ?>?ver=<?php echo VANA_MC_VERSION; ?>"
              type="text/css" media="all" />
          <?php endif; ?>
          <?php if ($this->is_astra_active() && file_exists($asset_css('assets/css/vana-ui.astra-bridge.css'))) : ?>
        <link rel="stylesheet" id="vana-ui-astra-bridge-css"
              href="<?php echo esc_url(VANA_MC_URL . 'assets/css/vana-ui.astra-bridge.css'); ?>?ver=<?php echo VANA_MC_VERSION; ?>"
              type="text/css" media="all" />
        <?php endif; ?>
        <style id="vana-force-styles">
            body { font-family: \'Questrial\', system-ui, sans-serif !important; }
            h1,h2,h3,h4,h5,h6 { font-family: \'Syne\', system-ui, sans-serif !important; font-weight: 700 !important; }
            .page-header h1, .vana-archive-header h1 { color: var(--vana-gold,#FFD700) !important; font-size: clamp(2rem,5vw,3.5rem) !important; text-align:center; margin-bottom:1.5rem; }
            .wp-block-post, article.post, .vana-card { background:#fff; border-radius:8px; padding:2rem; box-shadow:0 4px 12px rgba(0,0,0,.1); margin-bottom:2rem; }
            .wp-block-post h2, article.post h2, .vana-card h2 { color:var(--vana-blue,#4AA3FF) !important; font-size:1.75rem; }
            .vana-btn,.wp-block-button__link,.button,a.button { background:linear-gradient(135deg,#FFD700,#D4AF37) !important; color:#1A202C !important; border-radius:8px !important; padding:1rem 2rem !important; font-family:\'Syne\',sans-serif !important; font-weight:700 !important; text-decoration:none !important; display:inline-block; transition:transform .2s; }
            .vana-btn:hover,.button:hover { transform:translateY(-2px); box-shadow:0 8px 16px rgba(253,214,128,.4); }
            .vana-badge,.badge,.status-badge { background:#FFD700 !important; color:#1A202C !important; padding:.5rem 1rem; border-radius:4px; font-size:.875rem; font-weight:700; text-transform:uppercase; }
        </style>
        <?php
    }

    public function enqueue_frontend_scripts(): void {
        if (is_singular("vana_visit")) {
            $vana_ec_path = VANA_MC_PATH . "assets/js/VanaEventController.js";
            $vana_ec_ver  = file_exists($vana_ec_path)
                ? (string) filemtime($vana_ec_path)
                : VANA_MC_VERSION;
            $vana_vc_path = VANA_MC_PATH . "assets/js/VanaVisitController.js";
            $vana_vc_ver  = file_exists($vana_vc_path)
                ? (string) filemtime($vana_vc_path)
                : VANA_MC_VERSION;
            $vana_cc_path = VANA_MC_PATH . "assets/js/VanaChipController.js";
            $vana_cc_ver  = file_exists($vana_cc_path)
                ? (string) filemtime($vana_cc_path)
                : VANA_MC_VERSION;
            $vana_ac_path = VANA_MC_PATH . "assets/js/VanaAgendaController.js";
            $vana_ac_ver  = file_exists($vana_ac_path)
                ? (string) filemtime($vana_ac_path)
                : VANA_MC_VERSION;

            wp_enqueue_style(
                "vana-ui-visit-hub",
                VANA_MC_URL . "assets/css/vana-ui.visit-hub.css",
                [],
                VANA_MC_VERSION
            );

            wp_enqueue_script(
                "vana-event-controller",
                VANA_MC_URL . "assets/js/VanaEventController.js",
                [],                  // sem dependência jQuery
                $vana_ec_ver,
                true                 // footer
            );

            wp_enqueue_script(
                "vana-visit-controller",
                VANA_MC_URL . "assets/js/VanaVisitController.js",
                [],
                $vana_vc_ver,
                true
            );

            wp_enqueue_script(
                "vana-chip-controller",
                VANA_MC_URL . "assets/js/VanaChipController.js",
                [],
                $vana_cc_ver,
                true
            );

            wp_enqueue_script(
                "vana-agenda-controller",
                VANA_MC_URL . "assets/js/VanaAgendaController.js",
                [],
                $vana_ac_ver,
                true
            );
        }
    }

    public function admin_enqueue_scripts(string $hook): void {
        if (!$this->is_vana_admin_page($hook)) return;
        wp_enqueue_style("vana-admin", VANA_MC_URL . "assets/css/admin.css", [], VANA_MC_VERSION);
    }

    public function maybe_upgrade_db(): void {
        $current = (int) get_option("vana_mc_db_version", 0);
        if ($current < VANA_MC_DB_VERSION) {
            if ($current < 1) {
                Vana_Index::create_table();
            }
            update_option("vana_mc_db_version", VANA_MC_DB_VERSION);
        }
    }

    private function is_vana_admin_page(string $hook): bool {
        if (!in_array($hook, ["post.php", "post-new.php", "edit.php"], true)) {
            return false;
        }
        global $typenow, $post;
        $pt = $typenow ?: ($post->post_type ?? "");
        return in_array($pt, ["vana_tour", "vana_visit", "vana_submission"], true);
    }

    private function is_astra_active(): bool {
        return class_exists("Astra_Theme_Options") || function_exists("astra_get_option");
    }
}

endif;

// ── Bootstrap ─────────────────────────────────────────────
function vana_mission_control(): Vana_Mission_Control {
    return Vana_Mission_Control::instance();
}
vana_mission_control();

// ═══════════════════════════════════════════════════════════
//  FILTROS GLOBAIS
// ═══════════════════════════════════════════════════════════

// Desativa Gutenberg para vana_submission
add_filter("use_block_editor_for_post_type", function ($use, $post_type) {
    return $post_type === "vana_submission" ? false : $use;
}, 10, 2);

// ── Roteador de templates ──────────────────────────────────
add_filter("template_include", function ($template) {
    if (is_singular("vana_tour")) {
        $t = VANA_MC_PATH . "templates/single-vana_tour.php";
        if (file_exists($t)) return $t;
    }
    if (is_post_type_archive("vana_tour")) {
        $t = VANA_MC_PATH . "templates/archive-vana_tour.php";
        if (file_exists($t)) return $t;
    }
    if (is_singular("vana_visit")) {
        $t = VANA_MC_PATH . "templates/single-vana_visit.php";
        if (file_exists($t)) return $t;
    }
    if (is_post_type_archive("vana_visit")) {
        $t = locate_template(["archive-vana_visit.php"])
          ?: VANA_MC_PATH . "templates/archive-vana_visit.php";
        if (file_exists($t)) return $t;
    }
    return $template;
}, 99);

// ═══════════════════════════════════════════════════════════
//  ATIVAÇÃO
// ═══════════════════════════════════════════════════════════
register_activation_hook(__FILE__, function () {
    Vana_Index::init();
    Vana_Index::create_table();
    Vana_Tour_CPT::register();
    if (class_exists("Vana_Visit_CPT")) {
        Vana_Visit_CPT::register();
        Vana_Visit_CPT::register_meta();
    }
    if (class_exists("Vana_Katha_CPT")) {
        Vana_Katha_CPT::register();
        Vana_Katha_CPT::register_meta();
    }

    if (class_exists("Vana_HK_Passage_CPT")) {
        Vana_HK_Passage_CPT::register();
        Vana_HK_Passage_CPT::register_meta();
        Vana_HK_Passage_CPT::register_taxonomies();
    }
    
    flush_rewrite_rules();
    add_option("vana_mc_db_version", VANA_MC_DB_VERSION);
});
