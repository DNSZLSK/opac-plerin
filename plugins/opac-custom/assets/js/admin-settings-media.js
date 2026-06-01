/* OPAC Custom - selecteur de fichier (mediatheque) pour les champs media
 * de la page OPAC Reglages (ex : PDF charte des ateliers).
 *
 * Un bouton ouvre wp.media, le choix renseigne l'input URL du champ et
 * affiche le lien "Retirer". Calque de admin-media.js (image fiche CPT),
 * adapte a un champ URL et filtre sur les PDF.
 */
( function ( $ ) {
	'use strict';

	var labels = window.opacSettingsMedia || {};

	$( document ).on( 'click', '.opac-media-choose', function ( e ) {
		e.preventDefault();

		var $field = $( this ).closest( '.opac-media-field' );
		var frame  = wp.media( {
			title:    labels.title || 'Choisir un fichier',
			button:   { text: labels.button || 'Utiliser ce fichier' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			$field.find( '.opac-media-url' ).val( att.url );
			$field.find( '.opac-media-remove' ).show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.opac-media-remove', function ( e ) {
		e.preventDefault();

		var $field = $( this ).closest( '.opac-media-field' );
		$field.find( '.opac-media-url' ).val( '' );
		$( this ).hide();
	} );

}( jQuery ) );
