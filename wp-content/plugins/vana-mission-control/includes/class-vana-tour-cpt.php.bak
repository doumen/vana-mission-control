<?php
/**
 * Class: Vana_Tour_CPT
 * Registro do Custom Post Type: Tours
 * 
 * @package Vana_Mission_Control
 */

defined('ABSPATH') || exit;

class Vana_Tour_CPT {
    
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
}
