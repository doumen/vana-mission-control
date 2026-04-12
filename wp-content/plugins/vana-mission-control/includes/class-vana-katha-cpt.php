<?php
defined('ABSPATH') || exit;

final class Vana_Katha_CPT {

    public static function init(): void {
        add_action('init', [__CLASS__, 'register'], 10);
        add_action('init', [__CLASS__, 'register_meta'], 20);
    }

    public static function register(): void {
        $labels = [
            'name'               => __('Hari-kathās', 'vana-mission-control'),
            'singular_name'      => __('Hari-kathā', 'vana-mission-control'),
            'menu_name'          => __('Hari-kathā', 'vana-mission-control'),
            'add_new'            => __('Nova Hari-kathā', 'vana-mission-control'),
            'add_new_item'       => __('Adicionar Nova Hari-kathā', 'vana-mission-control'),
            'edit_item'          => __('Editar Hari-kathā', 'vana-mission-control'),
            'view_item'          => __('Ver Hari-kathā', 'vana-mission-control'),
            'all_items'          => __('Todas as Hari-kathās', 'vana-mission-control'),
            'search_items'       => __('Buscar Hari-kathās', 'vana-mission-control'),
            'not_found'          => __('Nenhuma Hari-kathā encontrada', 'vana-mission-control'),
            'not_found_in_trash' => __('Nenhuma Hari-kathā na lixeira', 'vana-mission-control'),
        ];

        register_post_type('vana_katha', [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-book-alt',
            'supports'            => ['title', 'editor', 'excerpt', 'custom-fields'],
            'has_archive'         => false,
            'rewrite'             => [
                'slug'       => 'katha',
                'with_front' => false,
            ],
        ]);
    }

    public static function register_meta(): void {
    
        $auth_cb = static function (): bool {
            return current_user_can( 'edit_posts' );
        };
    
        // ── Metas string completas ────────────────────────────────────
        $string_meta = [
    
            // Contexto canônico
            '_vana_katha_katha_ref',          // context.katha_ref
            '_vana_katha_visit_ref',          // context.visit_ref
            '_vana_katha_day_key',            // context.day_key  (YYYY-MM-DD)
            '_vana_katha_event_key',          // context.event_key (opcional)
            '_vana_katha_source_url',         // context.source_url
            '_vana_katha_source_platform',    // context.source_platform
            '_vana_katha_clip_start',         // context.clip_start
            '_vana_katha_clip_end',           // context.clip_end
            '_vana_katha_vod_ref',            // context.vod_id (string/slug)
            '_vana_katha_vod_platform',       // context.vod_platform
            '_vana_katha_vod_url',            // context.vod_url
    
            // Conteúdo lecture
            '_vana_katha_title_pt',
            '_vana_katha_title_en',
            '_vana_katha_excerpt_pt',
            '_vana_katha_excerpt_en',
            '_vana_katha_summary_pt',
            '_vana_katha_summary_en',
            '_vana_katha_source_language',    // lecture.source_language
            '_vana_katha_event_date',         // lecture.event_date (YYYY-MM-DD)
            '_vana_katha_location',           // lecture.location
            '_vana_katha_duration',           // lecture.duration (HH:MM:SS)
            '_vana_katha_media_potential',    // none|low|medium|high
            '_vana_katha_period',             // morning|midday|night|other
            '_vana_katha_t_start',
            '_vana_katha_t_end',
    
            // Editorial
            '_vana_katha_review_status',      // draft|pending|approved
            '_vana_katha_schema_version',     // ex: "3.2"
    
            // JSON blobs
            '_vana_katha_output_languages_json',   // ["pt","en"]
            '_vana_katha_lecture_taxonomies_json', // objeto taxonomias
            '_vana_katha_verses_json',             // verses_cited[]
            '_vana_katha_glossary_json',           // glossary[]
            '_vana_katha_notes_json',              // notes[]
            '_vana_katha_sources_json',
        ];
    
        foreach ( $string_meta as $key ) {
            register_post_meta( 'vana_katha', $key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => $auth_cb,
                'sanitize_callback' => 'sanitize_text_field',
            ] );
        }
    
        // ── Metas integer ─────────────────────────────────────────────
        register_post_meta( 'vana_katha', '_vana_katha_visit_id', [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => $auth_cb,
            'sanitize_callback' => 'absint',
        ] );
    
        // ── Metas boolean ─────────────────────────────────────────────
        register_post_meta( 'vana_katha', '_vana_katha_public_notice', [
            'type'          => 'boolean',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => $auth_cb,
        ] );
    }
}
