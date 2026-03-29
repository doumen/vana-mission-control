/**
 * VanaAgendaController.js
 * assets/js/VanaAgendaController.js
 *
 * Responsabilidades:
 *  - Abre / fecha a gaveta
 *  - Troca painéis de dia (browse interno — zero side effects)
 *  - Emite vana:event:select ao escolher um evento (▶ ou 📖)
 *  - Sincroniza tab ativa com vana:day:change externo (Hero)
 */
(function () {
    (function () {
        'use strict';

        // VanaAgendaController.js — v2
        // Controls the schedule drawer: open/close, day tabs, event selects.

        function init() {
            var drawer  = document.getElementById('vana-agenda-drawer');
            var overlay = document.querySelector('[data-vana-agenda-overlay]');

            if (!drawer) {
                console.warn('[VanaAgenda] #vana-agenda-drawer não encontrado no DOM.');
                return;
            }

            // Attach open handlers to any element with the data attribute.
            document.querySelectorAll('[data-vana-agenda-open]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    openDrawer();
                });
            });

            // Close handlers
            document.querySelectorAll('[data-vana-agenda-close]').forEach(function (btn) {
                btn.addEventListener('click', function (e) { e.preventDefault(); closeDrawer(); });
            });

            if (overlay) {
                overlay.addEventListener('click', function (e) { e.preventDefault(); closeDrawer(); });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !drawer.hasAttribute('hidden')) {
                    closeDrawer();
                }
            });

            // Delegated click inside the drawer for tabs and buttons
            drawer.addEventListener('click', function (e) {
                var tab = e.target.closest('[data-vana-agenda-day]');
                if (tab) {
                    _switchDay(tab.getAttribute('data-vana-agenda-day'), 'agenda');
                    return;
                }

                var playBtn = e.target.closest('[data-vana-play-vod]');
                if (playBtn) {
                    _emitMediaSelect(playBtn);
                    if (window.innerWidth < 768) closeDrawer();
                    return;
                }

                var hkBtn = e.target.closest('[data-vana-open-hk]');
                if (hkBtn) {
                    _emitHKSelect(hkBtn);
                    if (window.innerWidth < 768) closeDrawer();
                    return;
                }

                var galBtn = e.target.closest('[data-vana-open-gallery]');
                if (galBtn) {
                    _emitGallerySelect(galBtn);
                    if (window.innerWidth < 768) closeDrawer();
                    return;
                }
            });

            // Listen hero day changes to sync
            document.addEventListener('vana:day:change', function (e) {
                if (!e.detail || !e.detail.day) return;
                if (e.detail._source === 'agenda') return; // avoid loop
                _switchDay(e.detail.day, null);
            });
        }

        function openDrawer() {
            var drawer  = document.getElementById('vana-agenda-drawer');
            var overlay = document.querySelector('[data-vana-agenda-overlay]');
            var openers = document.querySelectorAll('[data-vana-agenda-open]');

            if (!drawer) return;
            drawer.removeAttribute('hidden');
            if (overlay) overlay.removeAttribute('hidden');
            openers.forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
            document.body.classList.add('vana-drawer-open');
            drawer.removeAttribute('aria-hidden');

            requestAnimationFrame(function () {
                var focusable = drawer.querySelector('button:not([disabled]), [href], [tabindex]');
                if (focusable) focusable.focus();
            });
        }

        function closeDrawer() {
            var drawer  = document.getElementById('vana-agenda-drawer');
            var overlay = document.querySelector('[data-vana-agenda-overlay]');
            var openers = document.querySelectorAll('[data-vana-agenda-open]');

            if (!drawer) return;
            drawer.setAttribute('hidden', '');
            if (overlay) overlay.setAttribute('hidden', '');
            openers.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
            document.body.classList.remove('vana-drawer-open');
            drawer.setAttribute('aria-hidden', 'true');

            var trigger = document.getElementById('vana-agenda-open-btn') || document.querySelector('[data-vana-agenda-open]');
            if (trigger) trigger.focus();
        }

        function _switchDay(dayKey, source) {
            var drawer = document.getElementById('vana-agenda-drawer');
            if (!drawer || !dayKey) return;

            drawer.querySelectorAll('[data-vana-agenda-day]').forEach(function (tab) {
                var active = tab.getAttribute('data-vana-agenda-day') === dayKey;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            drawer.querySelectorAll('[data-vana-agenda-panel]').forEach(function (panel) {
                var active = panel.getAttribute('data-vana-agenda-panel') === dayKey;
                if (active) {
                    panel.removeAttribute('hidden');
                    panel.classList.add('is-active');
                } else {
                    panel.setAttribute('hidden', '');
                    panel.classList.remove('is-active');
                }
            });

            if (source === 'agenda') {
                document.dispatchEvent(new CustomEvent('vana:day:change', { bubbles: true, detail: { day: dayKey, _source: 'agenda' } }));
            }
        }

        function _emitMediaSelect(btn) {
            var evLi = btn.closest('[data-vana-event-key]');
            var detail = {
                type: 'vod',
                event_key: btn.dataset.vanaEventKey || '',
                video_id: btn.dataset.vanaVideoId || btn.dataset.vanaVideo || '',
                provider: btn.dataset.vanaProvider || 'youtube',
                day_key: evLi ? evLi.dataset.vanaDayKey || '' : '',
            };
            document.dispatchEvent(new CustomEvent('vana:event:select', { bubbles: true, detail: detail }));
        }

        function _emitHKSelect(btn) {
            var evLi = btn.closest('[data-vana-event-key]');
            var detail = { type: 'katha', event_key: btn.dataset.vanaOpenHk || '', katha_ids: (btn.dataset.vanaKathaIds||'').split(',').filter(Boolean), day_key: evLi ? evLi.dataset.vanaDayKey || '' : '' };
            document.dispatchEvent(new CustomEvent('vana:event:select', { bubbles: true, detail: detail }));
        }

        function _emitGallerySelect(btn) {
            var evLi = btn.closest('[data-vana-event-key]');
            var detail = { type: 'gallery', event_key: btn.dataset.vanaOpenGallery || '', day_key: evLi ? evLi.dataset.vanaDayKey || '' : '' };
            document.dispatchEvent(new CustomEvent('vana:event:select', { bubbles: true, detail: detail }));
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

    }());
    /**
     * Emite vana:event:select com payload completo para o Stage.
     * Chamado ao clicar ▶ em qualquer VOD.
     */
    function _emitMediaSelect(btn) {
        const detail = {
            type:        'video',
            vod_key:     btn.dataset.vanaPlayVod     || '',
            video_id:    btn.dataset.vanaVideoId      || '',
            provider:    btn.dataset.vanaProvider     || 'youtube',
            event_key:   btn.dataset.vanaEventKey     || '',
            event_title: btn.dataset.vanaEventTitle   || '',
            event_time:  btn.dataset.vanaEventTime    || '',
            day_key:     btn.dataset.vanaDayKey       || '',
            timestamp_start: 0,
        };

        _dispatch('vana:event:select', detail);
    }

    /**
     * Emite vana:event:select com type='katha' para o HK.
     */
    function _emitHKSelect(btn) {
        const evLi = btn.closest('[data-vana-event-key]');
        const detail = {
            type:       'katha',
            event_key:  btn.dataset.vanaOpenHk         || '',
            katha_ids:  (btn.dataset.vanaKathaIds || '').split(',').filter(Boolean),
            event_title: evLi ? (evLi.querySelector('.vana-agenda__event-title')?.textContent?.trim() || '') : '',
            day_key:    evLi ? (evLi.dataset.vanaDayKey || '') : '',
        };

        _dispatch('vana:event:select', detail);
    }

    /**
     * Emite vana:event:select com type='gallery'.
     */
    function _emitGallerySelect(btn) {
        const evLi = btn.closest('[data-vana-event-key]');
        const detail = {
            type:      'gallery',
            event_key: btn.dataset.vanaOpenGallery || '',
            day_key:   evLi ? (evLi.dataset.vanaDayKey || '') : '',
        };

        _dispatch('vana:event:select', detail);
    }

    // ════════════════════════════════════════════════════════════
    // BROWSE INTERNO
    // ════════════════════════════════════════════════════════════

    /**
     * Troca o painel de dia na gaveta.
     * silent=true → não emite vana:day:change
     * (evita loop quando recebe o evento do Hero)
     */
    function _switchDay(dayKey, silent) {
        var drawer = _drawer();
        if (!drawer) return;

        // Atualiza tabs
        drawer.querySelectorAll('[data-vana-agenda-day]').forEach(function (t) {
            const active = t.dataset.vanaAgendaDay === dayKey;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        // Atualiza painéis
        drawer.querySelectorAll('[data-vana-agenda-panel]').forEach(function (p) {
            const show = p.dataset.vanaAgendaPanel === dayKey;
            p.hidden = !show;
            p.classList.toggle('is-active', show);
        });

        // Emite para o Hero sincronizar (só se não for silencioso)
        if (!silent) {
            _dispatch('vana:day:change', { day: dayKey });
        }
    }

    // ════════════════════════════════════════════════════════════
    // GAVETA
    // ════════════════════════════════════════════════════════════

    function _openDrawer() {
        var drawer = _drawer();
        var overlay = _overlay();
        if (!drawer || !overlay) {
            console.warn('[VanaAgenda] drawer or overlay missing');
            return;
        }

        drawer.hidden  = false;
        overlay.hidden = false;
        document.body.classList.add('vana-drawer-open');
        drawer.removeAttribute('aria-hidden');

        // Atualiza aria-expanded nos triggers
        document.querySelectorAll('[data-vana-agenda-open]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'true');
        });

        requestAnimationFrame(function () {
            const first = drawer.querySelector('button:not([disabled]), [href], [tabindex="0"]');
            if (first) first.focus();
        });
    }

    function _closeDrawer() {
        var drawer = _drawer();
        var overlay = _overlay();
        if (!drawer || !overlay) return;

        drawer.hidden  = true;
        overlay.hidden = true;
        document.body.classList.remove('vana-drawer-open');
        drawer.setAttribute('aria-hidden', 'true');

        document.querySelectorAll('[data-vana-agenda-open]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });

        const trigger = document.getElementById('vana-agenda-open-btn') || document.querySelector('[data-vana-agenda-open]');
        if (trigger) trigger.focus();
    }

    // ════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════

    function _dispatch(eventName, detail) {
        document.dispatchEvent(
            new CustomEvent(eventName, { bubbles: true, detail: detail })
        );
    }

    // ── Lazy DOM getters ───────────────────────────────────────────
    function _drawer() { return document.getElementById('vana-agenda-drawer'); }
    function _overlay() { return document.getElementById('vana-agenda-overlay'); }

})();
