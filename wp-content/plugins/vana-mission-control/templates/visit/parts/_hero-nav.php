<?php
/**
 * Partial: Hero Navigation
 * Navegação entre visitas (anterior / próxima).
 *
 * Variáveis herdadas do hero-header.php:
 *   $tour  (array)   → dados do tour atual
 *   $lang  (string)  → 'pt' | 'en'
 *
 * Estrutura esperada em $tour:
 *   $tour['nav']['prev'] → [ 'id', 'title_pt', 'title_en', 'url' ]
 *   $tour['nav']['next'] → [ 'id', 'title_pt', 'title_en', 'url' ]
 *
 * Regras (conforme CONTRATO.md § 9):
 *   - Se não há prev E não há next → não renderiza nada
 *   - URL deve ser https → validada via Vana_Utils::safe_https_url()
 *   - Título ausente → usa string genérica do dicionário
 */
if (!defined('ABSPATH')) exit;

// ─── Extração e validação ─────────────────────────────────────────────────────
$nav  = isset($tour['nav']) && is_array($tour['nav']) ? $tour['nav'] : [];
$prev = isset($nav['prev']) && is_array($nav['prev']) ? $nav['prev'] : [];
$next = isset($nav['next']) && is_array($nav['next']) ? $nav['next'] : [];

// URLs seguras
$prev_url = isset($prev['url']) ? Vana_Utils::safe_https_url((string) $prev['url']) : '';
$next_url = isset($next['url']) ? Vana_Utils::safe_https_url((string) $next['url']) : '';

// Nada a renderizar
if ($prev_url === '' && $next_url === '') return;

// ─── Títulos ──────────────────────────────────────────────────────────────────
$prev_title = ($prev !== [])
    ? Vana_Utils::pick_i18n_key($prev, 'title', $lang)
    : '';
$next_title = ($next !== [])
    ? Vana_Utils::pick_i18n_key($next, 'title', $lang)
    : '';

// Fallback para strings genéricas
if ($prev_title === '') $prev_title = Vana_Utils::t('day.prev', $lang);
if ($next_title === '') $next_title = Vana_Utils::t('day.next', $lang);
?>

<nav
    class="vana-hero__nav"
    aria-label="<?php echo esc_attr(Vana_Utils::t('aria.day_selector', $lang)); ?>"
>

    <?php // ── Anterior ──────────────────────────────────────────────────── ?>
    <?php if ($prev_url !== '') : ?>
    <a
        href="<?php echo esc_url($prev_url); ?>"
        class="vana-hero__nav-link vana-hero__nav-link--prev"
        rel="prev"
        aria-label="<?php echo esc_attr(Vana_Utils::t('aria.nav_prev', $lang)); ?>"
        data-tour-id="<?php echo esc_attr((string) ($prev['id'] ?? '')); ?>"
    >
        <span class="vana-hero__nav-arrow" aria-hidden="true">←</span>
        <span class="vana-hero__nav-meta">
            <span class="vana-hero__nav-hint">
                <?php echo esc_html(Vana_Utils::t('day.prev', $lang)); ?>
            </span>
            <span class="vana-hero__nav-title">
                <?php echo esc_html($prev_title); ?>
            </span>
        </span>
    </a>
    <?php else : ?>
    <span class="vana-hero__nav-link vana-hero__nav-link--prev vana-hero__nav-link--disabled" aria-hidden="true"></span>
    <?php endif; ?>

    <?php // ── Próxima ───────────────────────────────────────────────────── ?>
    <?php if ($next_url !== '') : ?>
    <a
        href="<?php echo esc_url($next_url); ?>"
        class="vana-hero__nav-link vana-hero__nav-link--next"
        rel="next"
        aria-label="<?php echo esc_attr(Vana_Utils::t('aria.nav_next', $lang)); ?>"
        data-tour-id="<?php echo esc_attr((string) ($next['id'] ?? '')); ?>"
    >
        <span class="vana-hero__nav-meta">
            <span class="vana-hero__nav-hint">
                <?php echo esc_html(Vana_Utils::t('day.next', $lang)); ?>
            </span>
            <span class="vana-hero__nav-title">
                <?php echo esc_html($next_title); ?>
            </span>
        </span>
        <span class="vana-hero__nav-arrow" aria-hidden="true">→</span>
    </a>
    <?php else : ?>
    <span class="vana-hero__nav-link vana-hero__nav-link--next vana-hero__nav-link--disabled" aria-hidden="true"></span>
    <?php endif; ?>

</nav><!-- /.vana-hero__nav -->
