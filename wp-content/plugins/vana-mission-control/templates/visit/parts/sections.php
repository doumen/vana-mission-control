<?php
/**
 * Sections Panel — Unificação de Hari-Katha, Galeria, Sangha, Revista
 * Template Part: templates/visit/parts/sections.php
 *
 * Seção 12 do spec v1:
 * - 4 painéis principais: HK | Galeria | Sangha | Revista
 * - Cada seção com ID único
 * - Estado .is-active controlado por chip-bar (anchor-chips.php)
 *
 * Variáveis consumidas:
 *   $data       array  — timeline JSON
 *   $lang       string — 'pt' | 'en'
 *   $visit_id   int    — ID da visita
 *   $active_day array  — dia ativo (para HK, gallery, sangha)
 *
 * Seletores críticos (alinhados com anchor-chips.php):
 *   #vana-sections          → wrapper principal
 *   .vana-section-panel     → cada painel (role=tabpanel)
 *   .vana-section-panel.is-active → painel visível
 *   data-section-id         → identificador único
 */
defined('ABSPATH') || exit;

// Resolver dados das seções a partir do $active_day
$hk_items      = $active_day['hari_katha'] ?? [];
$gallery_items = $active_day['gallery'] ?? [];
$sangha_items  = $active_day['sangha_moments'] ?? [];
// revista é por visit_id, não por day — ficará vazia até JS carregar
?>

<!-- ════════════════════════════════════════════════════════
     SECTIONS PANEL — Tabs de conteúdo
     ════════════════════════════════════════════════════════ -->
<div id="vana-sections" class="vana-sections" role="tablist" aria-label="Seções de conteúdo">

    <!-- Section 1: Hari-Katha -->
    <section
        id="vana-section-hk"
        class="vana-section-panel"
        data-vana-section="vana-section-hk"
        data-section-id="section-hk"
        role="tabpanel"
        aria-labelledby="vana-chip-hk"
    >
        <div class="vana-section-header">
            <h3 class="vana-section-title">🙏 <?php echo esc_html( vana_t( 'sections.hari_katha', $lang ) ?: 'Hari-Katha' ); ?></h3>
        </div>
        <div class="vana-section-body">
            <?php if (!empty($hk_items)): ?>
                <ul class="vana-section-list">
                    <?php foreach ($hk_items as $item): ?>
                        <?php if (!is_array($item)) continue; ?>
                        <li class="vana-section-item">
                            <h4 class="vana-section-item-title">
                                <?php echo esc_html( $item['title_' . $lang] ?? $item['title_pt'] ?? $item['title'] ?? '' ); ?>
                            </h4>
                            <?php if (!empty($item['excerpt_' . $lang] ?? $item['excerpt_pt'] ?? '')): ?>
                                <p class="vana-section-item-excerpt">
                                    <?php echo esc_html( $item['excerpt_' . $lang] ?? $item['excerpt_pt'] ?? '' ); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($item['url'])): ?>
                                <a href="<?php echo esc_url($item['url']); ?>" class="vana-section-item-link">
                                    <?php echo esc_html( vana_t( 'sections.read_more', $lang ) ?: 'Leia mais' ); ?> →
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="vana-section-empty"><?php echo esc_html( vana_t( 'sections.empty', $lang ) ?: 'Sem hari-katha para este dia' ); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section 2: Galeria -->
    <section
        id="vana-section-gallery"
        class="vana-section-panel"
        data-vana-section="vana-section-gallery"
        data-section-id="section-gallery"
        role="tabpanel"
        aria-labelledby="vana-chip-gallery"
    >
        <div class="vana-section-header">
            <h3 class="vana-section-title">📷 <?php echo esc_html( vana_t( 'sections.gallery', $lang ) ?: 'Galeria' ); ?></h3>
        </div>
        <div class="vana-section-body">
            <?php if (!empty($gallery_items)): ?>
                <div class="vana-gallery-grid">
                    <?php foreach ($gallery_items as $photo): ?>
                        <?php if (!is_array($photo)) continue; ?>
                        <figure class="vana-gallery-item">
                            <img
                                src="<?php echo esc_url( $photo['thumb_url'] ?? $photo['url'] ?? '' ); ?>"
                                alt="<?php echo esc_attr( $photo['caption'] ?? '' ); ?>"
                                loading="lazy"
                                class="vana-gallery-img"
                            />
                            <?php if (!empty($photo['caption'])): ?>
                                <figcaption class="vana-gallery-caption">
                                    <?php echo esc_html( $photo['caption'] ); ?>
                                </figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="vana-section-empty"><?php echo esc_html( vana_t( 'sections.empty', $lang ) ?: 'Sem fotos para este dia' ); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section 3: Sangha Moments -->
    <section
        id="vana-section-sangha"
        class="vana-section-panel"
        data-vana-section="vana-section-sangha"
        data-section-id="section-sangha"
        role="tabpanel"
        aria-labelledby="vana-chip-sangha"
    >
        <div class="vana-section-header">
            <h3 class="vana-section-title">💬 <?php echo esc_html( vana_t( 'sections.sangha', $lang ) ?: 'Sangha' ); ?></h3>
        </div>
        <div class="vana-section-body">
            <?php if (!empty($sangha_items)): ?>
                <ul class="vana-section-list">
                    <?php foreach ($sangha_items as $moment): ?>
                        <?php if (!is_array($moment)) continue; ?>
                        <li class="vana-section-item vana-sangha-moment">
                            <blockquote class="vana-sangha-quote">
                                <?php echo nl2br( esc_html( $moment['text'] ?? '' ) ); ?>
                            </blockquote>
                            <cite class="vana-sangha-author">
                                — <?php echo esc_html( $moment['author'] ?? '' ); ?>
                            </cite>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="vana-section-empty"><?php echo esc_html( vana_t( 'sections.empty', $lang ) ?: 'Sem relatos para este dia' ); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section 4: Revista -->
    <section
        id="vana-section-revista"
        class="vana-section-panel"
        data-vana-section="vana-section-revista"
        data-section-id="section-revista"
        role="tabpanel"
        aria-labelledby="vana-chip-revista"
    >
        <div class="vana-section-header">
            <h3 class="vana-section-title">📰 <?php echo esc_html( vana_t( 'sections.revista', $lang ) ?: 'Revista' ); ?></h3>
        </div>
        <div class="vana-section-body" id="vana-revista-content">
            <p class="vana-section-empty"><?php echo esc_html( vana_t( 'sections.revista_loading', $lang ) ?: 'Carregando revista...' ); ?></p>
        </div>
    </section>

</div>
