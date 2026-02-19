<?php
defined('ABSPATH') || exit;
get_header();

$paged = max(1, (int) get_query_var('paged'));
$q = new WP_Query([
    'post_type'      => 'vana_visit',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
    'meta_key'       => '_vana_start_date',
    'orderby'        => ['meta_value' => 'DESC', 'date' => 'DESC'],
]);
?>

<main class="vana-ui vana-tours-archive" style="padding: 3rem 0;">
  <div class="vana-container">
    <header class="vana-archive-header">
      <h1>Diários de Visita</h1>
      <p class="vana-text-muted">Explore a jornada cronológica da missão.</p>
    </header>

    <?php if ($q->have_posts()): ?>
      <div class="vana-grid vana-grid--tours">
        <?php while ($q->have_posts()): $q->the_post();
          $id = get_the_ID();
          $s_date = (string) get_post_meta($id, '_vana_start_date', true);
          $tz     = (string) get_post_meta($id, '_vana_tz', true);
        ?>
          <article class="vana-card">
            <a href="<?php the_permalink(); ?>" style="text-decoration:none; color:inherit;">
              <div>
                <?php if ($s_date !== ''): ?>
                  <div style="font-size:.85rem; font-weight:800; color: var(--vana-gold-deep); text-transform: uppercase;">
                    <?php echo esc_html($s_date); ?>
                    <?php if ($tz !== ''): ?> • <?php echo esc_html($tz); ?><?php endif; ?>
                  </div>
                <?php endif; ?>
                <h2 style="margin-top:10px;"><?php the_title(); ?></h2>
              </div>
            </a>
          </article>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>

      <div style="margin-top: 3rem;">
        <?php echo paginate_links(['total' => (int) $q->max_num_pages, 'current' => $paged]); ?>
      </div>
    <?php else: ?>
      <p>Nenhuma visita encontrada.</p>
    <?php endif; ?>
  </div>
</main>

<?php get_footer(); ?>