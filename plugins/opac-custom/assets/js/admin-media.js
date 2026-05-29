/* OPAC Custom - selecteur d'image integre au formulaire fiche CPT.
 *
 * Remplace la boite native "Image mise en avant" : un bouton ouvre la
 * mediatheque wp.media, le choix renseigne le hidden input opac_thumbnail_id
 * (sauvegarde en image mise en avant cote PHP) et met a jour l'apercu.
 */
( function ( $ ) {
	'use strict';

	var labels = window.opacMedia || {};

	$( document ).on( 'click', '.opac-image-choose', function ( e ) {
		e.preventDefault();

		var $field = $( this ).closest( '.opac-image-field' );
		var frame  = wp.media( {
			title:    labels.title || 'Choisir une image',
			button:   { text: labels.button || 'Utiliser cette image' },
			library:  { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;

			$field.find( 'input[name="opac_thumbnail_id"]' ).val( att.id );
			$field.find( '.opac-image-preview' )
				.removeClass( 'is-empty' )
				.html( $( '<img>', { src: url, alt: '' } ) );
			$field.find( '.opac-image-remove' ).show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.opac-image-remove', function ( e ) {
		e.preventDefault();

		var $field = $( this ).closest( '.opac-image-field' );
		$field.find( 'input[name="opac_thumbnail_id"]' ).val( '' );
		$field.find( '.opac-image-preview' ).addClass( 'is-empty' ).empty();
		$( this ).hide();
	} );

}( jQuery ) );
