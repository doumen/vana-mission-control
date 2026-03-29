<?php
defined('ABSPATH') || exit;

final class Vana_HK_Passage_CPT {

    public static function init(): void {
        add_action('init', [__CLASS__, 'register'], 10);
        add_action('init', [__CLASS__, 'register_meta'], 20);
        add_action('init', [__CLASS__, 'register_taxonomies'], 30);
    }

    public static function register(): void {
        $labels = [
            'name'               => __('Passages', 'vana-mission-control'),
            'singular_name'      => __('Passage', 'vana-mission-control'),
            'menu_name'          => __('Passages', 'vana-mission-control'),
            'add_new'            => __('Novo Passage', 'vana-mission-control'),
            'add_new_item'       => __('Adicionar Novo Passage', 'vana-mission-control'),
            'edit_item'          => __('Editar Passage', 'vana-mission-control'),
            'view_item'          => __('Ver Passage', 'vana-mission-control'),
            'all_items'          => __('Todos os Passages', 'vana-mission-control'),
            'search_items'       => __('Buscar Passages', 'vana-mission-control'),
            'not_found'          => __('Nenhum passage encontrado', 'vana-mission-control'),
            'not_found_in_trash' => __('Nenhum passage na lixeira', 'vana-mission-control'),
        ];

        register_post_type('hk_passage', [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-media-text',
            'supports'            => ['title', 'editor', 'excerpt', 'custom-fields'],
            'has_archive'         => false,
            'hierarchical'        => false,
            'rewrite'             => [
                'slug'       => 'passage',
                'with_front' => false,
            ],
        ]);
    }

    public static function register_meta(): void {
    
        $auth_cb = static function (): bool {
            return current_user_can( 'edit_posts' );
        };
    
        $string_meta = [
    
            // Identificação e vínculo
            '_hk_passage_ref',       // passage_ref editorial
            '_hk_katha_ref',         // katha_ref do pai
            '_hk_visit_ref',         // herança
            '_hk_day_key',           // herança
            '_hk_vod_ref',           // herança (remover no MVP se desnecessário)
    
            // Temporal
            '_hk_t_start',           // t_start (HH:MM:SS)
            '_hk_t_end',             // t_end
            '_hk_timestamp',         // display "00:12:34"
    
            // Classificação
            '_hk_passage_kind',      // narrative|instruction|verse_commentary|...
            '_hk_passage_type',      // intro|body|climax|conclusion|aside
            '_hk_primary_type',      // sambandha|lila|niti etc (campo "type" do JSON)
            '_hk_audience',          // mumuksu|sadhaka|advanced|general|children
    
            // Conteúdo PT
            '_hk_hook_pt',
            '_hk_key_quote_pt',
            '_hk_content_pt',
    
            // Conteúdo EN
            '_hk_hook_en',
            '_hk_key_quote_en',
            '_hk_content_en',
    
            // Mídia
            '_hk_media_potential',   // none|low|medium|high
    
            // Revisão
            '_hk_review_status',     // draft|pending|approved
            '_hk_review_flags_json', // array JSON
            '_hk_notes_json',        // array JSON
            '_hk_title_pt',
            '_hk_title_en',
            '_hk_source_segment_id',
            '_hk_source_vod_key',
        ];
    
        foreach ( $string_meta as $key ) {
            register_post_meta( 'hk_passage', $key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => $auth_cb,
                'sanitize_callback' => 'sanitize_text_field',
            ] );
        }
    
        // ── Integer ───────────────────────────────────────────────────
        $integer_meta = [
            '_hk_index',
            '_hk_katha_id',
            '_hk_visit_id',
            '_hk_ts_start',
            '_hk_ts_end',
        ];
    
        foreach ( $integer_meta as $key ) {
            register_post_meta( 'hk_passage', $key, [
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => $auth_cb,
                'sanitize_callback' => 'absint',
            ] );
        }
    
        // ── Boolean ───────────────────────────────────────────────────
        $boolean_meta = [
            '_hk_reel_worthy',
            '_hk_contains_confidential_content',
        ];
    
        foreach ( $boolean_meta as $key ) {
            register_post_meta( 'hk_passage', $key, [
                'type'          => 'boolean',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => $auth_cb,
            ] );
        }
    }

    public static function register_taxonomies(): void {
        $taxes = [
            'hk_topic'  => 'Tópicos',
            'hk_person' => 'Pessoas',
            'hk_place'  => 'Lugares',
            'hk_event'  => 'Eventos',
        ];

        foreach ($taxes as $taxonomy => $label) {
            register_taxonomy($taxonomy, ['hk_passage'], [
                'label'             => $label,
                'public'            => true,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => ['slug' => $taxonomy],
            ]);
        }
    }
}
