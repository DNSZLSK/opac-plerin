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
