/**
 * OPAC Custom - Recherche au fil de la frappe (liste des inscriptions).
 *
 * Ameliore la barre de recherche native de la liste admin : au lieu de taper
 * puis cliquer « Rechercher », on relance la recherche automatiquement apres une
 * courte pause de frappe. On re-soumet le formulaire complet (#posts-filter),
 * donc le statut selectionne et le filtre mois restent pris en compte, et la
 * recherche reste cote serveur (fonctionne sur toutes les inscriptions, pas
 * seulement la page affichee).
 *
 * Comme chaque relance recharge la page, on memorise (sessionStorage) qu'une
 * recherche live vient d'etre declenchee : au rechargement, on redonne le focus
 * au champ (curseur en fin), MEME si le champ est vide (cas : on efface tout).
 * Le focus n'est restaure que dans ce cas, jamais lors d'une navigation normale.
 */
( function () {
	'use strict';

	var DELAY = 400; // ms de pause de frappe avant de relancer la recherche.
	var FLAG  = 'opacInscLiveSearch';

	var input = document.getElementById( 'post-search-input' );
	if ( ! input ) {
		return;
	}
	var form = input.form || document.getElementById( 'posts-filter' );
	if ( ! form ) {
		return;
	}

	// Restaure le focus apres un rechargement declenche par la recherche live.
	try {
		if ( window.sessionStorage && sessionStorage.getItem( FLAG ) === '1' ) {
			sessionStorage.removeItem( FLAG );
			input.focus();
			// Replace le curseur en fin (le ré-assignement marche aussi si vide).
			var kept = input.value;
			input.value = '';
			input.value = kept;
		}
	} catch ( e ) {}

	var timer = null;
	var last = input.value;

	input.addEventListener( 'input', function () {
		if ( input.value === last ) {
			return;
		}
		last = input.value;

		if ( timer ) {
			window.clearTimeout( timer );
		}
		timer = window.setTimeout( function () {
			try {
				if ( window.sessionStorage ) {
					sessionStorage.setItem( FLAG, '1' );
				}
			} catch ( e ) {}
			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		}, DELAY );
	} );
}() );
