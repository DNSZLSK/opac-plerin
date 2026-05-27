/**
 * OPAC Plérin - frontend interactions
 *
 * P0 : dropdown CTA "S'inscrire" (toggle, click outside, escape, A11y).
 * Modules suivants : filtres agenda (M5), validation form inscription (M7).
 */

(function () {
    'use strict';

    function closeAll(wrappers) {
        wrappers.forEach(function (wrapper) {
            wrapper.setAttribute('data-open', 'false');
            var btn = wrapper.querySelector('.opac-cta-btn');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function initCtaDropdown() {
        var wrappers = document.querySelectorAll('.opac-cta');
        if (!wrappers.length) {
            return;
        }

        wrappers.forEach(function (wrapper) {
            var btn = wrapper.querySelector('.opac-cta-btn');
            if (!btn) {
                return;
            }

            btn.setAttribute('aria-haspopup', 'true');
            btn.setAttribute('aria-expanded', 'false');

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = wrapper.getAttribute('data-open') === 'true';
                closeAll(wrappers);
                if (!isOpen) {
                    wrapper.setAttribute('data-open', 'true');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });

            wrapper.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });

        document.addEventListener('click', function () {
            closeAll(wrappers);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAll(wrappers);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCtaDropdown);
    } else {
        initCtaDropdown();
    }
})();
