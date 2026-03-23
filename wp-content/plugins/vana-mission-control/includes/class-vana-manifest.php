<?php
/**
 * PWA Web App Manifest — Gerado dinamicamente via PHP
 * Arquivo: includes/class-vana-manifest.php
 *
 * Responsabilidades:
 *   1. Interceptar ?vana_manifest=1 via query var do WordPress
 *   2. Gerar manifest.webmanifest válido com dados da visita ativa
 *   3. Injetar origin_key no start_url para abrir a visita correta
 *   4. Registrar ícones do selo em 192px e 512px
 *   5. Servir com Content-Type correto e cache de 1h
 *
 * Ativação em functions.php:
 *   Vana_Manifest::init();
 *
 * URL gerada pelo single-vana_visit.php:
 *   /wp-json não é usado aqui — usamos query var para
 *   evitar conflitos com o REST namespace e servir
 *   o manifest no domínio raiz (requisito PWA scope).
 *
 * Referência de scope PWA:
 *   scope: "/"  → permite instalar qualquer URL do site
 *   start_url   → abre direto na visita ativa
 */
defined('ABSPATH') || exit;

final class Vana_Manifest {

    // ── Constantes ────────────────────────────────────────────────
    private const QUERY_VAR   = 'vana_manifest';
    private const CACHE_TTL   = 3600;             // 1 hora
    private const MIME_TYPE   = 'application/manifest+json';

    // ── Init ──────────────────────────────────────────────────────

    public static function init(): void {
        add_filter('query_vars',      [__CLASS__, 'register_query_var']);
        add_action('template_redirect', [__CLASS__, 'maybe_serve_manifest'], 1);
    }

    // ── Registra query var ────────────────────────────────────────

    public static function register_query_var(array $vars): array {
        $vars[] = self::QUERY_VAR;
        $vars[] = 'visit_id';       // reutiliza a var do single
        return $vars;
    }

    // ── Intercepta a requisição ───────────────────────────────────

    public static function maybe_serve_manifest(): void {
        if (!get_query_var(self::QUERY_VAR)) return;

        $visit_id = sanitize_text_field(
            (string) (get_query_var('visit_id') ?? $_GET['visit_id'] ?? '')
        );

        self::serve($visit_id);
        exit;
    }

    // ══════════════════════════════════════════════════════════════
    //  GERAÇÃO DO MANIFEST
    // ══════════════════════════════════════════════════════════════

    private static function serve(string $visit_id): void {

        // ── Dados base do site ────────────────────────────────────
        $site_name      = get_bloginfo('name')        ?: 'Vana Madhuryam';
        $site_desc      = get_bloginfo('description') ?: 'Bhakti Chakor — Hari-kathā';
        $site_url       = trailingslashit(home_url('/'));
        $theme_dir      = get_stylesheet_directory_uri();

        // ── Cores ─────────────────────────────────────────────────
        $bg_color       = apply_filters('vana_pwa_bg_color',    '#0f172a');
        $theme_color    = apply_filters('vana_pwa_theme_color', '#FFD906');

        // ── Ícones do selo ────────────────────────────────────────
        $seal_base      = apply_filters(
            'vana_seal_base_url',
            $theme_dir . '/assets/images'
        );

        $icons = self::build_icons($seal_base);

        // ── start_url — abre direto na visita se houver origin_key ─
        $start_url = $site_url;
        if ($visit_id !== '') {
            $start_url = add_query_arg(
                ['visit_id' => $visit_id, 'pwa' => '1'],
                $site_url
            );
        }

        // ── Shortcuts — links rápidos no ícone do app (Android) ──
        $shortcuts = self::build_shortcuts($site_url, $visit_id);

        // ── Monta o manifest ──────────────────────────────────────
        $manifest = [
            'name'             => $site_name,
            'short_name'       => 'Vana',
            'description'      => $site_desc,
            'lang'             => 'pt-BR',
            'dir'              => 'ltr',

            // Exibição
            'display'          => 'standalone',
            'display_override' => ['standalone', 'minimal-ui', 'browser'],
            'orientation'      => 'portrait-primary',

            // Cores
            'background_color' => $bg_color,
            'theme_color'      => $theme_color,

            // Escopo e entrada
            'scope'            => $site_url,
            'start_url'        => $start_url,
            'id'               => $site_url . ($visit_id ? '?visit_id=' . $visit_id : ''),

            // Ícones
            'icons'            => $icons,

            // Shortcuts (Android)
            'shortcuts'        => $shortcuts,

            // Screenshots (para "install prompt" enriquecido)
            'screenshots'      => self::build_screenshots($seal_base),

            // Categorias
            'categories'       => ['education', 'lifestyle', 'entertainment'],

            // Share Target (permite compartilhar para o PWA)
            'share_target'     => [
                'action'  => $site_url . '?pwa_share=1',
                'method'  => 'GET',
                'params'  => [
                    'title' => 'title',
                    'text'  => 'text',
                    'url'   => 'url',
                ],
            ],

            // Protocol handlers (links vana:// abrem o app)
            'protocol_handlers' => [
                [
                    'protocol' => 'web+vana',
                    'url'      => $site_url . '?vana_protocol=%s',
                ],
            ],

            // Prefer related applications
            'prefer_related_applications' => false,
        ];

        // ── Filtro para customizações externas ────────────────────
        $manifest = apply_filters('vana_pwa_manifest', $manifest, $visit_id);

        // ── Headers HTTP ──────────────────────────────────────────
        // Limpa qualquer output anterior
        if (!headers_sent()) {
            // Remove headers do WordPress (evita text/html)
            header_remove('Content-Type');
            header_remove('X-Robots-Tag');

            header('Content-Type: '  . self::MIME_TYPE . '; charset=utf-8');
            header('Cache-Control: public, max-age=' . self::CACHE_TTL);
            header('X-Content-Type-Options: nosniff');

            // CORS — permite que subdomínios leiam o manifest
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($origin !== '' && self::is_allowed_origin($origin)) {
                header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            }

            // ETag simples para cache condicional
            $etag = '"vana-manifest-' . md5($visit_id . VANA_SCHEMA_VERSION) . '"';
            header('ETag: ' . $etag);

            $if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($if_none_match === $etag) {
                http_response_code(304);
                exit;
            }
        }

        // ── Output ────────────────────────────────────────────────
        echo wp_json_encode(
            $manifest,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRETTY_PRINT
        );
    }

    // ══════════════════════════════════════════════════════════════
    //  BUILDERS PRIVADOS
    // ══════════════════════════════════════════════════════════════

    /**
     * Monta array de ícones PWA a partir do selo.
     *
     * Estrutura esperada em /assets/images/:
     *   vana-seal-192.png    → ícone 192×192 (obrigatório)
     *   vana-seal-512.png    → ícone 512×512 (obrigatório)
     *   vana-seal-180.png    → apple-touch-icon
     *   vana-seal-maskable.png → ícone maskable (Android adaptativo)
     *   vana-seal.svg        → SVG vetorial (Chrome 113+)
     */
    private static function build_icons(string $base): array {
        return [
            [
                'src'     => $base . '/vana-seal-192.png',
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => $base . '/vana-seal-512.png',
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => $base . '/vana-seal-180.png',
                'sizes'   => '180x180',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => $base . '/vana-seal-maskable.png',
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src'     => $base . '/vana-seal.svg',
                'sizes'   => 'any',
                'type'    => 'image/svg+xml',
                'purpose' => 'any',
            ],
        ];
    }

    /**
     * Shortcuts — atalhos no ícone do app (Android long-press).
     */
    private static function build_shortcuts(string $site_url, string $visit_id): array {

        $shortcuts = [
            [
                'name'        => 'Hari-Kathā ao Vivo',
                'short_name'  => 'Ao Vivo',
                'description' => 'Assistir à transmissão ao vivo',
                'url'         => $site_url . ($visit_id
                                    ? '?visit_id=' . $visit_id . '&pwa=1'
                                    : ''),
                'icons'       => [[
                    'src'   => get_stylesheet_directory_uri()
                               . '/assets/images/shortcut-live.png',
                    'sizes' => '96x96',
                ]],
            ],
            [
                'name'        => 'YouTube — Vana Madhuryam',
                'short_name'  => 'YouTube',
                'description' => 'Canal oficial no YouTube',
                'url'         => 'https://www.youtube.com/@vanamadhuryamofficial',
                'icons'       => [[
                    'src'   => get_stylesheet_directory_uri()
                               . '/assets/images/shortcut-youtube.png',
                    'sizes' => '96x96',
                ]],
            ],
            [
                'name'        => 'Facebook — Vana Madhuryam',
                'short_name'  => 'Facebook',
                'description' => 'Comunidade no Facebook',
                'url'         => 'https://www.facebook.com/vanamadhuryamofficial',
                'icons'       => [[
                    'src'   => get_stylesheet_directory_uri()
                               . '/assets/images/shortcut-facebook.png',
                    'sizes' => '96x96',
                ]],
            ],
            [
                'name'        => 'Instagram — Vana Madhuryam',
                'short_name'  => 'Instagram',
                'description' => 'Descoberta e Hooks no Instagram',
                'url'         => 'https://www.instagram.com/vanamadhuryamofficial/',
                'icons'       => [[
                    'src'   => get_stylesheet_directory_uri()
                               . '/assets/images/shortcut-instagram.png',
                    'sizes' => '96x96',
                ]],
            ],
        ];

        // Filtro para adicionar/remover shortcuts dinamicamente
        return apply_filters('vana_pwa_shortcuts', $shortcuts, $visit_id);
    }

    /**
     * Screenshots para o install prompt enriquecido.
     * (Chrome 111+ exibe antes de instalar o PWA)
     */
    private static function build_screenshots(string $base): array {
        return [
            [
                'src'          => $base . '/screenshot-mobile-stage.jpg',
                'sizes'        => '390x844',
                'type'         => 'image/jpeg',
                'form_factor'  => 'narrow',
                'label'        => 'Stage — Hari-Kathā ao vivo',
            ],
            [
                'src'          => $base . '/screenshot-mobile-schedule.jpg',
                'sizes'        => '390x844',
                'type'         => 'image/jpeg',
                'form_factor'  => 'narrow',
                'label'        => 'Programação da visita',
            ],
            [
                'src'          => $base . '/screenshot-desktop-stage.jpg',
                'sizes'        => '1280x800',
                'type'         => 'image/jpeg',
                'form_factor'  => 'wide',
                'label'        => 'Stage Desktop — Hari-Kathā',
            ],
        ];
    }

    /**
     * Valida se a origem do CORS é permitida.
     */
    private static function is_allowed_origin(string $origin): bool {
        $allowed = apply_filters('vana_manifest_allowed_origins', [
            home_url(),
            'https://www.vanamadhuryamofficial.com',
        ]);

        foreach ($allowed as $allowed_origin) {
            if (rtrim($origin, '/') === rtrim($allowed_origin, '/')) {
                return true;
            }
        }

        return false;
    }
}
