/* OPAC Custom - galerie multiple integree aux fiches metier. */
( function ( $ ) {
	'use strict';

	var labels = window.opacGallery || {};

	function refreshEmptyState( $field ) {
		$field.find( '.opac-gallery-empty' ).toggle( $field.find( '.opac-gallery-selection-item' ).length === 0 );
	}

	function attachmentUrl( attachment ) {
		return attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
	}

	function appendAttachment( $field, attachment ) {
		if ( $field.find( '.opac-gallery-selection-item[data-id="' + attachment.id + '"]' ).length ) {
			return;
		}

		var title = attachment.title || labels.untitled || 'Photo sans titre';
		var $item = $( '<li>', {
			'class': 'opac-gallery-selection-item',
			'data-id': attachment.id
		} );

		$item.append( $( '<span>', {
			'class': 'dashicons dashicons-move opac-gallery-drag',
			'aria-hidden': 'true'
		} ) );
		$item.append( $( '<img>', { src: attachmentUrl( attachment ), alt: '' } ) );
		$item.append( $( '<span>', { 'class': 'opac-gallery-image-title', text: title } ) );
		$item.append( $( '<button>', {
			type: 'button',
			'class': 'button-link-delete opac-gallery-remove',
			text: labels.remove || 'Retirer'
		} ) );
		$item.append( $( '<input>', {
			type: 'hidden',
			name: 'opac_gallery_ids[]',
			value: attachment.id
		} ) );

		$field.find( '.opac-gallery-selection' ).append( $item );
	}

	$( '.opac-gallery-selection' ).sortable( {
		items: '> .opac-gallery-selection-item',
		handle: '.opac-gallery-drag',
		axis: 'x',
		placeholder: 'opac-gallery-sort-placeholder'
	} );

	$( document ).on( 'click', '.opac-gallery-add', function ( event ) {
		event.preventDefault();

		var $field = $( this ).closest( '.opac-gallery-field' );
		var frame = wp.media( {
			title: labels.title || 'Choisir les photos du carrousel',
			button: { text: labels.button || 'Ajouter au carrousel' },
			library: { type: 'image' },
			multiple: true
		} );

		frame.on( 'select', function () {
			frame.state().get( 'selection' ).each( function ( attachment ) {
				appendAttachment( $field, attachment.toJSON() );
			} );
			refreshEmptyState( $field );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.opac-gallery-remove', function ( event ) {
		event.preventDefault();
		var $field = $( this ).closest( '.opac-gallery-field' );
		$( this ).closest( '.opac-gallery-selection-item' ).remove();
		refreshEmptyState( $field );
	} );

}( jQuery ) );
