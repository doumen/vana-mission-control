<?php
/**
 * Class: Vana_Tour_CPT
 * Registro do Custom Post Type: Tours
 * 
 * @package Vana_Mission_Control
 */

defined('ABSPATH') || exit;

final class Vana_Tour_CPT {

    public static function init(): void {
        add_action('init', [__CLASS__, 'register'], 10);
        add_action('init', [__CLASS__, 'register_meta'], 20);

        // Admin list table: colunas + conteúdo
        add_filter('manage_vana_tour_posts_columns',        [__CLASS__, 'admin_columns_head']);
        // legacy / alternative hook used by some WP versions/themes
        add_filter('manage_edit-vana_tour_columns',         [__CLASS__, 'admin_columns_head']);
        add_action('manage_vana_tour_posts_custom_column',  [__CLASS__, 'admin_columns_content'], 10, 2);

        // Ordenação
        add_filter('manage_edit-vana_tour_sortable_columns', [__CLASS__, 'admin_sortable_columns']);
        add_action('pre_get_posts',                           [__CLASS__, 'admin_orderby_meta']);
    }

    public static function register(): void {
        $labels = [
            'name'               => _x('Tours', 'Post type general name', 'vana-mission-control'),
            'singular_name'      => _x('Tour', 'Post type singular name', 'vana-mission-control'),
            'menu_name'          => _x('Tours', 'Admin Menu text', 'vana-mission-control'),
            'add_new'            => __('Nova Tour', 'vana-mission-control'),
            'add_new_item'       => __('Adicionar Nova Tour', 'vana-mission-control'),
            'edit_item'          => __('Editar Tour', 'vana-mission-control'),
            'view_item'          => __('Ver Tour', 'vana-mission-control'),
            'all_items'          => __('Todas as Tours', 'vana-mission-control'),
            'search_items'       => __('Buscar Tours', 'vana-mission-control'),
            'not_found'          => __('Nenhuma tour encontrada', 'vana-mission-control'),
            'not_found_in_trash' => __('Nenhuma tour na lixeira', 'vana-mission-control'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-airplane',
            'menu_position'      => 5,
            'capability_type'    => 'post',
            'has_archive'        => 'tours', // 🔥 ATIVADO!
            'rewrite'            => [
                'slug'       => 'tour',
                'with_front' => false,
            ],
            'supports'           => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
            'taxonomies'         => [],
        ];

        register_post_type('vana_tour', $args);
    }

    public static function register_meta(): void {
        $auth_cb = static function(): bool {
            return current_user_can('edit_posts');
        };

        $small_string_meta = [
            '_vana_origin_key',
        ];

        foreach ($small_string_meta as $key) {
            register_post_meta('vana_tour', $key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => $auth_cb,
                'sanitize_callback' => 'sanitize_text_field',
            ]);
        }

        // ── Metas de identidade de tour (Fase 4)
        $tour_metas = [
            '_vana_region_code'  => 'string',
            '_vana_season_code'  => 'string',
            '_vana_year_start'   => 'integer',
            '_vana_year_end'     => 'integer',
            '_vana_title_pt'     => 'string',
            '_vana_title_en'     => 'string',
        ];

        foreach ($tour_metas as $meta_key => $type) {
            $args = [
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => $type,
                'auth_callback' => $auth_cb,
            ];

            if ($type === 'string') {
                $args['sanitize_callback'] = 'sanitize_text_field';
            } elseif ($type === 'integer') {
                $args['sanitize_callback'] = 'absint';
            }

            register_post_meta('vana_tour', $meta_key, $args);
        }

    }

    /* ---------------------------
     * Admin: Colunas (lista de Tours)
     * --------------------------- */

    public static function admin_columns_head(array $columns): array {
        $new      = [];
        $inserted = false;

        foreach ($columns as $key => $title) {
            if ($key === 'date') {
                $new['region']     = __('Região', 'vana-mission-control');
                $new['year']       = __('Ano', 'vana-mission-control');
                $new['origin_key'] = __('Origin Key', 'vana-mission-control');
                $inserted = true;
            }
            $new[$key] = $title;
        }

        if (!$inserted) {
            $new['region']     = __('Região', 'vana-mission-control');
            $new['year']       = __('Ano', 'vana-mission-control');
            $new['origin_key'] = __('Origin Key', 'vana-mission-control');
        }

        return $new;
    }

    public static function admin_columns_content(string $column_name, int $post_id): void {
        if (get_post_type($post_id) !== 'vana_tour') return;
        switch ($column_name) {

            case 'region': {
                $val = strtoupper( trim((string) get_post_meta($post_id, '_vana_region_code', true)) );
                echo $val !== ''
                    ? '<code>' . esc_html($val) . '</code>'
                    : '<span aria-hidden="true">&mdash;</span>';
                break;
            }

            case 'year': {
                $start = (string) get_post_meta($post_id, '_vana_year_start', true);
                $end   = (string) get_post_meta($post_id, '_vana_year_end', true);

                if ($start === '' && $end === '') {
                    echo '<span aria-hidden="true">&mdash;</span>';
                    break;
                }

                if ($start === $end || $end === '') {
                    echo esc_html($start);
                } else {
                    echo esc_html($start . '–' . $end);
                }
                break;
            }

            case 'origin_key': {
                $val = (string) get_post_meta($post_id, '_vana_origin_key', true);
                echo $val !== ''
                    ? '<code>' . esc_html($val) . '</code>'
                    : '<span aria-hidden="true">&mdash;</span>';
                break;
            }
        }
    }

    public static function admin_sortable_columns(array $columns): array {
        $columns['region']     = 'region';
        $columns['year']       = 'year';
        $columns['origin_key'] = 'origin_key';
        return $columns;
    }

    public static function admin_orderby_meta(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) return;
        if (($query->get('post_type') ?? '') !== 'vana_tour') return;

        $orderby = (string) $query->get('orderby');
        switch ($orderby) {
            case 'region':
                $query->set('meta_key', '_vana_region_code');
                $query->set('orderby', 'meta_value');
                break;

            case 'year':
                $query->set('meta_key', '_vana_year_start');
                $query->set('orderby', 'meta_value_num');
                break;

            case 'origin_key':
                $query->set('meta_key', '_vana_origin_key');
                $query->set('orderby', 'meta_value');
                break;
        }
    }
}
