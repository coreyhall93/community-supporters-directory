/**
 * Community Supporters Directory — front-end filtering.
 *
 * Powers the filter bar (Sponsorship / Language / Country) and applies the
 * country chosen on the map (map.js dispatches a `comsup:setcountry` event).
 * Runs client-side on already-rendered markup, so it works on cached pages.
 */
( function () {
	'use strict';

	function initWrap( wrap ) {
		var selects       = wrap.querySelectorAll( '[data-comsup-filter]' );
		var items         = wrap.querySelectorAll( '.comsup-card, .comsup-table tbody tr' );
		var noResults     = wrap.querySelector( '.comsup-supporters__noresults' );
		var countrySelect = wrap.querySelector( '[data-comsup-filter="country"]' );
		var mapCountry    = ''; // Used when there is no country select.

		function apply() {
			var crit = { sponsored: '', language: '', country: '' };
			selects.forEach( function ( s ) {
				crit[ s.getAttribute( 'data-comsup-filter' ) ] = s.value;
			} );
			if ( ! countrySelect ) {
				crit.country = mapCountry;
			}

			var visible = 0;
			items.forEach( function ( item ) {
				var ok = true;
				if ( crit.sponsored ) {
					ok = ok && item.getAttribute( 'data-sponsored' ) === crit.sponsored;
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

		// The map (map.js) filters by country through this event.
		wrap.addEventListener( 'comsup:setcountry', function ( e ) {
			var token = ( e.detail && e.detail.token ) || '';
			if ( countrySelect ) {
				countrySelect.value = token;
			} else {
				mapCountry = token;
			}
			apply();
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
