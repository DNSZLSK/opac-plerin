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
	var labels = window.opacFiche || {};

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

	// --- 2. Cohérence des plages, horaires et séances répétées.
	var postForm = document.getElementById( 'post' );
	if ( postForm && postForm.querySelector( '.opac-fiche' ) ) {
		var valueOf = function ( row, suffix ) {
			var input = row.querySelector( 'input[name$="[' + suffix + ']"]' );
			return input ? input.value : '';
		};

		var validateSchedule = function () {
			var startDate = document.getElementById( 'opac_date_debut' );
			var endDate = document.getElementById( 'opac_date_fin' );
			var eventStart = document.getElementById( 'opac_date_event' );
			var eventEnd = document.getElementById( 'opac_date_event_fin' );
			var ranges = [ [ startDate, endDate ], [ eventStart, eventEnd ] ];

			ranges.forEach( function ( range ) {
				if ( range[ 1 ] ) {
					range[ 1 ].setCustomValidity( '' );
					if ( range[ 0 ] && range[ 0 ].value && range[ 1 ].value && range[ 1 ].value < range[ 0 ].value ) {
						range[ 1 ].setCustomValidity( labels.dateRange || 'La date de fin doit être postérieure ou égale à la date de début.' );
					}
				}
			} );

			var seen = {};
			postForm.querySelectorAll( '.opac-creneaux-editor tbody tr' ).forEach( function ( row ) {
				var dateInput = row.querySelector( 'input[name$="[date]"]' );
				var dayInput = row.querySelector( 'select[name$="[jour]"]' );
				var startInput = row.querySelector( 'input[name$="[debut]"]' );
				var endInput = row.querySelector( 'input[name$="[fin]"]' );
				var anchorInput = dateInput || dayInput;

				if ( anchorInput ) {
					anchorInput.setCustomValidity( '' );
				}
				if ( endInput ) {
					endInput.setCustomValidity( '' );
				}

				if ( startInput && endInput && startInput.value && endInput.value && endInput.value <= startInput.value ) {
					endInput.setCustomValidity( labels.timeRange || 'L’heure de fin doit être postérieure à l’heure de début.' );
				}

				if ( dateInput && dateInput.value
					&& ( ( startDate && startDate.value && dateInput.value < startDate.value )
						|| ( endDate && endDate.value && dateInput.value > endDate.value ) ) ) {
					dateInput.setCustomValidity( labels.sessionRange || 'La séance doit être comprise entre les dates de début et de fin.' );
				}

				var anchor = anchorInput ? anchorInput.value : '';
				var start = valueOf( row, 'debut' );
				var end = valueOf( row, 'fin' );
				if ( anchor && start && end ) {
					var kind = dateInput ? 'stage' : 'atelier';
					var key = kind + '|' + anchor + '|' + start + '|' + end;
					if ( seen[ key ] && anchorInput ) {
						anchorInput.setCustomValidity( labels.duplicate || 'Cette séance ou ce créneau existe déjà.' );
					} else {
						seen[ key ] = true;
					}
				}
			} );
		};

		postForm.addEventListener( 'input', validateSchedule );
		postForm.addEventListener( 'change', validateSchedule );
		validateSchedule();
	}

	// --- 3. Suggestion de période d'après la date de début.
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
