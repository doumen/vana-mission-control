<?php
/**
 * Template: Archive Tours
 * 
 * @package Vana_Mission_Control
 * @version 2.0.0
 * 
 * Nota: Este template SÓ FUNCIONA se 'has_archive' => true no registro do CPT.
 * Se você está usando uma Página (ID 315) com shortcode [lista_tours], 
 * este arquivo NÃO SERÁ CARREGADO pelo WordPress.
 * 
 * Changelog:
 * - Query otimizada (evita múltiplos get_post_meta)
 * - Internacionalização completa
 * - Empty state para quando não há tours
 * - Estrutura semântica (SEO)
 */

get_header();

// ========== QUERY OTIMIZADA ==========

// 1. Busca a Tour Atual (Destaque)
$current_query = new WP_Query([
    'post_type'      => 'vana_tour',
    'posts_per_page' => 1,
    'meta_query'     => [
        ['key' => '_tour_is_current', 'value' => '1']
    ],
    'post_status'    => 'publish',
    'no_found_rows'  => true, // Performance: não precisa contar total
]);

// IDs para excluir da próxima query
$exclude_ids = [];
if ($current_query->have_posts()) {
    $exclude_ids[] = $current_query->posts[0]->ID;
}

// 2. Busca as Outras Tours (Histórico/Futuro)
$others_query = new WP_Query([
    'post_type'      => 'vana_tour',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'post__not_in'   => $exclude_ids,
    'post_status'    => 'publish',
]);
?>

<main id="primary" class="site-main">
    <div class="vana-ui vana-tours-archive">
        <div class="vana-container">

            <!-- Header do Archive -->
            <header class="vana-archive-header">
                <h1>🌍 <?php esc_html_e('Tours de Guru-Seva', 'vana-mission-control'); ?></h1>
                <p class="vana-text-muted">
                    <?php esc_html_e('Acompanhe as viagens de pregação de Śrīla Gurudeva pelo mundo', 'vana-mission-control'); ?>
                </p>
            </header>

            <!-- Seção: Tour Atual (Destaque) -->
            <?php if ($current_query->have_posts()): ?>
                <?php while ($current_query->have_posts()): $current_query->the_post(); ?>
                    <section class="vana-current-tour-section">
                        <div class="vana-card vana-card--gold vana-card--featured">
                            
                            <div class="vana-card__badge">
                                <span class="vana-badge vana-badge--current">
                                    ✨ <?php esc_html_e('Acontecendo Agora', 'vana-mission-control'); ?>
                                </span>
                            </div>
                            
                            <div class="vana-card__content">
                                <h2>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <?php $dates = get_post_meta(get_the_ID(), '_tour_dates_label', true); ?>
                                <?php if ($dates): ?>
                                    <p class="vana-tour-dates">
                                        📅 <?php echo esc_html($dates); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (has_excerpt()): ?>
                                    <div class="vana-tour-excerpt">
                                        <?php echo wp_kses_post(get_the_excerpt()); ?>
                                    </div>
                                <?php endif; ?>

                                <a href="<?php the_permalink(); ?>" class="vana-btn vana-btn--primary">
                                    <?php esc_html_e('Acompanhar Tour', 'vana-mission-control'); ?> →
                                </a>
                            </div>
                        </div>
                    </section>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            <?php endif; ?>

            <!-- Seção: Outras Tours -->
            <?php if ($others_query->have_posts()): ?>
                <section class="vana-other-tours-section">
                    <h2 class="vana-section-title">
                        🗓️ <?php esc_html_e('Histórico de Viagens', 'vana-mission-control'); ?>
                    </h2>
                    
                    <div class="vana-grid vana-grid--tours">
                        <?php while ($others_query->have_posts()): $others_query->the_post(); ?>
                            <article class="vana-card vana-card--mini">
                                
                                <?php if (has_post_thumbnail()): ?>
                                    <a href="<?php the_permalink(); ?>" class="vana-card__thumb">
                                        <?php the_post_thumbnail('medium', ['loading' => 'lazy']); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="vana-card__body">
                                    <h3>
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_title(); ?>
                                        </a>
                                    </h3>

                                    <?php $dates = get_post_meta(get_the_ID(), '_tour_dates_label', true); ?>
                                    <?php if ($dates): ?>
                                        <p class="vana-text-muted vana-text-sm">
                                            📅 <?php echo esc_html($dates); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>
                <?php wp_reset_postdata(); ?>
            
            <!-- Empty State: Nenhuma Tour Encontrada -->
            <?php elseif (empty($exclude_ids)): ?>
                <div class="vana-empty-state" style="text-align: center; padding: 80px 20px;">
                    <p style="font-size: 4rem; margin: 0;">🌍</p>
                    <h2><?php esc_html_e('Nenhuma tour cadastrada', 'vana-mission-control'); ?></h2>
                    <p class="vana-text-muted">
                        <?php esc_html_e('Em breve você poderá acompanhar as viagens de Śrīla Gurudeva aqui.', 'vana-mission-control'); ?>
                    </p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php get_footer(); ?>
