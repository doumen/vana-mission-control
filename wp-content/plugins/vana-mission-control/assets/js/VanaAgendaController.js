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
    'use strict';

    // Use delegated listeners and lazy DOM lookup so controller works
    // even if the drawer is injected after scripts run.

    document.addEventListener('click', function (e) {
        // open trigger (delegated)
        if (e.target.closest('[data-vana-agenda-open]')) {
            _openDrawer();
            return;
        }

        // close triggers (delegated)
        if (
            e.target.closest('[data-vana-agenda-close]') ||
            e.target.closest('[data-vana-agenda-overlay]')
        ) {
            _closeDrawer();
            return;
        }

        // If click is inside the drawer, handle specific actions
        var drawer = _drawer();
        if (!drawer) return;
        if (!drawer.contains(e.target)) return;

        // day tab
        var tab = e.target.closest('[data-vana-agenda-day]');
        if (tab) {
            _switchDay(tab.dataset.vanaAgendaDay);
            return;
        }

        // VOD play
        var playBtn = e.target.closest('[data-vana-play-vod]');
        if (playBtn) {
            _emitMediaSelect(playBtn);
            if (window.innerWidth < 768) _closeDrawer();
            return;
        }

        // HK
        var hkBtn = e.target.closest('[data-vana-open-hk]');
        if (hkBtn) {
            _emitHKSelect(hkBtn);
            if (window.innerWidth < 768) _closeDrawer();
            return;
        }

        // Gallery
        var galBtn = e.target.closest('[data-vana-open-gallery]');
        if (galBtn) {
            _emitGallerySelect(galBtn);
            if (window.innerWidth < 768) _closeDrawer();
            return;
        }
    });

    // ── Escuta vana:day:change do Hero (sincroniza tab) ──────────
    // NÃO recarrega nada — apenas reflete o estado visual
    document.addEventListener('vana:day:change', function (e) {
        const day = e.detail && e.detail.day;
        if (day) _switchDay(day, /* silent */ true);
    });

    // Fecha com ESC (lazy drawer lookup)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var drawer = _drawer();
            if (drawer && !drawer.hidden) _closeDrawer();
        }
    });

    // ════════════════════════════════════════════════════════════
    // EMISSORES DE EVENTOS
    // ════════════════════════════════════════════════════════════

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
