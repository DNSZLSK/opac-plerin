/* OPAC Custom - « Copier pour Excel » sur la liste des inscriptions.
 *
 * La secretaire garde SES classeurs Excel de suivi. Ici elle coche une ou
 * plusieurs inscriptions (ou rien = toute la page affichee), clique sur le
 * bouton, puis Ctrl+V sur une ligne vide de son onglet : les lignes se collent
 * dans les bonnes colonnes.
 *
 * La ligne tabulee de chaque inscription est pre-calculee cote serveur
 * (OPAC_Inscriptions::excel_tsv_line) et deposee dans un <span.opac-insc-tsv
 * data-tsv> cache de la cellule Nom. Ici on ne fait que rassembler les lignes
 * cochees et les mettre dans le presse-papier.
 *
 * Vanilla JS, charge en pied de page sur la liste des inscriptions
 * (cf. OPAC_Admin::enqueue_admin_assets).
 */
( function () {
	'use strict';

	var btn = document.getElementById( 'opac-copy-excel' );
	if ( ! btn ) {
		return;
	}

	var t = window.opacCopyExcel || {};
	var LABEL      = t.label     || 'Copier pour Excel';
	var EMPTY      = t.empty     || 'Rien à copier';
	var COPIED_SEL = t.copiedSel || '%d ligne(s) copiée(s)';
	var COPIED_ALL = t.copiedAll || '%d copiée(s), collez dans Excel';
	var FAILED     = t.failed    || 'Copie impossible';

	// Rassemble les lignes TSV : les cochees, ou toute la page si rien n'est coche.
	function collect() {
		var all = document.querySelectorAll( '#the-list input[name="post[]"]' );
		var checked = document.querySelectorAll( '#the-list input[name="post[]"]:checked' );
		var scope = checked.length ? checked : all;
		var lines = [];
		Array.prototype.forEach.call( scope, function ( cb ) {
			var row = cb.closest( 'tr' );
			var holder = row ? row.querySelector( '.opac-insc-tsv' ) : null;
			if ( holder && typeof holder.getAttribute( 'data-tsv' ) === 'string' ) {
				lines.push( holder.getAttribute( 'data-tsv' ) );
			}
		} );
		// Fin de ligne Windows : Excel colle une ligne par saut.
		return { text: lines.join( '\r\n' ), count: lines.length, selection: checked.length > 0 };
	}

	// Retour visuel bref sur le libelle du bouton (pas de popup pour une admin
	// non technique : le bouton confirme lui-meme puis reprend son libelle).
	function flash( msg ) {
		btn.textContent = msg;
		window.setTimeout( function () {
			btn.textContent = LABEL;
		}, 2500 );
	}

	// Copie de secours quand l'API presse-papier n'est pas dispo (contexte non
	// securise, ex. http en local) : textarea hors ecran + execCommand.
	function legacyCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.top = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( ta );
		return ok;
	}

	btn.addEventListener( 'click', function () {
		var data = collect();
		if ( ! data.count ) {
			flash( EMPTY );
			return;
		}
		var done = function () {
			var tpl = data.selection ? COPIED_SEL : COPIED_ALL;
			flash( tpl.replace( '%d', data.count ) );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( data.text ).then( done, function () {
				flash( legacyCopy( data.text ) ? tplDone( data ) : FAILED );
			} );
			return;
		}
		flash( legacyCopy( data.text ) ? tplDone( data ) : FAILED );
	} );

	// Libelle de confirmation (reutilise par la voie de secours).
	function tplDone( data ) {
		var tpl = data.selection ? COPIED_SEL : COPIED_ALL;
		return tpl.replace( '%d', data.count );
	}
} )();
