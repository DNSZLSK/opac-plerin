/**
 * OPAC Custom - Comportements de la fiche CPT (atelier / éphémère).
 *
 * 1. Animateur : révèle le champ de saisie libre uniquement quand l'option
 *    « Autre / saisie libre » est sélectionnée dans le déroulant.
 * 2. Période (éphémère) : pré-coche la saison probable d'après la date de
 *    début. Pure suggestion, jamais imposée : on ne touche à rien si une
 *    période est déjà cochée, l'équipe garde la main sur les cas limites
 *    (les dates exactes des vacances scolaires varient chaque année et par zone).
 */
( function () {
	'use strict';

	// --- 1. Saisie libre d'animateur, révélée à la demande.
	var selects = document.querySelectorAll( '.opac-person-select' );
	Array.prototype.forEach.call( selects, function ( select ) {
		var libre = select.parentNode.querySelector( '.js-opac-animator-libre' );
		if ( ! libre ) {
			return;
		}
		var sync = function () {
			libre.style.display = ( '__libre__' === select.value ) ? '' : 'none';
		};
		select.addEventListener( 'change', sync );
		sync();
	} );

	// --- 2. Suggestion de période d'après la date de début.
	var dateInput = document.getElementById( 'opac_date_debut' );
	var checkboxes = document.querySelectorAll( 'input[name="opac_tax_opac_period[]"]' );
	if ( ! dateInput || ! checkboxes.length ) {
		return;
	}

	// Mois (1-12) -> slug de vacances scolaires. Découpage volontairement large
	// et approximatif : c'est une aide à la saisie, pas une vérité calendaire.
	function slugForMonth( m ) {
		if ( m >= 10 && m <= 11 ) {
			return 'automne';
		}
		if ( 12 === m || m <= 2 ) {
			return 'hiver';
		}
		if ( m >= 3 && m <= 5 ) {
			return 'printemps';
		}
		return 'ete'; // juin -> septembre
	}

	dateInput.addEventListener( 'change', function () {
		if ( ! dateInput.value ) {
			return;
		}
		// Ne jamais écraser un choix manuel : on ne suggère que si rien n'est coché.
		var anyChecked = Array.prototype.some.call( checkboxes, function ( c ) {
			return c.checked;
		} );
		if ( anyChecked ) {
			return;
		}
		var parts = dateInput.value.split( '-' ); // AAAA-MM-JJ
		if ( parts.length < 2 ) {
			return;
		}
		var slug = slugForMonth( parseInt( parts[1], 10 ) );
		Array.prototype.forEach.call( checkboxes, function ( c ) {
			if ( c.getAttribute( 'data-term-slug' ) === slug ) {
				c.checked = true;
			}
		} );
	} );
} )();
