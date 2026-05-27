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

    /**
     * Tabs de filtrage des stages ephemeres par periode (archive page).
     * Show/hide cards en jouant sur la classe opac-period-<slug> ajoutee
     * au wrapper post WP par le filter post_class du plugin opac-custom.
     */
    /**
     * Helper : navigation clavier au sein d'un tablist (fleches gauche/droite,
     * Home, End) avec rotation + focus auto. Active aussi le tab focuse au
     * passage (auto-activation pattern WAI-ARIA APG).
     */
    function bindTablistKeyboard(tabs) {
        tabs.forEach(function (tab, idx) {
            tab.addEventListener('keydown', function (e) {
                var target = null;
                if (e.key === 'ArrowRight') {
                    target = tabs[(idx + 1) % tabs.length];
                } else if (e.key === 'ArrowLeft') {
                    target = tabs[(idx - 1 + tabs.length) % tabs.length];
                } else if (e.key === 'Home') {
                    target = tabs[0];
                } else if (e.key === 'End') {
                    target = tabs[tabs.length - 1];
                }
                if (target) {
                    e.preventDefault();
                    target.focus();
                    target.click();
                }
            });
        });
    }

    function initStageTabs() {
        var tabs = document.querySelectorAll('.opac-stage-tabs [data-period]');
        if (!tabs.length) {
            return;
        }
        var cards = document.querySelectorAll('.opac-stages-list > .wp-block-post');
        if (!cards.length) {
            return;
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');

                var period = tab.getAttribute('data-period');
                cards.forEach(function (card) {
                    var match = period === 'all' || card.classList.contains('opac-period-' + period);
                    card.style.display = match ? '' : 'none';
                });
            });
        });

        bindTablistKeyboard(tabs);
    }

    /**
     * Tabs de filtrage de l'agenda par categorie (archive opac_event).
     * Show/hide cards a.opac-event par classe opac-cat-<slug>, puis
     * cache les .opac-month-label devenus orphelins (aucun event visible
     * dans leur segment jusqu'au prochain month-label).
     */
    function initEventTabs() {
        var tabs = document.querySelectorAll('.opac-event-tabs [data-cat]');
        if (!tabs.length) {
            return;
        }
        var agenda = document.querySelector('.opac-agenda');
        if (!agenda) {
            return;
        }

        function applyFilter(cat) {
            var children = agenda.children;
            var currentLabel = null;
            var labelHasVisible = false;

            function commit() {
                if (currentLabel) {
                    currentLabel.style.display = labelHasVisible ? '' : 'none';
                }
            }

            for (var i = 0; i < children.length; i++) {
                var el = children[i];
                if (el.classList.contains('opac-month-label')) {
                    commit();
                    currentLabel = el;
                    labelHasVisible = false;
                } else if (el.classList.contains('opac-event')) {
                    var match = cat === 'all' || el.classList.contains('opac-cat-' + cat);
                    el.style.display = match ? '' : 'none';
                    if (match) {
                        labelHasVisible = true;
                    }
                }
            }
            commit();
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');
                applyFilter(tab.getAttribute('data-cat'));
            });
        });

        bindTablistKeyboard(tabs);
    }

    /**
     * Header est position: fixed (anti-blur/tremblement WebKit sur sticky).
     * On mesure sa hauteur et on set --opac-header-h utilisee par le CSS
     * comme padding-top sur le main pour eviter que le contenu remonte
     * sous le header. Recalcule au resize (responsive).
     */
    function initStickyHeader() {
        var header = document.querySelector('.wp-site-blocks > header');
        if (!header) return;

        function syncHeight() {
            var h = header.offsetHeight;
            if (h > 0) {
                document.documentElement.style.setProperty('--opac-header-h', h + 'px');
            }
        }

        syncHeight();
        window.addEventListener('resize', syncHeight);

        // Re-mesure apres font load (les fonts peuvent changer la hauteur).
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(syncHeight);
        }
    }

    function initAll() {
        initStickyHeader();
        initCtaDropdown();
        initStageTabs();
        initEventTabs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
