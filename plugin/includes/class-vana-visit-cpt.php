<?php
/**
 * CPT: vana_visit (Hub)
 * - Conteúdo vem do JSON do Trator (sem editor)
 * - Sem archive público
 * - Admin auditável: colunas + ordenação + filtro + busca por origin_key
 * - Meta registrada com auth_callback (hardening)
 */
defined('ABSPATH') || exit;

final class Vana_Visit_CPT {

    public static function init(): void {
        add_action('init', [__CLASS__, 'register']);
        add_action('init', [__CLASS__, 'register_meta']);

        // Admin list table: colunas + conteúdo
        add_filter('manage_vana_visit_posts_columns', [__CLASS__, 'admin_columns_head']);
        add_action('manage_vana_visit_posts_custom_column', [__CLASS__, 'admin_columns_content'], 10, 2);

        // Ordenação
        add_filter('manage_edit-vana_visit_sortable_columns', [__CLASS__, 'admin_sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'admin_orderby_meta']);

        // Filtro por Tour pai (meta)
        add_action('restrict_manage_posts', [__CLASS__, 'admin_filters']);
        add_action('pre_get_posts', [__CLASS__, 'admin_apply_filters']);

        // Busca por origin_key (sem mexer em SQL manual)
        add_action('pre_get_posts', [__CLASS__, 'admin_search_router']);
    }

    public static function register(): void {
        $labels = [
            'name'               => _x('Visitas', 'Post Type General Name', 'vana'),
            'singular_name'      => _x('Visita', 'Post Type Singular Name', 'vana'),
            'menu_name'          => __('Visitas', 'vana'),
            'name_admin_bar'     => __('Visita', 'vana'),
            'all_items'          => __('Todas as Visitas', 'vana'),
            'add_new_item'       => __('Adicionar Nova Visita', 'vana'),
            'new_item'           => __('Nova Visita', 'vana'),
            'edit_item'          => __('Editar Visita', 'vana'),
            'view_item'          => __('Ver Visita', 'vana'),
            'search_items'       => __('Pesquisar Visita', 'vana'),
            'not_found'          => __('Não encontrado', 'vana'),
            'not_found_in_trash' => __('Não encontrado no Lixo', 'vana'),
        ];

        $args = [
            'labels'              => $labels,
            'description'         => __('Hub de eventos de uma paragem da Tour (conteúdo via JSON do Trator)', 'vana'),

            // Conteúdo controlado pelo Trator
            'supports'            => ['title', 'thumbnail'],
            'hierarchical'        => false,

            // Público por URL (single), mas sem catálogo (archive)
            'public'              => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'has_archive'         => 'visits',

            // Admin
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 21,
            'menu_icon'           => 'dashicons-location-alt',
            'show_in_admin_bar'   => true,

            // REST do CPT não é necessária (ingest é via endpoint HMAC)
            'show_in_rest'        => false,

            // URL canônica
            'rewrite'             => [
                'slug'       => 'visit',
                'with_front' => false,
                'pages'      => true,
            ],

            'can_export'          => true,
        ];

        register_post_type('vana_visit', $args);
    }

    public static function register_meta(): void {
        $auth_cb = static function(): bool {
            return current_user_can('edit_posts');
        };

        $small_string_meta = [
            '_vana_origin_key',
            '_vana_parent_tour_origin_key',
            '_vana_timeline_schema_version',
            '_vana_timeline_updated_at',
            '_vana_timeline_hash',
            '_vana_start_date', // <--- ADICIONE ESTE
            '_vana_tz',         // <--- ADICIONE ESTE            
        ];

        foreach ($small_string_meta as $key) {
            register_post_meta('vana_visit', $key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'auth_callback'     => $auth_cb,
                'sanitize_callback' => 'sanitize_text_field',
            ]);
        }

        // JSON grande: não sanitizar (para não corromper)
        register_post_meta('vana_visit', '_vana_visit_timeline_json', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'auth_callback'     => $auth_cb,
            'sanitize_callback' => null,
        ]);
    }

    /* ---------------------------
     * Admin: Colunas
     * --------------------------- */

    public static function admin_columns_head(array $columns): array {
        $new = [];
        $inserted = false;

        foreach ($columns as $key => $title) {
            if ($key === 'date') {
                $new['origin_key']  = __('Origin Key', 'vana');
                $new['parent_tour'] = __('Tour Pai', 'vana');
                $new['schema']      = __('Schema', 'vana');
                $inserted = true;
            }
            $new[$key] = $title;
        }

        if (!$inserted) {
            $new['origin_key']  = __('Origin Key', 'vana');
            $new['parent_tour'] = __('Tour Pai', 'vana');
            $new['schema']      = __('Schema', 'vana');
        }

        return $new;
    }

    public static function admin_columns_content(string $column_name, int $post_id): void {
        switch ($column_name) {
            case 'origin_key': {
                $key = (string) get_post_meta($post_id, '_vana_origin_key', true);
                echo $key !== '' ? '<code>' . esc_html($key) . '</code>' : '<span aria-hidden="true">&mdash;</span>';
                break;
            }

            case 'parent_tour': {
                $parent = (string) get_post_meta($post_id, '_vana_parent_tour_origin_key', true);
                echo $parent !== '' ? esc_html($parent) : '<span aria-hidden="true">&mdash;</span>';
                break;
            }

            case 'schema': {
                $schema  = (string) get_post_meta($post_id, '_vana_timeline_schema_version', true);
                $updated = (string) get_post_meta($post_id, '_vana_timeline_updated_at', true);

                if ($schema === '' && $updated === '') {
                    echo '<span aria-hidden="true">&mdash;</span>';
                    break;
                }

                if ($schema !== '') {
                    echo '<strong>' . esc_html($schema) . '</strong>';
                }

                if ($updated !== '') {
                    try {
                        $dt  = new DateTimeImmutable($updated);
                        $fmt = function_exists('wp_date')
                            ? wp_date('d/m/y H:i', $dt->getTimestamp())
                            : date_i18n('d/m/y H:i', $dt->getTimestamp());
                        echo '<br><small style="color:#777;">' . esc_html($fmt) . '</small>';
                    } catch (Exception $e) {
                        echo '<br><small style="color:#777;">' . esc_html($updated) . '</small>';
                    }
                }
                break;
            }
        }
    }

    /* ---------------------------
     * Admin: Ordenação
     * --------------------------- */

    public static function admin_sortable_columns(array $columns): array {
        $columns['origin_key']  = 'origin_key';
        $columns['parent_tour'] = 'parent_tour';
        $columns['schema']      = 'schema';
        return $columns;
    }

    public static function admin_orderby_meta(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) return;
        if (($query->get('post_type') ?? '') !== 'vana_visit') return;

        $orderby = (string) $query->get('orderby');
        switch ($orderby) {
            case 'origin_key':
                $query->set('meta_key', '_vana_origin_key');
                $query->set('orderby', 'meta_value');
                break;

            case 'parent_tour':
                $query->set('meta_key', '_vana_parent_tour_origin_key');
                $query->set('orderby', 'meta_value');
                break;

            case 'schema':
                $query->set('meta_key', '_vana_timeline_updated_at');
                $query->set('orderby', 'meta_value');
                break;
        }
    }

    /* ---------------------------
     * Admin: Filtro por Tour pai
     * --------------------------- */

    public static function admin_filters(): void {
        global $typenow;
        if ($typenow !== 'vana_visit') return;

        $current = isset($_GET['vana_parent_tour']) ? sanitize_text_field((string) $_GET['vana_parent_tour']) : '';
        $parents = self::distinct_meta_values('_vana_parent_tour_origin_key');

        echo '<select name="vana_parent_tour">';
        echo '<option value="">' . esc_html__('Todas as Tours', 'vana') . '</option>';
        foreach ($parents as $p) {
            echo '<option value="' . esc_attr($p) . '"' . selected($current, $p, false) . '>' . esc_html($p) . '</option>';
        }
        echo '</select>';
    }

    public static function admin_apply_filters(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) return;
        if (($query->get('post_type') ?? '') !== 'vana_visit') return;

        if (!empty($_GET['vana_parent_tour'])) {
            $parent = sanitize_text_field((string) $_GET['vana_parent_tour']);
            $query->set('meta_query', [
                [
                    'key'     => '_vana_parent_tour_origin_key',
                    'value'   => $parent,
                    'compare' => '=',
                ]
            ]);
        }
    }

    private static function distinct_meta_values(string $meta_key): array {
        global $wpdb;
        $key = sanitize_text_field($meta_key);

        $sql = $wpdb->prepare(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value <> ''
             ORDER BY meta_value ASC
             LIMIT 500",
            $key
        );

        $rows = $wpdb->get_col($sql);
        return array_values(array_filter(array_map('strval', $rows)));
    }

    /* ---------------------------
     * Admin: Busca por origin_key
     * --------------------------- */

    public static function admin_search_router(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) return;
        if (($query->get('post_type') ?? '') !== 'vana_visit') return;

        $s = (string) $query->get('s');
        if ($s === '') return;

        // Heurística simples: se parece origin_key, converte busca em meta_query
        // Exemplos: "visit:xyz", "visit_", etc.
        $looks_like_origin = (strpos($s, 'visit:') !== false) || (strpos($s, 'visit_') !== false);

        if (!$looks_like_origin) return;

        // Remove busca padrão por título para focar em meta
        $query->set('s', '');

        $mq = (array) $query->get('meta_query');
        $mq[] = [
            'key'     => '_vana_origin_key',
            'value'   => $s,
            'compare' => 'LIKE',
        ];
        $query->set('meta_query', $mq);
    }
}
