<?php
/**
 * Tour Drawer — Gaveta deslizante para seleção de tours
 * Template Part: templates/visit/parts/tour-drawer.php
 *
 * Cenários (conforme spec v1 seção 8):
 *   Cenário A: $tour_id existe → lista de visitas da tour
 *   Cenário B: $tour_id === null → lista cronológica global de tours
 *
 * Variáveis consumidas:
 *   $tour_id    int|null   — ID da tour (resolvido em _bootstrap.php)
 *   $lang       string     — 'pt' | 'en'
 *
 * JS Controller: inline em templates/visit/assets/visit-scripts.php
 *    (Este arquivo contém a implementação da gaveta: open/close, fetch AJAX,
 *     render das listas e APIs públicas. Atualize a referência apenas se a
 *     implementação for extraída para um controller externo e `window.vanaDrawer`
 *     for preservado.)
 * Seletores críticos (manter em sincronismo):
 *   #vana-tour-drawer       → container principal (role=dialog)
 *   #vana-drawer-tour-list  → <ul> com tours
 *   #vana-drawer-visit-list → <ul> com visitas (quando $tour_id)
 *   #vana-drawer-overlay    → backdrop overlay
 *   #vana-drawer-back       → botão "voltar" (tour → visitas)
 *   .vana-drawer__close     → botão fechar
 */
defined('ABSPATH') || exit;
?>

<!-- ════════════════════════════════════════════════════════
     TOUR DRAWER
     ════════════════════════════════════════════════════════ -->
<div
    id="vana-tour-drawer"
    class="vana-drawer vana-drawer--tour"
    role="dialog"
    aria-modal="true"
    aria-label="<?php echo esc_attr(vana_t('hero.tours', $lang)); ?>"
    hidden
>
    <div class="vana-drawer__header">
        <button class="vana-drawer__back" id="vana-drawer-back" hidden aria-label="Voltar">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
        <span class="vana-drawer__header-title" id="vana-drawer-title">
            <?php echo esc_html(vana_t('hero.tours', $lang)); ?>
        </span>
        <button class="vana-drawer__close" aria-label="Fechar">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M1 1L13 13M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div class="vana-drawer__body" id="vana-drawer-body">
        <div class="vana-drawer__loading" id="vana-drawer-loading">
            <span class="vana-drawer__spinner"></span>
        </div>
        <ul class="vana-drawer__tour-list" id="vana-drawer-tour-list" role="list" hidden></ul>
    </div>

    <div class="vana-drawer__body vana-drawer__body--visits" id="vana-drawer-visits" hidden>
        <div class="vana-drawer__loading" id="vana-drawer-visits-loading">
            <span class="vana-drawer__spinner"></span>
        </div>
        <ul class="vana-drawer__visit-list" id="vana-drawer-visit-list" role="list" hidden></ul>
    </div>
</div>

<div class="vana-drawer__overlay" id="vana-drawer-overlay" hidden></div>
