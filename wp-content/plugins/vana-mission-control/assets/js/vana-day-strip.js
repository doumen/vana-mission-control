console.log('vana-day-strip: init');
(function () {
    'use strict';

    document.addEventListener('click', function (e) {

        const pill = e.target.closest('[data-action="open-agenda-day"]');
        if (!pill) return;
        e.preventDefault();

        const dayKey = pill.dataset.dayKey;
        if (!dayKey) return;

        // ── 1. Atualiza is-active nas pills do HERO ──────────────
        document.querySelectorAll('.vana-day-pill').forEach(p => {
            const active = p.dataset.dayKey === dayKey;
            p.classList.toggle('is-active', active);
            p.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        // ── 2. Abre a Agenda ─────────────────────────────────────
        const drawer  = document.getElementById('vana-agenda-drawer');
        const overlay = document.getElementById('vana-agenda-overlay');
        const openBtn = document.getElementById('vana-agenda-open-btn');

        if (!drawer) return;

        drawer.removeAttribute('hidden');
        overlay?.removeAttribute('hidden');
        document.body.classList.add('vana-drawer-open');
        openBtn?.setAttribute('aria-expanded', 'true');

        // ── 3. Ativa o tab do dia na Agenda ──────────────────────
        const allTabs   = drawer.querySelectorAll('[role="tab"][data-day-key]');
        const allPanels = drawer.querySelectorAll('[data-day-panel]');

        // Reset todos
        allTabs.forEach(t => {
            t.classList.remove('is-active');
            t.setAttribute('aria-selected', 'false');
        });
        allPanels.forEach(p => {
            p.classList.remove('is-active');
            p.setAttribute('hidden', '');
        });

        // Ativa o alvo
        const targetTab = drawer.querySelector(
            `[role="tab"][data-day-key="${CSS.escape(dayKey)}"]`
        );
        const targetPanel = drawer.querySelector(
            `[data-day-panel="${CSS.escape(dayKey)}"]`
        );

        if (targetTab) {
            targetTab.classList.add('is-active');
            targetTab.setAttribute('aria-selected', 'true');
        }

        if (targetPanel) {
            targetPanel.classList.add('is-active');
            targetPanel.removeAttribute('hidden');
        }

        // ── 4. Scroll ao topo do body da Agenda ──────────────────
        drawer.querySelector('.vana-drawer__body')
              ?.scrollTo({ top: 0, behavior: 'smooth' });

    });

})();
