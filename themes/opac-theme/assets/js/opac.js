/**
 * OPAC Plérin - frontend interactions
 *
 * P0 : dropdown CTA "S'inscrire" (toggle, click outside, escape, A11y).
 * Modules suivants : filtres agenda (M5), validation form inscription (M7).
 */

(function () {
    'use strict';

    // Signature dev dans la console (F12). %c applique du CSS inline au texte.
    console.log(
        '%cDNSZLSK%c  kewin.io · OPAC Plérin',
        'color:#5bc0de;font-size:22px;font-weight:bold;letter-spacing:3px;',
        'color:#5bc0de;font-size:12px;'
    );

    /**
     * Disclosure "S'inscrire". Le <details> natif gere toggle, clavier et
     * repli sans JS. La variante historique div + button reste prise en charge
     * car un template-part personnalise en base WordPress peut continuer a
     * primer sur parts/header.html apres un deploiement de fichiers.
     */
    function initCtaDropdown() {
        var details = document.querySelectorAll('details.opac-cta');
        var legacy = document.querySelectorAll('div.opac-cta');

        if (!details.length && !legacy.length) {
            return;
        }

        legacy.forEach(function (wrapper, idx) {
            var btn = wrapper.querySelector('.opac-cta-btn');
            var panel = wrapper.querySelector('.opac-cta-dd');
            if (!btn || !panel) {
                return;
            }

            var panelId = panel.id || 'opac-cta-dd-' + idx;
            panel.id = panelId;
            panel.removeAttribute('role');
            panel.querySelectorAll('[role="menuitem"]').forEach(function (item) {
                item.removeAttribute('role');
            });
            btn.removeAttribute('aria-haspopup');
            btn.setAttribute('aria-controls', panelId);
            btn.setAttribute('aria-expanded', 'false');
            wrapper.setAttribute('data-open', 'false');

            btn.addEventListener('click', function () {
                var open = wrapper.getAttribute('data-open') === 'true';
                wrapper.setAttribute('data-open', open ? 'false' : 'true');
                btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            });
        });

        document.addEventListener('click', function (e) {
            details.forEach(function (d) {
                if (d.open && !d.contains(e.target)) {
                    d.open = false;
                }
            });
            legacy.forEach(function (wrapper) {
                if (wrapper.getAttribute('data-open') === 'true' && !wrapper.contains(e.target)) {
                    wrapper.setAttribute('data-open', 'false');
                    var btn = wrapper.querySelector('.opac-cta-btn');
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            details.forEach(function (d) {
                if (d.open) {
                    d.open = false;
                    var s = d.querySelector('summary');
                    if (s) {
                        s.focus();
                    }
                }
            });
            legacy.forEach(function (wrapper) {
                if (wrapper.getAttribute('data-open') === 'true') {
                    wrapper.setAttribute('data-open', 'false');
                    var btn = wrapper.querySelector('.opac-cta-btn');
                    if (btn) {
                        btn.setAttribute('aria-expanded', 'false');
                        btn.focus();
                    }
                }
            });
        });
    }

    /**
     * Applique l'etat de selection a un radiogroup de filtres : le radio actif
     * recoit aria-checked=true, la classe .is-active et tabindex=0 ; les autres
     * aria-checked=false et tabindex=-1 (roving : un seul radio dans l'ordre de
     * tabulation, comme l'exige le pattern WAI-ARIA radio). Le filtrage effectif
     * des cartes reste dans le handler de clic appelant.
     */
    function setActiveRadio(radios, active) {
        radios.forEach(function (r) {
            var on = (r === active);
            r.classList.toggle('is-active', on);
            r.setAttribute('aria-checked', on ? 'true' : 'false');
            r.tabIndex = on ? 0 : -1;
        });
    }

    /**
     * Navigation clavier d'un radiogroup de filtres (fleches gauche/droite ET
     * haut/bas, Home, End) avec rotation + focus auto. Active aussi le radio
     * focuse au passage (selection qui suit le focus, pattern WAI-ARIA radio) :
     * le .click() declenche le handler qui pose l'etat via setActiveRadio et
     * filtre. Le roving tabindex est donc mis a jour a chaque deplacement.
     */
    function bindRadioKeyboard(radios) {
        radios.forEach(function (radio, idx) {
            radio.addEventListener('keydown', function (e) {
                var target = null;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    target = radios[(idx + 1) % radios.length];
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    target = radios[(idx - 1 + radios.length) % radios.length];
                } else if (e.key === 'Home') {
                    target = radios[0];
                } else if (e.key === 'End') {
                    target = radios[radios.length - 1];
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
     * Semantique ARIA des filtres posee ICI, au demarrage du JS : sans JS les
     * boutons ne font rien, on evite donc des commandes "radio" visibles mais
     * inertes (le CSS masque les barres tant que <html> n'a pas la classe js).
     * role=radiogroup sur le conteneur, role=radio + aria-checked + roving
     * tabindex sur chaque bouton, d'apres la classe .is-active initiale.
     */
    function setupRadioGroup(group, radios) {
        if (group) {
            group.setAttribute('role', 'radiogroup');
        }
        radios.forEach(function (r) {
            r.setAttribute('role', 'radio');
            var on = r.classList.contains('is-active');
            r.setAttribute('aria-checked', on ? 'true' : 'false');
            r.tabIndex = on ? 0 : -1;
        });
    }

    /**
     * Zone live (aria-live=polite, visuellement masquee) inseree apres le groupe
     * de filtres : annonce le nombre de resultats apres chaque filtrage, pour
     * qu'un lecteur d'ecran sache que la liste a change (la bascule de
     * aria-checked seule ne dit pas combien d'elements restent visibles).
     */
    function makeLiveRegion(afterEl) {
        var live = document.createElement('div');
        live.className = 'opac-visually-hidden';
        live.setAttribute('aria-live', 'polite');
        if (afterEl && afterEl.parentNode) {
            afterEl.parentNode.insertBefore(live, afterEl.nextSibling);
        } else {
            document.body.appendChild(live);
        }
        return live;
    }

    function announceCount(live, n) {
        if (!live) {
            return;
        }
        live.textContent = n + ' résultat' + (n > 1 ? 's' : '') + ' affiché' + (n > 1 ? 's' : '');
    }

    /**
     * Onglets de filtrage des ephemeres par periode (page archive). Le bloc
     * serveur opac/ephemeres-list pose la classe opac-period-<slug> sur chaque
     * carte .opac-stage-card ; on show/hide selon le tab actif.
     */
    function initStageTabs() {
        var tabs = document.querySelectorAll('.opac-stage-tabs [data-period]');
        if (!tabs.length) {
            return;
        }
        var cards = document.querySelectorAll('.opac-stages-list > .opac-stage-card');
        if (!cards.length) {
            return;
        }

        var group = document.querySelector('.opac-stage-tabs');
        setupRadioGroup(group, tabs);
        var live = makeLiveRegion(group);

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setActiveRadio(tabs, tab);

                var period = tab.getAttribute('data-period');
                var visible = 0;
                cards.forEach(function (card) {
                    var match = period === 'all' || card.classList.contains('opac-period-' + period);
                    // Classe (pas style.display) : .opac-stage-card a display:grid
                    // !important, qu'un display:none inline ne battrait pas.
                    card.classList.toggle('opac-hidden', !match);
                    if (match) {
                        visible++;
                    }
                });
                announceCount(live, visible);

                // Separateur "Ephemeres passes" : visible seulement s'il reste
                // au moins une carte a venir ET une carte passee apres filtrage.
                var sep = document.querySelector('.opac-stages-list > .opac-stages-sep');
                if (sep) {
                    var vUp = false, vPast = false;
                    cards.forEach(function (card) {
                        if (card.classList.contains('opac-hidden')) { return; }
                        if (card.classList.contains('is-past')) { vPast = true; } else { vUp = true; }
                    });
                    sep.classList.toggle('opac-hidden', !(vUp && vPast));
                }
            });
        });

        bindRadioKeyboard(tabs);
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

        var group = document.querySelector('.opac-event-tabs');
        setupRadioGroup(group, tabs);
        var live = makeLiveRegion(group);

        function applyFilter(cat) {
            var children = agenda.children;
            var currentLabel = null;
            var labelHasVisible = false;
            var visible = 0;

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
                        visible++;
                    }
                }
            }
            commit();
            return visible;
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setActiveRadio(tabs, tab);
                announceCount(live, applyFilter(tab.getAttribute('data-cat')));
            });
        });

        bindRadioKeyboard(tabs);
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
     * Galerie des realisations (fiches atelier, ephemere et evenement) :
     * - carrousel horizontal avec fleches et swipe natif.
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
        // Rend inerte tout ce qui est derriere l'overlay (les freres de la
        // lightbox dans le body) : le contenu de fond n'est plus focusable ni
        // expose aux lecteurs d'ecran tant que le dialog est ouvert. Complete
        // aria-modal, dont le support reste inegal.
        //
        // On ne memorise QUE les elements que la lightbox a elle-meme rendus
        // inertes (on saute ceux deja inertes) et on ne retire inert qu'a ceux-la
        // a la fermeture : un element inerte pour une autre raison garde son etat.
        var inerted = [];
        function setBackgroundInert(on) {
            if (on) {
                inerted = [];
                Array.prototype.forEach.call(document.body.children, function (el) {
                    if (el === lb || el.hasAttribute('inert')) {
                        return;
                    }
                    el.setAttribute('inert', '');
                    inerted.push(el);
                });
            } else {
                inerted.forEach(function (el) { el.removeAttribute('inert'); });
                inerted = [];
            }
        }
        function open(i) {
            // On memorise la vignette declencheuse elle-meme (items[i]), pas
            // document.activeElement : un clic souris sur un <a>/<button> ne le
            // focalise pas dans tous les navigateurs (Safari), le focus reviendrait
            // alors au body. items[i] garantit le retour sur la bonne vignette.
            lastFocus = items[i] || document.activeElement;
            show(i);
            lb.classList.add('is-open');
            // Verrou de scroll : empeche le fond de defiler derriere l'overlay.
            document.body.classList.add('opac-no-scroll');
            setBackgroundInert(true);
            lb.querySelector('.opac-lightbox-close').focus();
        }
        function close() {
            lb.classList.remove('is-open');
            document.body.classList.remove('opac-no-scroll');
            setBackgroundInert(false);
            lbImg.setAttribute('src', '');
            if (lastFocus && lastFocus.focus) {
                lastFocus.focus();
            }
        }

        items.forEach(function (el, i) {
            // .opac-gallery-item est un lien vers l'image pleine taille (repli
            // sans JS) : on annule la navigation pour ouvrir la lightbox a la place.
            el.addEventListener('click', function (e) { e.preventDefault(); open(i); });
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
            else if (e.key === 'Tab') {
                // Piege de focus : Tab cyclique sur les commandes visibles de la
                // lightbox (offsetParent != null exclut prev/next masques en bord
                // de galerie). Empeche le focus de s'echapper vers le fond inerte.
                var f = Array.prototype.filter.call(
                    lb.querySelectorAll('button'),
                    function (el) { return el.offsetParent !== null; }
                );
                if (!f.length) { return; }
                var first = f[0], last = f[f.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // Swipe horizontal sur la lightbox (mobile) : photo precedente / suivante.
        // Reutilise show()/current. Ignore le multi-touch (pinch) et les gestes
        // verticaux (scroll), pour ne declencher que sur un vrai swipe lateral.
        var touchX = null, touchY = null;
        lb.addEventListener('touchstart', function (e) {
            if (e.touches.length > 1) { touchX = null; return; }
            touchX = e.touches[0].clientX;
            touchY = e.touches[0].clientY;
        }, { passive: true });
        lb.addEventListener('touchend', function (e) {
            if (touchX === null || !lb.classList.contains('is-open')) { return; }
            var t = e.changedTouches[0];
            var dx = t.clientX - touchX, dy = t.clientY - touchY;
            touchX = null;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                show(dx < 0 ? current + 1 : current - 1);
            }
        }, { passive: true });
    }

    /**
     * Rend une famille de cards entierement cliquable : un clic n'importe ou
     * sur la carte suit le lien retourne par getLink(card). Les vrais
     * liens/boutons internes (titre, "S'inscrire", "Details") gardent leur
     * comportement propre, et on ne navigue pas pendant une selection de texte.
     * Le lien cible reste un <a> natif (navigation clavier / lecteurs d'ecran).
     */
    function bindCardLinks(selector, getLink) {
        document.querySelectorAll(selector).forEach(function (card) {
            var link = getLink(card);
            if (!link) {
                return;
            }
            card.classList.add('is-clickable');
            card.addEventListener('click', function (e) {
                // Laisse les liens/boutons internes agir seuls.
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

    /**
     * Cartes entierement cliquables vers leur fiche, meme pattern pour les 3 :
     * - ephemeres (.opac-stage-card)            : lien = titre .opac-stage-name a
     * - ateliers + actualites (.opac-card hors ephemeres) : lien = titre
     *   .opac-card-name a, sinon le "Details ->" du footer .opac-card-footer a
     */
    function initCardLinks() {
        bindCardLinks('.opac-stage-card', function (card) {
            return card.querySelector('.opac-stage-name a');
        });
        bindCardLinks('.opac-card:not(.opac-stage-card)', function (card) {
            return card.querySelector('.opac-card-name a') || card.querySelector('.opac-card-footer a');
        });
    }

    /**
     * Selecteur de saison (archive ephemeres) : recharge la page au changement
     * du menu deroulant et masque le bouton « Afficher » (qui sert de repli
     * quand le JS est absent).
     */
    function initSaisonSelect() {
        var form = document.querySelector('.opac-saison-form');
        if (!form) {
            return;
        }
        var select = form.querySelector('.opac-saison-dropdown');
        if (!select) {
            return;
        }
        var go = form.querySelector('.opac-saison-go');
        if (go) {
            go.hidden = true;
        }
        select.addEventListener('change', function () {
            form.submit();
        });
    }

    function initAll() {
        initStickyHeader();
        initCtaDropdown();
        initStageTabs();
        initEventTabs();
        initSaisonSelect();
        initGallery();
        initCardLinks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
