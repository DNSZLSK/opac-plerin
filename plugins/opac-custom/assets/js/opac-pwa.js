/**
 * OPAC - Enregistrement du service worker (PWA). Cote public uniquement.
 * L'URL du service worker est fournie par wp_localize_script (opacPwa.sw).
 * Enregistrement silencieux : aucune degradation si le navigateur ne supporte
 * pas les service workers ou si l'enregistrement echoue.
 */
(function () {
    if (!('serviceWorker' in navigator)) {
        return;
    }
    var url = (window.opacPwa && window.opacPwa.sw) ? window.opacPwa.sw : '/opac-sw.js';
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(url).catch(function () {
            /* silencieux : la PWA est un plus, jamais un bloquant */
        });
    });
})();

/* ------------------------------------------------------------------
 * Bandeau "Installer l'app" (footer, mobile/tablette). Revele
 * .opac-pwa-install selon la plateforme : Android Chromium via
 * beforeinstallprompt (le bouton declenche l'invite native), iOS et
 * Firefox Android via une instruction manuelle (data-hint), faute
 * d'install programmatique chez eux. Masque si l'app tourne deja en
 * standalone. Best-effort : si rien ne matche, le bandeau reste masque.
 * ------------------------------------------------------------------ */
(function () {
    var box = document.querySelector('.opac-pwa-install');
    if (!box) { return; }

    // Deja installe (lance depuis l'ecran d'accueil) : ne rien proposer.
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
                     window.navigator.standalone === true;
    if (standalone) { return; }

    function reveal(mode, hint) {
        box.setAttribute('data-mode', mode);
        if (hint) { box.setAttribute('data-hint', hint); }
        box.classList.add('is-visible');
    }

    var ua = window.navigator.userAgent || '';
    // iOS "vrai", ou iPadOS 13+ (qui se declare Macintosh mais est tactile).
    var isIOS = /iP(hone|ad|od)/.test(ua) ||
                (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);

    if (isIOS) {
        // Pas d'install programmatique sur iOS : instruction "passer par
        // Safari", seul autorise a installer une PWA (restriction Apple).
        // La formulation vaut aussi depuis Chrome/Firefox/Brave iOS.
        reveal('manual', 'ios');
        return;
    }

    // Firefox Android n'emet jamais beforeinstallprompt : l'install passe par
    // le menu du navigateur, on affiche l'instruction correspondante.
    if (/Android/i.test(ua) && /Firefox\//.test(ua)) {
        reveal('manual', 'firefox');
        return;
    }

    // Android / Chromium : le navigateur n'emet beforeinstallprompt que si le
    // site est installable. On ne montre le bouton qu'a ce moment-la.
    var deferred = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        reveal('android');
    });

    var btn = box.querySelector('.opac-pwa-install-btn');
    if (btn) {
        btn.addEventListener('click', function () {
            if (!deferred) { return; }
            deferred.prompt();
            deferred.userChoice.then(function () {
                deferred = null;
                box.classList.remove('is-visible'); // installe ou refuse : on retire
            });
        });
    }

    // App installee -> plus besoin du bandeau.
    window.addEventListener('appinstalled', function () {
        box.classList.remove('is-visible');
    });
})();
