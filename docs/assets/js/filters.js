/**
 * Community Supporters Directory — front-end filtering.
 *
 * Powers the filter bar (Role / Contribution / Employer / Language / Country).
 * Runs client-side on already-rendered markup, so it works on cached pages.
 *
 * Filtering drives the MAP as well as the list. Narrowing to one employer has to
 * leave one dot on the map, not 74 - seeing the survivors in place is the whole
 * reason the map exists. Each item carries data-index (its record index) and the
 * visible set is published on a `comsup:filtered` event that map.js listens for.
 */
( function () {
	'use strict';

	function initWrap( wrap ) {
		var selects   = wrap.querySelectorAll( '[data-comsup-filter]' );
		var items     = wrap.querySelectorAll( '.comsup-card, .comsup-table tbody tr' );
		var noResults = wrap.querySelector( '.comsup-supporters__noresults' );

		function apply() {
			var crit = { employer: '', language: '', country: '', role: '', contrib: '' };
			selects.forEach( function ( s ) {
				crit[ s.getAttribute( 'data-comsup-filter' ) ] = s.value;
			} );

			var visible = 0;
			items.forEach( function ( item ) {
				var ok = true;
				if ( crit.employer ) {
					ok = ok && item.getAttribute( 'data-employer' ) === crit.employer;
				}
				if ( crit.role ) {
					ok = ok && ( item.getAttribute( 'data-roles' ) || '' ).indexOf( '|' + crit.role + '|' ) !== -1;
				}
				if ( crit.contrib ) {
					ok = ok && item.getAttribute( 'data-contrib' ) === crit.contrib;
				}
				if ( crit.country ) {
					ok = ok && ( item.getAttribute( 'data-countries' ) || '' ).indexOf( '|' + crit.country + '|' ) !== -1;
				}
				if ( crit.language ) {
					ok = ok && ( item.getAttribute( 'data-languages' ) || '' ).indexOf( '|' + crit.language + '|' ) !== -1;
				}
				item.hidden = ! ok;
				if ( ok ) {
					visible++;
				}
			} );

			if ( noResults ) {
				noResults.hidden = visible !== 0;
			}

			// Tell the map which records survived, so its dots match the cards.
			var shown = [];
			items.forEach( function ( item ) {
				if ( ! item.hidden ) {
					shown.push( parseInt( item.getAttribute( 'data-index' ), 10 ) );
				}
			} );
			var anyActive = !! ( crit.employer || crit.role || crit.contrib ||
				crit.country || crit.language );
			wrap.dispatchEvent( new CustomEvent( 'comsup:filtered', {
				detail: { indices: shown, filtered: anyActive },
			} ) );
		}

		selects.forEach( function ( s ) {
			s.addEventListener( 'change', apply );
		} );

		apply();
	}

	function init() {
		document.querySelectorAll( '.comsup-supporters-wrap' ).forEach( initWrap );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
