<?php
defined('ABSPATH') || exit;

final class Vana_Submission_CPT {

    public static function init(): void {
        add_action('init', [__CLASS__, 'register']);
        add_action('init', [__CLASS__, 'register_meta']);

        add_filter('manage_vana_submission_posts_columns', [__CLASS__, 'admin_columns']);
        add_action('manage_vana_submission_posts_custom_column', [__CLASS__, 'admin_column_content'], 10, 2);
    }

    public static function register(): void {
        $labels = [
            'name'          => 'Oferendas',
            'singular_name' => 'Oferenda',
            'menu_name'     => 'Oferendas',
            'all_items'     => 'Todas as Oferendas',
            'add_new_item'  => 'Adicionar Oferenda',
            'edit_item'     => 'Editar Oferenda',
        ];

        register_post_type('vana_submission', [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 22,
            'menu_icon'           => 'dashicons-heart',
            'supports'            => ['title'],
            'has_archive'         => false,
            'show_in_rest'        => false,
            'capability_type'     => 'post',
        ]);
    }

    public static function register_meta(): void {
        $meta = [
            '_visit_id'             => ['type' => 'integer', 'single' => true, 'sanitize_callback' => 'absint'],
            '_sender_display_name'  => ['type' => 'string',  'single' => true, 'sanitize_callback' => 'sanitize_text_field'],
            '_message'              => ['type' => 'string',  'single' => true, 'sanitize_callback' => 'sanitize_textarea_field'],
            '_image_url'            => ['type' => 'string',  'single' => true, 'sanitize_callback' => 'esc_url_raw'],
            '_external_url'         => ['type' => 'string',  'single' => true, 'sanitize_callback' => 'esc_url_raw'],
            '_submitted_at'         => ['type' => 'integer', 'single' => true, 'sanitize_callback' => 'absint'],
            '_consent_publish'      => ['type' => 'integer', 'single' => true, 'sanitize_callback' => 'absint'],
        ];

        foreach ($meta as $key => $args) {
            register_post_meta('vana_submission', $key, array_merge($args, [
                'show_in_rest' => false,
                'auth_callback' => '__return_true',
            ]));
        }
    }

    public static function admin_columns($columns): array {
        return [
            'cb'       => $columns['cb'] ?? 'cb',
            'title'    => 'Nome do Devoto',
            'visit_id' => 'Visita',
            'media'    => 'Mídia',
            'status'   => 'Status',
            'date'     => $columns['date'] ?? 'date',
        ];
    }

    public static function admin_column_content($column, $post_id): void {
        if ($column === 'visit_id') {
            $vid = (int) get_post_meta($post_id, '_visit_id', true);
            if ($vid) {
                echo '<a href="'.esc_url(get_edit_post_link($vid)).'">'.esc_html(get_the_title($vid)).'</a>';
            } else {
                echo '—';
            }
            return;
        }

        if ($column === 'media') {
            $img = (string) get_post_meta($post_id, '_image_url', true);
            $ext = (string) get_post_meta($post_id, '_external_url', true);
            if ($img) echo '<span class="dashicons dashicons-format-image" title="Foto"></span> ';
            if ($ext) echo '<span class="dashicons dashicons-video-alt3" title="Vídeo/Link"></span> ';
            return;
        }

        if ($column === 'status') {
            $status = get_post_status($post_id);
            if ($status === 'pending') {
                echo '<span style="background:#d97706;color:#fff;padding:3px 8px;border-radius:6px;font-weight:700;">Aguardando</span>';
            } elseif ($status === 'publish') {
                echo '<span style="background:#15803d;color:#fff;padding:3px 8px;border-radius:6px;font-weight:700;">Aprovado</span>';
            } else {
                echo esc_html($status);
            }
            return;
        }
    }
}