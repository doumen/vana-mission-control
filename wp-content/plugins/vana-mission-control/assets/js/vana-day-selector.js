(function () {
    'use strict';

    document.querySelectorAll('.vana-day-selector').forEach(function (selector) {
        var tabs   = selector.querySelectorAll('[role="tab"]');
        var panels = selector.querySelectorAll('[role="tabpanel"]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = this.getAttribute('aria-controls');

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
                this.setAttribute('aria-selected', 'true');
                this.removeAttribute('tabindex');
                this.classList.add('vana-day-selector__tab--active');

                var panel = document.getElementById(target);
                if (panel) {
                    panel.removeAttribute('hidden');
                    panel.classList.add('vana-day-selector__panel--active');
                }
            });

            // Navegação por teclado (← →)
            tab.addEventListener('keydown', function (e) {
                var idx  = Array.from(tabs).indexOf(this);
                var next = null;
                if (e.key === 'ArrowRight') next = tabs[idx + 1] || tabs[0];
                if (e.key === 'ArrowLeft')  next = tabs[idx - 1] || tabs[tabs.length - 1];
                if (next) { next.focus(); next.click(); }
            });
        });
    });
}());
