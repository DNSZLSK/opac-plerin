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
 * .opac-pwa-install selon la plateforme : Android via beforeinstallprompt
 * (le bouton declenche l'invite native), iOS via une instruction (Apple
 * interdit l'install programmatique). Masque si l'app tourne deja en
 * standalone. Best-effort : si rien ne matche, le bandeau reste masque.
 * ------------------------------------------------------------------ */
(function () {
    var box = document.querySelector('.opac-pwa-install');
    if (!box) { return; }

    // Deja installe (lance depuis l'ecran d'accueil) : ne rien proposer.
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
                     window.navigator.standalone === true;
    if (standalone) { return; }

    function reveal(mode) {
        box.setAttribute('data-mode', mode);
        box.classList.add('is-visible');
    }

    var ua = window.navigator.userAgent || '';
    // iOS "vrai", ou iPadOS 13+ (qui se declare Macintosh mais est tactile).
    var isIOS = /iP(hone|ad|od)/.test(ua) ||
                (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);

    if (isIOS) {
        // Pas d'install programmatique sur iOS : on montre l'instruction. Seul
        // Safari installe une PWA (restriction Apple). Les navigateurs tiers
        // identifiables (Chrome=CriOS, Firefox=FxiOS, Edge=EdgiOS, Opera=OPiOS,
        // app Google=GSA, DuckDuckGo) recoivent "ouvrez dans Safari". Brave se
        // deguise en Safari (pas de token propre) : il tombe dans "safari", dont
        // le message mentionne deja Safari, donc l'utilisateur est quand meme
        // oriente correctement.
        var iosOther = /CriOS|FxiOS|EdgiOS|OPiOS|GSA|DuckDuckGo/i.test(ua);
        box.setAttribute('data-ios', iosOther ? 'other' : 'safari');
        reveal('ios');
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
