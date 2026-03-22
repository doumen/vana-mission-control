/**
 * VanaEventController.js
 * Intercepta cliques nos botões do event-selector e recarrega a página
 * adicionando ?event_key={key} à URL (SSR via VisitStageResolver).
 *
 * @since 5.1.1
 */
(function () {
    'use strict';

    function init() {
        const selector = document.querySelector('.vana-event-selector');
        if (!selector) return;

        selector.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-vana-event-key]');
            if (!btn) return;

            const key = btn.dataset.vanaEventKey;
            if (!key) return;

            // Não recarrega se já estiver ativo
            if (btn.classList.contains('vana-event-btn--active')) return;

            e.preventDefault();

            // Monta nova URL preservando demais params (?v_day=, ?lang=, etc)
            const url = new URL(window.location.href);
            url.searchParams.set('event_key', key);
            window.location.href = url.toString();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
