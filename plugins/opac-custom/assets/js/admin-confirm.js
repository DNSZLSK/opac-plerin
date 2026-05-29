/* OPAC Custom - garde-fous suppression + modifications non enregistrees.
 *
 * Confirmations graduees avant la mise en corbeille (douce, reversible),
 * la suppression definitive et le vidage de corbeille (fermes, irreversibles),
 * et les actions groupees. Plus une alerte si on quitte le formulaire fiche
 * avec des modifications non enregistrees. Pensé pour une admin non-technique
 * (Katell) : eviter les pertes de donnees accidentelles.
 *
 * Vanilla JS, charge en pied de page sur les listes et les editeurs des CPTs
 * geres + les inscriptions (cf. OPAC_Admin::enqueue_admin_assets).
 */
( function () {
	'use strict';

	var m         = window.opacConfirm || {};
	var TRASH     = m.trash      || 'Êtes-vous sûr de vouloir mettre cet élément à la corbeille ?';
	var DELETE    = m.del        || 'Supprimer définitivement ? Cette action est irréversible.';
	var EMPTY     = m.emptyTrash || 'Vider la corbeille supprimera définitivement tous les éléments. Continuer ?';
	var BULKTRASH = m.bulkTrash  || 'Mettre les éléments sélectionnés à la corbeille ?';
	var BULKDEL   = m.bulkDelete || 'Supprimer définitivement les éléments sélectionnés ? Cette action est irréversible.';
	var UNSAVED   = m.unsaved    || 'Des modifications ne sont pas enregistrées. Voulez-vous vraiment quitter cette page ?';

	// Autorise la prochaine sortie de page a passer sans alerte "non enregistre"
	// (action de suppression/corbeille deja confirmee, ou enregistrement en cours).
	var allowUnload = false;

	// --- A / B : liens "Corbeille" (doux) et "Supprimer définitivement" (ferme).
	// Couvre les row actions ET le bouton #delete-action de l'editeur, qui
	// portent tous la classe submitdelete. On distingue via le parametre action.
	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest ? e.target.closest( 'a.submitdelete' ) : null;
		if ( ! link ) { return; }
		var href = link.getAttribute( 'href' ) || '';
		var permanent = /[?&]action=delete(&|$)/.test( href );
		if ( window.confirm( permanent ? DELETE : TRASH ) ) {
			allowUnload = true;
		} else {
			e.preventDefault();
		}
	} );

	// --- B : boutons "Vider la corbeille" (haut + bas de liste).
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( 'input[name="delete_all"], input[name="delete_all2"]' ) : null;
		if ( ! btn ) { return; }
		if ( window.confirm( EMPTY ) ) {
			allowUnload = true;
		} else {
			e.preventDefault();
		}
	} );

	// --- C : actions groupees corbeille / suppression definitive.
	var listForm = document.getElementById( 'posts-filter' );
	if ( listForm ) {
		listForm.addEventListener( 'submit', function ( e ) {
			var top    = listForm.querySelector( 'select[name="action"]' );
			var bottom = listForm.querySelector( 'select[name="action2"]' );
			var action = ( top && '-1' !== top.value ) ? top.value : ( bottom ? bottom.value : '-1' );
			if ( 'trash' === action && ! window.confirm( BULKTRASH ) ) {
				e.preventDefault();
			} else if ( 'delete' === action && ! window.confirm( BULKDEL ) ) {
				e.preventDefault();
			}
		} );
	}

	// --- E : garde "modifications non enregistrees" sur le formulaire fiche.
	// Ne s'active que sur l'editeur des CPTs qui rendent la fiche (.opac-fiche).
	var postForm = document.getElementById( 'post' );
	if ( postForm && postForm.querySelector( '.opac-fiche' ) ) {
		var dirty = false;
		var markDirty = function () { dirty = true; };
		postForm.addEventListener( 'input', markDirty );
		postForm.addEventListener( 'change', markDirty );
		postForm.addEventListener( 'submit', function () { allowUnload = true; } );

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty && ! allowUnload ) {
				e.preventDefault();
				e.returnValue = UNSAVED; // texte ignore par les navigateurs modernes (invite generique)
				return UNSAVED;
			}
		} );
	}
}() );
