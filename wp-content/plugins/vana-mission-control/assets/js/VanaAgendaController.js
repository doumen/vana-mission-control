/**
 * VanaAgendaController.js — Schema 6.1 / v4
 * Unificado: remove IIFE duplo, conecta todos os listeners,
 * expõe window.VanaAgenda para debug externo.
 */
(function () {
    'use strict';

    // ── Seletores ────────────────────────────────────────────────
    var SEL = {
        drawer:  '[data-vana-agenda-drawer]',
        overlay: '[data-vana-agenda-overlay]',
        open:    '[data-vana-agenda-open]',
        close:   '[data-vana-agenda-close]',
        dayTab:  '[data-vana-agenda-day]',
        panel:   '[data-vana-agenda-panel]',
        playVod: '[data-vana-play-vod]',
        openHk:  '[data-vana-open-hk]'
    };

    // ── Estado ───────────────────────────────────────────────────
    var _isOpen    = false;
    var _lastFocus = null;
    var _activeDay = null;

    // ── DOM helpers ──────────────────────────────────────────────
    function drawer()  { return document.getElementById('vana-agenda-drawer')  || document.querySelector(SEL.drawer);  }
    function overlay() { return document.getElementById('vana-agenda-overlay') || document.querySelector(SEL.overlay); }

    function setVisible(el, visible) {
        if (!el) return;
        el.hidden = !visible;
        if (visible) el.removeAttribute('hidden');
    }

    function dispatch(name, detail) {
        var ev = new CustomEvent(name, { bubbles: true, cancelable: true, detail: detail || {} });
        return document.dispatchEvent(ev);
    }

    function getFocusable(root) {
        if (!root) return [];
        return Array.from(root.querySelectorAll(
            'a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])'
        )).filter(function (el) { return el.offsetParent !== null; });
    }

    function trapFocus(el) {
        if (!el) return;
        el.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            var f = getFocusable(el);
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        });
    }

    // ── Gaveta: abrir ────────────────────────────────────────────
    function openDrawer() {
        var d = drawer(), o = overlay();
        if (!d) { console.warn('[VanaAgenda] drawer não encontrado no DOM.'); return; }

        _lastFocus = document.activeElement;

        setVisible(d, true);
        if (o) setVisible(o, true);

        // Garante transição CSS
        requestAnimationFrame(function () {
            d.classList.add('is-open');
            if (o) o.classList.add('is-open');
        });

        document.body.style.overflow = 'hidden';
        document.body.classList.add('vana-drawer-open');
        d.removeAttribute('aria-hidden');

        document.querySelectorAll(SEL.open).forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'true');
        });

        _isOpen = true;
        dispatch('vana:agenda:open', {});

        requestAnimationFrame(function () {
            var closeBtn = d.querySelector(SEL.close);
            if (closeBtn) closeBtn.focus();
            else d.focus();
        });
    }

    // ── Gaveta: fechar ───────────────────────────────────────────
    function closeDrawer() {
        var d = drawer(), o = overlay();
        if (!d) return;

        d.classList.remove('is-open');
        if (o) o.classList.remove('is-open');

        // Aguarda transição antes de esconder
        var delay = parseFloat(getComputedStyle(d).transitionDuration || '0') * 1000;
        setTimeout(function () {
            setVisible(d, false);
            if (o) setVisible(o, false);
        }, delay || 0);

        document.body.style.overflow = '';
        document.body.classList.remove('vana-drawer-open');
        d.setAttribute('aria-hidden', 'true');

        document.querySelectorAll(SEL.open).forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });

        _isOpen = false;
        dispatch('vana:agenda:close', {});

        if (_lastFocus && typeof _lastFocus.focus === 'function') {
            _lastFocus.focus();
        }
    }

    // ── Troca de dia ─────────────────────────────────────────────
    function switchDay(dayKey, source) {
        var d = drawer();
        if (!d || !dayKey) return;

        d.querySelectorAll(SEL.dayTab).forEach(function (tab) {
            var active = tab.getAttribute('data-vana-agenda-day') === dayKey;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.classList.toggle('is-active', active);
        });

        d.querySelectorAll(SEL.panel).forEach(function (panel) {
            var active = panel.getAttribute('data-vana-agenda-panel') === dayKey;
            setVisible(panel, active);
            panel.classList.toggle('is-active', active);
        });

        _activeDay = dayKey;

        if (source === 'agenda') {
            dispatch('vana:day:change', { day: dayKey, _source: 'agenda' });
        }
    }

    // ── Bind de todos os eventos ─────────────────────────────────
    function bindAll() {
        var d = drawer();
        if (!d) {
            console.warn('[VanaAgenda] #vana-agenda-drawer não encontrado — abortando init.');
            return;
        }

        d.setAttribute('tabindex', '-1');
        trapFocus(d);

        // Botões de abrir
        document.querySelectorAll(SEL.open).forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openDrawer();
            });
        });

        // Botões de fechar
        d.querySelectorAll(SEL.close).forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                closeDrawer();
            });
        });

        // Overlay
        var o = overlay();
        if (o) o.addEventListener('click', closeDrawer);

        // Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && _isOpen) closeDrawer();
        });

        // Tabs de dia
        d.addEventListener('click', function (e) {
            var tab = e.target.closest(SEL.dayTab);
            if (!tab) return;
            switchDay(tab.getAttribute('data-vana-agenda-day'), 'agenda');
        });

        // Clique em evento → reload com ?day=&event_key=
        d.addEventListener('click', function (e) {
            var li = e.target.closest('[data-vana-event-key][data-vana-day-key]');
            if (!li) return;
            var isVod    = !!e.target.closest(SEL.playVod);
            var isHk     = !!e.target.closest(SEL.openHk);
            var isGal    = !!e.target.closest('[data-vana-open-gallery]');
            var isNotify = !!e.target.closest('[data-vana-notify-event]');
            if (isVod || isHk || isGal || isNotify) return;
            var evKey  = li.getAttribute('data-vana-event-key')  || '';
            var dayKey = li.getAttribute('data-vana-day-key')    || '';
            if (!evKey || !dayKey) return;
            e.preventDefault();
            closeDrawer();
            var url = new URL(window.location.href);
            url.searchParams.set('day', dayKey);
            url.searchParams.set('event_key', evKey);
            window.location.href = url.toString();
        });

        // VOD Play → emite evento + reload
        d.addEventListener('click', function (e) {
            var btn = e.target.closest(SEL.playVod);
            if (!btn) return;
            var videoId  = btn.getAttribute('data-vana-video-id')  || '';
            var provider = btn.getAttribute('data-vana-provider')  || 'youtube';
            var evKey    = btn.getAttribute('data-vana-event-key') || '';
            var dayKey   = btn.getAttribute('data-vana-day-key')   || '';
            if (!videoId) return;
            e.preventDefault();
            var proceed = dispatch('vana:event:select', {
                type: 'video', videoId: videoId, provider: provider,
                eventKey: evKey, dayKey: dayKey
            });
            if (!proceed) return;
            closeDrawer();
            var url = new URL(window.location.href);
            url.searchParams.set('day', dayKey);
            if (evKey) url.searchParams.set('event_key', evKey);
            window.location.href = url.toString();
        });

        // Sincroniza com evento externo do Hero (evita loop com _source)
        document.addEventListener('vana:day:change', function (e) {
            if (!e.detail || !e.detail.day) return;
            if (e.detail._source === 'agenda') return;
            switchDay(e.detail.day, null);
        });

        // Ativa o dia inicial
        var firstTab = d.querySelector(SEL.dayTab + '.is-active') || d.querySelector(SEL.dayTab);
        if (firstTab) {
            var firstDay = firstTab.getAttribute('data-vana-agenda-day');
            if (firstDay) switchDay(firstDay, null);
        }
    }

    // ── Init ─────────────────────────────────────────────────────
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindAll);
        } else {
            bindAll(); // DOM já pronto (script carregado com defer ou no footer)
        }
    }

    init();

    // ── API pública ──────────────────────────────────────────────
    window.VanaAgenda = {
        open:      openDrawer,
        close:     closeDrawer,
        switchDay: switchDay
    };

}());
