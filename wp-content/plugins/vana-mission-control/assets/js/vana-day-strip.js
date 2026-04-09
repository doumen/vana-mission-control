(function () {
    'use strict';

    document.addEventListener('click', function (e) {

        // ── Clique num pill do hero ──────────────────────────────
        const pill = e.target.closest('[data-action="open-agenda-day"]');
        if (!pill) return;

        e.preventDefault();
        const dayKey = pill.dataset.dayKey;
        if (!dayKey) return;

        // 1. Abre a Agenda (remove hidden + marca body)
        const drawer  = document.getElementById('vana-agenda-drawer');
        const overlay = document.getElementById('vana-agenda-overlay');

        if (drawer)  drawer.removeAttribute('hidden');
        if (overlay) overlay.removeAttribute('hidden');
        document.body.classList.add('vana-drawer-open');

        // Atualiza aria do botão que abre a agenda
        const openBtn = document.getElementById('vana-agenda-open-btn');
        if (openBtn) openBtn.setAttribute('aria-expanded', 'true');

        // 2. Ativa o tab do dia correto dentro da Agenda
        const targetTab = drawer?.querySelector(
            `[role="tab"][data-day-key="${CSS.escape(dayKey)}"]`
        );

        if (targetTab) {
            // Desativa todos os tabs
            drawer.querySelectorAll('[role="tab"]').forEach(t => {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
            });

            // Esconde todos os panels
            drawer.querySelectorAll('[data-day-panel]').forEach(p => {
                p.classList.remove('is-active');
                p.setAttribute('hidden', '');
            });

            // Ativa o tab alvo
            targetTab.classList.add('is-active');
            targetTab.setAttribute('aria-selected', 'true');

            // Mostra o panel correspondente
            const targetPanel = drawer.querySelector(
                `[data-day-panel="${CSS.escape(dayKey)}"]`
            );
            if (targetPanel) {
                targetPanel.classList.add('is-active');
                targetPanel.removeAttribute('hidden');
            }
        }

        // 3. Scroll suave ao topo da Agenda
        drawer?.querySelector('.vana-drawer__body')?.scrollTo({ top: 0, behavior: 'smooth' });
    });

})();
