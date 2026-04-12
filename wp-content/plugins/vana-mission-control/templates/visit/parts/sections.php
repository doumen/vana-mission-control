<?php
/**
 * Sections / Zona Mutável — v6.2
 * 
 * NOTA: O container #vana-mutable-zone agora é renderizado pelo
 * visit-template.php (PR-1, Schema 6.1). Este partial fornece
 * APENAS os painéis internos, que são injetados dentro da MZ
 * principal pelo visit-template.php.
 *
 * Painéis:
 *  - visita  (seções: orphan-extras, gallery, sangha)
 *  - passage (conteúdo do passage carregado via REST)
 *  - lente   (lista temática de passages)
 */
defined('ABSPATH') || exit;
?>

<!-- ═══════════════════════════════════════════════════════════════
     SECTIONS PANEL (v6.2 — sem wrapper MZ duplicado)
     Conteúdo complementar da visita: gallery, sangha, passages.
     ═══════════════════════════════════════════════════════════════ -->
<div id="vana-sections-panel" class="vana-sections-panel" data-visit-id="<?php echo (int) ($visit_id ?? 0); ?>">

    <!-- Painel: VISITA (seções tradicionais) -->
    <div id="mz-panel-visita" class="vana-mz__panel is-active" data-mz-state="visita" data-panel="visita" role="tabpanel" aria-hidden="false">
        <?php
                // Hari-katha — ATIVADO
                if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/hari-katha.php' ) ) {
                    include VANA_MC_PATH . 'templates/visit/parts/hari-katha.php';
                }
                // Orphan extras (precomputed by Trator / schema >= 6.1)
                if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/orphan-extras.php' ) ) {
                    include VANA_MC_PATH . 'templates/visit/parts/orphan-extras.php';
                }

                // Gallery
                if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/gallery.php' ) ) {
                    include VANA_MC_PATH . 'templates/visit/parts/gallery.php';
                }

            // Sangha moments
            if ( file_exists( VANA_MC_PATH . 'templates/visit/parts/sangha-moments.php' ) ) {
                    include VANA_MC_PATH . 'templates/visit/parts/sangha-moments.php';
            }
        ?>
    </div>

    <!-- Painel: PASSAGE (conteúdo carregado dinamicamente) -->
    <div id="mz-panel-passage" class="vana-mz__panel" data-mz-state="passage" data-panel="passage" role="tabpanel" aria-hidden="true" hidden>
        <div id="mz-passage" class="vana-passage-container">
            <div class="vana-mz__loading" style="padding:36px;text-align:center;color:var(--vana-muted);">
                <?php echo esc_html( vana_t('passage.loading', $lang ?? 'pt') ); ?>
            </div>
        </div>
    </div>

    <!-- Painel: LENTE (lista temática, carregada via REST) -->
    <div id="mz-panel-lente" class="vana-mz__panel" data-mz-state="lente" data-panel="lente" role="tabpanel" aria-hidden="true" hidden>
        <div id="mz-lens" class="vana-lens-container">
            <div class="vana-mz__loading" style="padding:36px;text-align:center;color:var(--vana-muted);">
                <?php echo esc_html( vana_t('lens.loading', $lang ?? 'pt') ); ?>
            </div>
        </div>
    </div>

</div><!-- /vana-sections-panel -->

<script>
(function () {
    'use strict';

    /* ── Panel Switcher v6.2 ──────────────────────────────────────────
       Opera sobre #vana-sections-panel (não mais #vana-mutable-zone).
       Escuta vana:state:changed para alternar painéis visita/passage/lente.
       ───────────────────────────────────────────────────────────────── */
    var root = document.getElementById('vana-sections-panel');
    if (!root) return;

    function setActive(state) {
        root.querySelectorAll('.vana-mz__panel').forEach(function (p) {
            var ps = p.getAttribute('data-mz-state') || p.getAttribute('data-panel');
            var active = ps === state;
            p.classList.toggle('is-active', active);
            if (active) {
                p.removeAttribute('hidden');
                p.setAttribute('aria-hidden', 'false');
            } else {
                p.setAttribute('hidden', '');
                p.setAttribute('aria-hidden', 'true');
            }
        });
    }

    // Clique em chips âncora que apontam para seções
    document.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-vana-chip]');
        if (chip) {
            var target = chip.getAttribute('data-vana-chip');
            if (!target) return;
            if (target.indexOf('hk') !== -1 ||
                target.indexOf('gallery') !== -1 ||
                target.indexOf('sangha') !== -1) {
                setActive('visita');
            }
        }

        var openPass = e.target.closest('[data-vana-open-passage]');
        if (openPass) {
            e.preventDefault();
            var pid = openPass.getAttribute('data-passage-id');
            var kref = openPass.getAttribute('data-katha-ref') || '';
            if (!pid) return;
            if (window.VanaRouter && typeof window.VanaRouter.toPassage === 'function') {
                window.VanaRouter.toPassage(pid, kref);
            } else {
                openPassage(pid);
            }
        }
    });

    // Abre passage in-place via REST (fallback sem VanaRouter)
    function openPassage(passageId) {
        setActive('passage');
        var container = document.getElementById('mz-passage');
        if (!container) return;
        container.innerHTML = '<div class="vana-mz__loading">' +
            (window.__vanaLang === 'en' ? 'Loading…' : 'Carregando…') + '</div>';

        var restRoot = (window.CFG && window.CFG.restRoot) || '/wp-json/vana/v1/';
        var nonce    = (window.CFG && window.CFG.restNonce) || '';

        fetch(restRoot + 'passage/' + encodeURIComponent(passageId), {
            headers: { 'X-WP-Nonce': nonce },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json || !json.success) {
                container.innerHTML = '<div style="padding:36px;color:var(--vana-muted);text-align:center;">' +
                    (window.__vanaLang === 'en' ? 'Failed to load passage' : 'Falha ao carregar passage') + '</div>';
                return;
            }
            var html = (json.data && json.data.html)
                ? json.data.html
                : '<article class="vana-passage">' +
                  (json.data.title ? '<h3>' + json.data.title + '</h3>' : '') +
                  (json.data.content ? '<div>' + json.data.content + '</div>' : '') +
                  '</article>';
            container.innerHTML = html;
            try {
                history.pushState({ mz: 'passage', id: passageId }, '',
                    window.location.pathname + '#passage-' + passageId);
            } catch (e) {}
        })
        .catch(function () {
            container.innerHTML = '<div style="padding:36px;color:var(--vana-muted);text-align:center;">' +
                (window.__vanaLang === 'en' ? 'Failed to load passage' : 'Falha ao carregar passage') + '</div>';
        });
    }

    // Escuta o router para trocar painéis
    document.addEventListener('vana:state:changed', function (ev) {
        var s = ev && ev.detail && ev.detail.state;
        if (s) setActive(s);
    });

    // Estado inicial
    setActive('visita');

    window.addEventListener('popstate', function () {
        setActive('visita');
    });

    // Sync com VanaRouter se já ativo
    if (window.VanaRouter && window.VanaRouter.state) {
        setActive(window.VanaRouter.state);
    }

})();
</script>
