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

    // ── Referências DOM ──────────────────────────────────────────
    const drawer  = document.getElementById('vana-agenda-drawer');
    const overlay = document.getElementById('vana-agenda-overlay');

    if (!drawer || !overlay) return;

    // ── Abre a gaveta ────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-vana-agenda-open]')) {
            _openDrawer();
        }
    });

    // ── Fecha: botão close ou overlay ────────────────────────────
    document.addEventListener('click', function (e) {
        if (
            e.target.closest('[data-vana-agenda-close]') ||
            e.target.closest('[data-vana-agenda-overlay]')
        ) {
            _closeDrawer();
        }
    });

    // ── Fecha com ESC ────────────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !drawer.hidden) _closeDrawer();
    });

    // ── Delegação de cliques dentro da gaveta ────────────────────
    drawer.addEventListener('click', function (e) {

        // ── Day tab (browse interno — NÃO emite evento externo) ──
        const tab = e.target.closest('[data-vana-agenda-day]');
        if (tab) {
            _switchDay(tab.dataset.vanaAgendaDay);
            return;
        }

        // ── Botão ▶ play VOD ─────────────────────────────────────
        const playBtn = e.target.closest('[data-vana-play-vod]');
        if (playBtn) {
            _emitMediaSelect(playBtn);
            if (window.innerWidth < 768) _closeDrawer();
            return;
        }

        // ── Botão 📖 Hari-kathā ──────────────────────────────────
        const hkBtn = e.target.closest('[data-vana-open-hk]');
        if (hkBtn) {
            _emitHKSelect(hkBtn);
            if (window.innerWidth < 768) _closeDrawer();
            return;
        }

        // ── Botão 🖼 galeria ─────────────────────────────────────
        const galBtn = e.target.closest('[data-vana-open-gallery]');
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
        drawer.hidden  = false;
        overlay.hidden = false;
        document.body.classList.add('vana-drawer-open');
        drawer.removeAttribute('aria-hidden');

        const first = drawer.querySelector('button, [href], [tabindex="0"]');
        if (first) first.focus();
    }

    function _closeDrawer() {
        drawer.hidden  = true;
        overlay.hidden = true;
        document.body.classList.remove('vana-drawer-open');
        drawer.setAttribute('aria-hidden', 'true');

        // Devolve foco ao trigger
        const trigger = document.querySelector('[data-vana-agenda-open]');
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

})();
