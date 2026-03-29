(function () {
    'use strict';

    var _syncingFromGaveta = false;

    document.querySelectorAll('.vana-day-selector').forEach(function (selector) {
        var tabs   = selector.querySelectorAll('[role="tab"]');
        var panels = selector.querySelectorAll('[role="tabpanel"]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                _activateTab(this);
            });

            tab.addEventListener('keydown', function (e) {
                var idx  = Array.from(tabs).indexOf(this);
                var next = null;
                if (e.key === 'ArrowRight') next = tabs[idx + 1] || tabs[0];
                if (e.key === 'ArrowLeft')  next = tabs[idx - 1] || tabs[tabs.length - 1];
                if (next) { next.focus(); next.click(); }
            });
        });

        function _activateTab(activeTab) {
            var target = activeTab.getAttribute('aria-controls');

            // Desativa todos
            tabs.forEach(function (t) {
                t.setAttribute('aria-selected', 'false');
                t.setAttribute('tabindex', '-1');
                t.classList.remove('vana-day-selector__tab--active');
            });
            panels.forEach(function (p) {
                p.setAttribute('hidden', '');
                p.classList.remove('vana-day-selector__panel--active');
            });

            // Ativa o selecionado
            activeTab.setAttribute('aria-selected', 'true');
            activeTab.removeAttribute('tabindex');
            activeTab.classList.add('vana-day-selector__tab--active');

            var panel = document.getElementById(target);
            if (panel) {
                panel.removeAttribute('hidden');
                panel.classList.add('vana-day-selector__panel--active');
            }

            // Emite vana:day:change para sincronizar a gaveta
            var dayKey = activeTab.dataset.dayKey
                      || activeTab.dataset.date
                      || activeTab.dataset.index
                      || '';

            if (dayKey) {
                document.dispatchEvent(
                    new CustomEvent('vana:day:change', {
                        bubbles: true,
                        detail:  { day: dayKey, _source: 'hero' }
                    })
                );
            }
        }
    });

    // Sincroniza a aba do Hero quando a gaveta emite vana:day:change
    document.addEventListener('vana:day:change', function (e) {
        if (!e.detail || !e.detail.day || e.detail._source === 'hero') return;
        if (_syncingFromGaveta) return;

        var dayKey = e.detail.day;
        _syncingFromGaveta = true;

        document.querySelectorAll('.vana-day-selector [role="tab"]').forEach(function (tab) {
            var tabKey = tab.dataset.dayKey
                      || tab.dataset.date
                      || tab.dataset.index
                      || '';

            if (tabKey === dayKey) {
                tab.click();
            }
        });

        _syncingFromGaveta = false;
    });

}());
