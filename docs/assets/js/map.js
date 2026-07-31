/**
 * Community Supporters Directory — supporter map (Leaflet).
 *
 * One dot per person, placed at their own location. This map answers "who is
 * near me", so individuals have to be visible in relation to each other —
 * aggregating them into a per-country circle scaled by headcount defeats that,
 * and at world zoom those circles were large enough to swallow whole regions.
 *
 * Dots resize with zoom rather than holding one pixel size: small enough at
 * world view that dense regions stay readable, large enough when you zoom into
 * a region to be an easy click target.
 *
 * Placement honesty: a solid dot means we resolved a self-reported city. A
 * hollow dot means we only knew the country, so it sits on the country centroid
 * and should not be read as "this person is here".
 */
( function () {
	'use strict';

	// Dot radius in pixels at a given Leaflet zoom level. Deliberately gentle:
	// the old count-scaled markers ran to 28px, which is what made the world
	// view unreadable.
	var MIN_RADIUS = 3.5;
	var MAX_RADIUS = 13;

	function radiusForZoom( zoom ) {
		return Math.max( MIN_RADIUS, Math.min( MAX_RADIUS, 2.5 + ( zoom - 1 ) * 1.15 ) );
	}

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = String( value == null ? '' : value );
		return div.innerHTML;
	}

	/**
	 * Spread markers that share an exact coordinate.
	 *
	 * Everyone who only had a country sits on that country's centroid, so
	 * without this they stack into a single dot and the rest are invisible and
	 * unclickable. Offsets are deterministic (same input, same layout) and tiny.
	 */
	function spreadDuplicates( markers ) {
		var buckets = {};
		markers.forEach( function ( m ) {
			var key = m.lat.toFixed( 3 ) + ',' + m.lng.toFixed( 3 );
			( buckets[ key ] = buckets[ key ] || [] ).push( m );
		} );

		Object.keys( buckets ).forEach( function ( key ) {
			var group = buckets[ key ];
			if ( group.length < 2 ) {
				return;
			}
			// Ring of ~35km, which reads as "around here" without implying a city.
			var step = ( 2 * Math.PI ) / group.length;
			group.forEach( function ( m, i ) {
				var cos = Math.cos( m.lat * Math.PI / 180 ) || 1;
				m.lat += 0.32 * Math.sin( i * step );
				m.lng += 0.32 * Math.cos( i * step ) / cos;
				m.spread = true;
			} );
		} );

		return markers;
	}

	function popupHtml( m ) {
		var bits = [ '<strong>' + escapeHtml( m.name ) + '</strong>' ];

		if ( m.place ) {
			bits.push(
				'<span class="comsup-pop__place">' + escapeHtml( m.place ) +
				( 'country' === m.precision ? ' <em>(country only)</em>' : '' ) + '</span>'
			);
		}
		if ( m.role ) {
			bits.push( '<span class="comsup-pop__role">' + escapeHtml( m.role ) + '</span>' );
		}
		if ( m.employer ) {
			bits.push( '<span class="comsup-pop__employer">' + escapeHtml( m.employer ) + '</span>' );
		}
		if ( m.profile ) {
			bits.push(
				'<a class="comsup-pop__link" href="' + escapeHtml( m.profile ) +
				'" target="_blank" rel="noopener">WordPress profile &#8599;</a>'
			);
		}

		return '<div class="comsup-pop">' + bits.join( '' ) + '</div>';
	}

	function initMap( el ) {
		if ( ! window.L ) {
			return;
		}

		var markers = [];
		try {
			markers = JSON.parse( el.getAttribute( 'data-markers' ) || '[]' );
		} catch ( e ) {
			markers = [];
		}
		if ( ! markers.length ) {
			return;
		}

		spreadDuplicates( markers );

		var wrap = el.closest ? el.closest( '.comsup-supporters-wrap' ) : null;

		function setCountry( token ) {
			if ( wrap ) {
				wrap.dispatchEvent( new CustomEvent( 'comsup:setcountry', { detail: { token: token } } ) );
			}
		}

		var map = window.L.map( el.id, { worldCopyJump: true, scrollWheelZoom: false } )
			.setView( [ 25, 5 ], 2 );

		window.L.tileLayer( 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
			subdomains: 'abcd',
			maxZoom: 19,
		} ).addTo( map );

		var layer = window.L.layerGroup().addTo( map );
		var dots  = [];

		markers.forEach( function ( m ) {
			var isCity = 'city' === m.precision;

			var dot = window.L.circleMarker( [ m.lat, m.lng ], {
				radius: radiusForZoom( map.getZoom() ),
				color: '#3858E9',
				weight: isCity ? 1.5 : 1.5,
				// Hollow for country-centroid placements: the dot is real, the
				// precision is not, and the shape says so without a legend.
				fillColor: isCity ? '#3858E9' : '#fff',
				fillOpacity: isCity ? 0.85 : 0.9,
			} );

			dot.bindPopup( popupHtml( m ), { className: 'comsup-popup', closeButton: true } );
			dot.bindTooltip( m.name, { direction: 'top', offset: [ 0, -4 ] } );

			// Keep the old affordance: opening someone's dot narrows the list to
			// their country, so the cards below match what you're looking at.
			dot.on( 'click', function () {
				if ( m.cid ) {
					setCountry( m.cid );
				}
			} );

			dot.addTo( layer );
			dots.push( dot );
		} );

		map.on( 'zoomend', function () {
			var r = radiusForZoom( map.getZoom() );
			dots.forEach( function ( d ) {
				d.setRadius( r );
			} );
		} );

		map.on( 'click', function () {
			setCountry( '' );
		} );
	}

	function init() {
		document.querySelectorAll( '.comsup-map__canvas' ).forEach( initMap );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
