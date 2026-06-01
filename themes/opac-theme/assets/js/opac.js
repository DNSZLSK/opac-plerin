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

    /**
     * Galerie des realisations (fiche atelier) :
     * - bouton "Voir tout (N)" : deplie les vignettes en surplus (cap 4).
     * - lightbox maison (sans librairie) : clic sur une vignette = grande image
     *   en overlay, navigation fleches/Prev/Next, fermeture Echap / clic fond / X.
     *   A11y : role=dialog, focus sur Fermer a l'ouverture, focus rendu a l'appel.
     */
    function initGallery() {
        // Carrousel : fleches qui defilent le track (swipe natif via overflow-x).
        document.querySelectorAll('.opac-gallery-carousel').forEach(function (carousel) {
            var track = carousel.querySelector('.opac-gallery-track');
            var prev = carousel.querySelector('.opac-gallery-prev');
            var next = carousel.querySelector('.opac-gallery-next');
            if (!track || !prev || !next) {
                return;
            }
            function update() {
                var noScroll = track.scrollWidth <= track.clientWidth + 1;
                prev.hidden = noScroll || track.scrollLeft <= 1;
                next.hidden = noScroll || track.scrollLeft >= (track.scrollWidth - track.clientWidth - 1);
            }
            function pageScroll(dir) {
                track.scrollBy({ left: dir * Math.round(track.clientWidth * 0.9), behavior: 'smooth' });
            }
            prev.addEventListener('click', function () { pageScroll(-1); });
            next.addEventListener('click', function () { pageScroll(1); });
            track.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            update();
        });

        var items = Array.prototype.slice.call(document.querySelectorAll('.opac-gallery-item'));
        if (!items.length) {
            return;
        }

        // Construit la lightbox une seule fois.
        var lb = document.createElement('div');
        lb.className = 'opac-lightbox';
        lb.setAttribute('role', 'dialog');
        lb.setAttribute('aria-modal', 'true');
        lb.setAttribute('aria-label', 'Galerie des réalisations');
        lb.innerHTML =
            '<button type="button" class="opac-lightbox-btn opac-lightbox-close" aria-label="Fermer">×</button>' +
            '<button type="button" class="opac-lightbox-btn opac-lightbox-prev" aria-label="Photo précédente">‹</button>' +
            '<figure class="opac-lightbox-fig"><img alt="" /><figcaption class="opac-lightbox-caption"></figcaption></figure>' +
            '<button type="button" class="opac-lightbox-btn opac-lightbox-next" aria-label="Photo suivante">›</button>';
        document.body.appendChild(lb);

        var lbImg = lb.querySelector('img');
        var lbCap = lb.querySelector('.opac-lightbox-caption');
        var current = 0;
        var lastFocus = null;

        function show(i) {
            current = (i + items.length) % items.length;
            var el = items[current];
            lbImg.setAttribute('src', el.getAttribute('data-full') || '');
            var cap = el.getAttribute('data-caption') || '';
            lbImg.setAttribute('alt', cap);
            lbCap.textContent = cap;
            lbCap.style.display = cap ? '' : 'none';
        }
        function open(i) {
            lastFocus = document.activeElement;
            show(i);
            lb.classList.add('is-open');
            lb.querySelector('.opac-lightbox-close').focus();
        }
        function close() {
            lb.classList.remove('is-open');
            lbImg.setAttribute('src', '');
            if (lastFocus && lastFocus.focus) {
                lastFocus.focus();
            }
        }

        items.forEach(function (el, i) {
            el.addEventListener('click', function () { open(i); });
        });
        lb.querySelector('.opac-lightbox-close').addEventListener('click', close);
        lb.querySelector('.opac-lightbox-prev').addEventListener('click', function () { show(current - 1); });
        lb.querySelector('.opac-lightbox-next').addEventListener('click', function () { show(current + 1); });
        lb.addEventListener('click', function (e) { if (e.target === lb) { close(); } });
        document.addEventListener('keydown', function (e) {
            if (!lb.classList.contains('is-open')) {
                return;
            }
            if (e.key === 'Escape') { close(); }
            else if (e.key === 'ArrowLeft') { show(current - 1); }
            else if (e.key === 'ArrowRight') { show(current + 1); }
        });
    }

    /**
     * Cartes ephemeres entierement cliquables : un clic n'importe ou sur la
     * carte suit le lien du titre, vers la fiche. Les vrais liens/boutons
     * internes (titre, "S'inscrire") gardent leur comportement propre, et le
     * titre reste un <a> natif (navigation clavier / lecteurs d'ecran).
     */
    function initCardLinks() {
        var cards = document.querySelectorAll('.opac-stage-card');
        if (!cards.length) {
            return;
        }
        cards.forEach(function (card) {
            var link = card.querySelector('.opac-stage-name a');
            if (!link) {
                return;
            }
            card.classList.add('is-clickable');
            card.addEventListener('click', function (e) {
                // Laisse les liens/boutons internes (titre, S'inscrire) agir seuls.
                if (e.target.closest('a, button')) {
                    return;
                }
                // Ne navigue pas si l'utilisateur est en train de selectionner du texte.
                if (window.getSelection && String(window.getSelection())) {
                    return;
                }
                link.click();
            });
        });
    }

    function initAll() {
        initStickyHeader();
        initCtaDropdown();
        initEventTabs();
        initGallery();
        initCardLinks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
