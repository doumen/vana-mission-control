<?php
/**
 * Partial: Hero Badges
 * Exibe os badges de região, período, live e novo.
 *
 * Variáveis herdadas do hero-header.php:
 *   $tour  (array)   → dados do tour
 *   $lang  (string)  → 'pt' | 'en'
 *
 * Regras de exibição (conforme CONTRATO.md § 7):
 *   badge.region → exibe se region_code preenchido
 *   badge.season → exibe se season_code preenchido
 *   badge.live   → exibe se $tour['has_live'] === true
 *   badge.new    → exibe se $tour['is_new'] === true
 *                  E a visita tem menos de 30 dias
 */
if (!defined('ABSPATH')) exit;

// ─── Extração de dados ────────────────────────────────────────────────────────
$region_code = strtoupper(trim((string) ($tour['region_code'] ?? '')));
$season_code = strtoupper(trim((string) ($tour['season_code'] ?? '')));
$has_live    = !empty($tour['has_live']) && $tour['has_live'] === true;
$is_new_flag = !empty($tour['is_new'])   && $tour['is_new']  === true;

// ─── Regra dos 30 dias para badge "Novo" ─────────────────────────────────────
$is_new = false;
if ($is_new_flag && isset($tour['created_at'])) {
    $created = strtotime((string) $tour['created_at']);
    if ($created !== false) {
        $age_days = (time() - $created) / DAY_IN_SECONDS;
        $is_new   = ($age_days <= 30);
    }
}

// ─── Verifica se há pelo menos 1 badge para renderizar o wrapper ──────────────
$has_any_badge = (
    $region_code !== '' ||
    $season_code !== '' ||
    $has_live          ||
    $is_new
);

if (!$has_any_badge) return;
?>

<div
    class="vana-hero__badges"
    role="list"
    aria-label="<?php echo esc_attr(Vana_Utils::t('aria.badge_region', $lang)); ?>"
>

    <?php // ── Badge: Região ──────────────────────────────────────────────── ?>
    <?php if ($region_code !== '') :
        $region_key   = 'badge.region.' . $region_code;
        $region_label = Vana_Utils::t($region_key, $lang);
        // Se a chave não existe no dicionário, t() retorna a própria chave
        // Nesse caso, exibimos o código bruto como fallback
        if ($region_label === $region_key) $region_label = $region_code;
    ?>
    <span
        class="vana-badge vana-badge--region"
        role="listitem"
        aria-label="<?php echo esc_attr(Vana_Utils::t('aria.badge_region', $lang)); ?>"
        data-code="<?php echo esc_attr($region_code); ?>"
    >
        <span class="vana-badge__icon" aria-hidden="true">🌍</span>
        <span class="vana-badge__label"><?php echo esc_html($region_label); ?></span>
    </span>
    <?php endif; ?>

    <?php // ── Badge: Período ─────────────────────────────────────────────── ?>
    <?php if ($season_code !== '') :
        $season_key   = 'badge.season.' . $season_code;
        $season_label = Vana_Utils::t($season_key, $lang);
        if ($season_label === $season_key) $season_label = $season_code;
    ?>
    <span
        class="vana-badge vana-badge--season"
        role="listitem"
        aria-label="<?php echo esc_attr(Vana_Utils::t('aria.badge_season', $lang)); ?>"
        data-code="<?php echo esc_attr($season_code); ?>"
    >
        <span class="vana-badge__icon" aria-hidden="true">🗓️</span>
        <span class="vana-badge__label"><?php echo esc_html($season_label); ?></span>
    </span>
    <?php endif; ?>

    <?php // ── Badge: Live ────────────────────────────────────────────────── ?>
    <?php if ($has_live) : ?>
    <span
        class="vana-badge vana-badge--live"
        role="listitem"
        aria-label="<?php echo esc_attr(Vana_Utils::t('aria.badge_live', $lang)); ?>"
    >
        <span class="vana-badge__dot" aria-hidden="true"></span>
        <span class="vana-badge__label"><?php echo esc_html(Vana_Utils::t('badge.live', $lang)); ?></span>
    </span>
    <?php endif; ?>

    <?php // ── Badge: Novo ────────────────────────────────────────────────── ?>
    <?php if ($is_new) : ?>
    <span
        class="vana-badge vana-badge--new"
        role="listitem"
        aria-label="<?php echo esc_attr(Vana_Utils::t('aria.badge_new', $lang)); ?>"
    >
        <span class="vana-badge__icon" aria-hidden="true">✨</span>
        <span class="vana-badge__label"><?php echo esc_html(Vana_Utils::t('badge.new', $lang)); ?></span>
    </span>
    <?php endif; ?>

</div><!-- /.vana-hero__badges -->
