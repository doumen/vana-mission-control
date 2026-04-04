/**
 * VanaAgendaController.js — v1.2
 * Controla o drawer de agenda: open/close, tabs, VOD player.
 */
(function () {
    'use strict';

    function init() {
        var drawer  = document.querySelector('[data-vana-agenda-drawer]');
        var overlay = document.querySelector('[data-vana-agenda-overlay]');
        var openBtn = document.querySelector('[data-vana-agenda-open]');

        if (!drawer) {
            console.warn('[VanaAgenda] drawer não encontrado no DOM.');
            return;
        }

        // ── Open / Close ────────────────────────────────────────────
        function openDrawer() {
            drawer.removeAttribute('hidden');
            drawer.classList.add('is-open');
            if (overlay) {
                overlay.removeAttribute('hidden');
                overlay.classList.add('is-open');
            }
            document.body.classList.add('vana-drawer-open');
            document.querySelectorAll('[data-vana-agenda-open]').forEach(function(b) {
                b.setAttribute('aria-expanded', 'true');
            });
            var closeBtn = drawer.querySelector('[data-vana-agenda-close]');
            if (closeBtn) closeBtn.focus();
            else { drawer.setAttribute('tabindex', '-1'); drawer.focus(); }
        }

        function closeDrawer() {
            drawer.setAttribute('hidden', '');
            drawer.classList.remove('is-open');
            if (overlay) {
                overlay.setAttribute('hidden', '');
                overlay.classList.remove('is-open');
            }
            document.body.classList.remove('vana-drawer-open');
            document.querySelectorAll('[data-vana-agenda-open]').forEach(function(b) {
                b.setAttribute('aria-expanded', 'false');
            });
            if (openBtn) openBtn.focus();
        }

        // ── Botões abrir ────────────────────────────────────────────
        document.querySelectorAll('[data-vana-agenda-open]').forEach(function (btn) {
            btn.addEventListener('click', openDrawer);
        });

        // ── Overlay fecha ───────────────────────────────────────────
        if (overlay) overlay.addEventListener('click', closeDrawer);

        // ── Botão fechar dentro do drawer ───────────────────────────
        drawer.addEventListener('click', function (e) {
            if (e.target.closest('[data-vana-agenda-close]')) closeDrawer();
        });

        // ── ESC fecha ───────────────────────────────────────────────
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !drawer.hasAttribute('hidden')) closeDrawer();
        });

        // ── Tab switching ────────────────────────────────────────────
        drawer.addEventListener('click', function (e) {
            var tab = e.target.closest('[data-vana-agenda-day]');
            if (!tab) return;

            var dayKey = tab.getAttribute('data-vana-agenda-day');

            drawer.querySelectorAll('[data-vana-agenda-day]').forEach(function (t) {
                var active = t === tab;
                t.classList.toggle('is-active', active);
                t.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            drawer.querySelectorAll('[data-vana-agenda-panel]').forEach(function (p) {
                var active = p.getAttribute('data-vana-agenda-panel') === dayKey;
                p.classList.toggle('is-active', active);
                if (active) p.removeAttribute('hidden');
                else p.setAttribute('hidden', '');
            });
        });

        // ── VOD Player ───────────────────────────────────────────────
        drawer.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-vana-play-vod]');
            if (!btn) return;

            var videoId  = btn.getAttribute('data-vana-video-id');
            var provider = btn.getAttribute('data-vana-provider') || 'youtube';
            var title    = btn.getAttribute('data-vana-event-title') || '';

            if (provider === 'youtube' && videoId) {
                openVideoModal(videoId, title);
            }
        });

        // ── API pública ─────────────────────────────────────────────
        window.VanaAgenda = { open: openDrawer, close: closeDrawer };
        console.log('[VanaAgenda] v1.2 inicializado com sucesso.');
    }

    // ── Modal de vídeo ─────────────────────────────────────────────
    function openVideoModal(videoId, title) {
        var existing = document.getElementById('vana-video-modal');
        if (existing) existing.remove();

        var modal = document.createElement('div');
        modal.id = 'vana-video-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', title || 'Vídeo');
        modal.innerHTML =
            '<div class="vana-video-modal__backdrop"></div>' +
            '<div class="vana-video-modal__container">' +
                '<button class="vana-video-modal__close" aria-label="Fechar vídeo">' +
                    '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">' +
                        '<path d="M1 1L13 13M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
                    '</svg>' +
                '</button>' +
                '<div class="vana-video-modal__ratio">' +
                    '<iframe src="https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0" ' +
                        'frameborder="0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        requestAnimationFrame(function () { modal.classList.add('is-open'); });

        function closeModal() {
            modal.classList.remove('is-open');
            setTimeout(function () { modal.remove(); }, 280);
            document.removeEventListener('keydown', onKey);
        }

        function onKey(e) { if (e.key === 'Escape') closeModal(); }

        modal.querySelector('.vana-video-modal__backdrop').addEventListener('click', closeModal);
        modal.querySelector('.vana-video-modal__close').addEventListener('click', closeModal);
        document.addEventListener('keydown', onKey);
    }

    // ── Aguarda DOM ─────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
