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

    /**
     * VanaAgendaController.js — v3 (Schema 6.1)
     * Seletores e eventos alinhados ao markup atual do agenda-drawer.php
     */
    (function () {
        'use strict';
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
        var _isOpen = false, _lastFocus = null, _activeDay = null;
        function drawer()  { return document.querySelector(SEL.drawer); }
        function overlay() { return document.querySelector(SEL.overlay); }
        function emit(name, detail) { document.dispatchEvent(new CustomEvent(name, {bubbles:true, detail:detail||{}})); }
        function setVisible(el, visible) { if (!el) return; if (visible) { el.removeAttribute('hidden'); el.hidden = false; } else { el.setAttribute('hidden',''); el.hidden = true; } }
        function getFocusable(root) { if (!root) return []; return Array.from(root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter(function(el){return el.offsetParent!==null;}); }
        function trapFocus(el) { if (!el) return; el.addEventListener('keydown',function(e){if(e.key!=='Tab')return;var f=getFocusable(el);if(!f.length)return;var first=f[0],last=f[f.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}); }
        function openDrawer() { var d=drawer(),o=overlay(); if(!d){console.warn('[VanaAgenda] drawer não encontrado no DOM.');return;} _lastFocus=document.activeElement; setVisible(d,true); setVisible(o,true); d.classList.add('is-open'); if(o)o.classList.add('is-open'); document.body.style.overflow='hidden'; document.querySelectorAll(SEL.open).forEach(function(btn){btn.setAttribute('aria-expanded','true');}); _isOpen=true; var closeBtn=d.querySelector(SEL.close); if(closeBtn)closeBtn.focus();else d.focus(); emit('vana:agenda:open',{}); }
        function closeDrawer() { var d=drawer(),o=overlay(); if(!d)return; d.classList.remove('is-open'); if(o)o.classList.remove('is-open'); setVisible(d,false); setVisible(o,false); document.body.style.overflow=''; document.querySelectorAll(SEL.open).forEach(function(btn){btn.setAttribute('aria-expanded','false');}); _isOpen=false; if(_lastFocus&&typeof _lastFocus.focus==='function'){_lastFocus.focus();} emit('vana:agenda:close',{}); }
        function switchDay(dayKey,source){var d=drawer();if(!d||!dayKey)return;d.querySelectorAll(SEL.dayTab).forEach(function(tab){var active=tab.getAttribute('data-vana-agenda-day')===dayKey;tab.setAttribute('aria-selected',active?'true':'false');tab.classList.toggle('is-active',active);});d.querySelectorAll(SEL.panel).forEach(function(panel){var active=panel.getAttribute('data-vana-agenda-panel')===dayKey;setVisible(panel,active);panel.classList.toggle('is-active',active);});_activeDay=dayKey;if(source==='agenda'){emit('vana:day:change',{day:dayKey,_source:'agenda'});}}
        function handlePlayVod(btn){var videoId=btn.getAttribute('data-vana-video-id')||'',provider=btn.getAttribute('data-vana-provider')||'youtube',evKey=btn.getAttribute('data-vana-event-key')||'',dayKey=btn.getAttribute('data-vana-day-key')||'';if(!videoId)return;emit('vana:event:select',{videoId:videoId,provider:provider,eventKey:evKey,dayKey:dayKey});closeDrawer();}
        function bindAll(){var d=drawer();if(!d){console.warn('[VanaAgenda] #vana-agenda-drawer não encontrado — abortando init.');return;}d.setAttribute('tabindex','-1');trapFocus(d);document.querySelectorAll(SEL.open).forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();openDrawer();});});d.querySelectorAll(SEL.close).forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();closeDrawer();});});var o=overlay();if(o)o.addEventListener('click',closeDrawer);document.addEventListener('keydown',function(e){if(e.key==='Escape'&&_isOpen)closeDrawer();});d.addEventListener('click',function(e){var tab=e.target.closest(SEL.dayTab);if(!tab)return;switchDay(tab.getAttribute('data-vana-agenda-day'),'agenda');});d.addEventListener('click',function(e){var btn=e.target.closest(SEL.playVod);if(!btn)return;handlePlayVod(btn);});var firstTab=d.querySelector(SEL.dayTab+'.is-active')||d.querySelector(SEL.dayTab);if(firstTab){var firstDay=firstTab.getAttribute('data-vana-agenda-day');if(firstDay)switchDay(firstDay,null);}document.addEventListener('vana:day:change',function(e){if(!e.detail||!e.detail.day)return;if(e.detail._source==='agenda')return;switchDay(e.detail.day,null);});}
        if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',bindAll);}else{bindAll();}
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
