/**
 * Community Supporters Directory — front-end filtering.
 *
 * Powers the filter bar (Role / Contribution / Employer / Language / Country).
 * Runs client-side on already-rendered markup, so it works on cached pages.
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
