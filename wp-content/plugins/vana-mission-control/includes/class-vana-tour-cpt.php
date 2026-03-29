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

        // Admin metaboxes + save
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_vana_tour', [__CLASS__, 'save_meta_box'], 10, 2);

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

    /* ---------------------------
     * Admin: Metaboxes (edit screen)
     * --------------------------- */

    public static function add_meta_boxes(): void {
        add_meta_box(
            'vana_tour_details',
            __('Tour Details', 'vana-mission-control'),
            [__CLASS__, 'render_meta_box'],
            'vana_tour',
            'normal',
            'high'
        );
    }

    public static function render_meta_box(WP_Post $post): void {
        // Nonce
        wp_nonce_field('vana_tour_meta', 'vana_tour_meta_nonce');

        $region = esc_attr( (string) get_post_meta($post->ID, '_vana_region_code', true) );
        $season = esc_attr( (string) get_post_meta($post->ID, '_vana_season_code', true) );
        $y_start = esc_attr( (string) get_post_meta($post->ID, '_vana_year_start', true) );
        $y_end = esc_attr( (string) get_post_meta($post->ID, '_vana_year_end', true) );
        $title_pt = esc_attr( (string) get_post_meta($post->ID, '_vana_title_pt', true) );
        $title_en = esc_attr( (string) get_post_meta($post->ID, '_vana_title_en', true) );
        $origin = esc_attr( (string) get_post_meta($post->ID, '_vana_origin_key', true) );

        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="vana_region_code"><?php esc_html_e('Region Code', 'vana-mission-control'); ?></label></th>
                    <td><input name="vana_region_code" id="vana_region_code" type="text" value="<?php echo $region; ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="vana_season_code"><?php esc_html_e('Season Code', 'vana-mission-control'); ?></label></th>
                    <td><input name="vana_season_code" id="vana_season_code" type="text" value="<?php echo $season; ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="vana_year_start"><?php esc_html_e('Year Start', 'vana-mission-control'); ?></label></th>
                    <td><input name="vana_year_start" id="vana_year_start" type="number" min="0" value="<?php echo $y_start; ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="vana_year_end"><?php esc_html_e('Year End', 'vana-mission-control'); ?></label></th>
                    <td><input name="vana_year_end" id="vana_year_end" type="number" min="0" value="<?php echo $y_end; ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="vana_title_pt"><?php esc_html_e('Title (PT)', 'vana-mission-control'); ?></label></th>
                    <td><input name="vana_title_pt" id="vana_title_pt" type="text" value="<?php echo $title_pt; ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="vana_title_en"><?php esc_html_e('Title (EN)', 'vana-mission-control'); ?></label></th>
                    <td><input name="vana_title_en" id="vana_title_en" type="text" value="<?php echo $title_en; ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="vana_origin_key"><?php esc_html_e('Origin Key', 'vana-mission-control'); ?></label></th>
                    <td><input name="vana_origin_key" id="vana_origin_key" type="text" value="<?php echo $origin; ?>" class="regular-text" readonly /></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public static function save_meta_box(int $post_id, WP_Post $post): void {
        // Verify nonce
        if (empty($_POST['vana_tour_meta_nonce']) || !wp_verify_nonce($_POST['vana_tour_meta_nonce'], 'vana_tour_meta')) {
            return;
        }
        // Autosave / permissions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Sanitize and save
        if (isset($_POST['vana_region_code'])) {
            update_post_meta($post_id, '_vana_region_code', sanitize_text_field((string) $_POST['vana_region_code']));
        }
        if (isset($_POST['vana_season_code'])) {
            update_post_meta($post_id, '_vana_season_code', sanitize_text_field((string) $_POST['vana_season_code']));
        }
        if (isset($_POST['vana_year_start'])) {
            update_post_meta($post_id, '_vana_year_start', absint($_POST['vana_year_start']));
        }
        if (isset($_POST['vana_year_end'])) {
            update_post_meta($post_id, '_vana_year_end', absint($_POST['vana_year_end']));
        }
        if (isset($_POST['vana_title_pt'])) {
            update_post_meta($post_id, '_vana_title_pt', sanitize_text_field((string) $_POST['vana_title_pt']));
        }
        if (isset($_POST['vana_title_en'])) {
            update_post_meta($post_id, '_vana_title_en', sanitize_text_field((string) $_POST['vana_title_en']));
        }
        // Origin key is readonly in the UI but allow explicit save (if provided)
        if (isset($_POST['vana_origin_key'])) {
            update_post_meta($post_id, '_vana_origin_key', sanitize_text_field((string) $_POST['vana_origin_key']));
        }
    }
}
