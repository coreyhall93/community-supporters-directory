/**
 * Community Supporters Directory — supporter map (Leaflet).
 *
 * One dot per person, placed at their own location. This map answers "who is
 * near me", so individuals have to be visible in relation to each other.
 *
 * - Dots resize with zoom (small at world view, clickable up close).
 * - Solid dot: we resolved a self-reported city. Hollow dot: only the country
 *   is known, so the dot sits on the country centroid. The on-map key says so.
 * - Wheel zoom needs ⌘/Ctrl held, so the page can still be scrolled past the
 *   map; scrolling over the map without the modifier shows a short hint.
 * - A Reset control returns to the load-time view.
 * - Clicking a dot opens the person's popup and nothing else. It used to set
 *   the Country filter too, which was more surprising than useful.
 */
( function () {
	'use strict';

	var HOME_CENTER = [ 25, 5 ];
	var HOME_ZOOM   = 2;
	var MIN_RADIUS  = 3.5;
	var MAX_RADIUS  = 13;

	function radiusForZoom( zoom ) {
		return Math.max( MIN_RADIUS, Math.min( MAX_RADIUS, 2.5 + ( zoom - 1 ) * 1.15 ) );
	}

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = String( value == null ? '' : value );
		return div.innerHTML;
	}

	/**
	 * Spread markers that share an exact coordinate (country centroids),
	 * so stacked people stay individually visible and clickable.
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
			var step = ( 2 * Math.PI ) / group.length;
			group.forEach( function ( m, i ) {
				var cos = Math.cos( m.lat * Math.PI / 180 ) || 1;
				m.lat += 0.32 * Math.sin( i * step );
				m.lng += 0.32 * Math.cos( i * step ) / cos;
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
			bits.push( '<span class="comsup-pop__employer">Works at ' + escapeHtml( m.employer ) + '</span>' );
		}
		if ( m.sponsor ) {
			bits.push( '<span class="comsup-pop__employer">Five for the Future: ' + escapeHtml( m.sponsor ) + '</span>' );
		}
		if ( m.profile ) {
			bits.push(
				'<a class="comsup-pop__link" href="' + escapeHtml( m.profile ) +
				'" target="_blank" rel="noopener">WordPress profile &#8599;</a>'
			);
		}

		return '<div class="comsup-pop">' + bits.join( '' ) + '</div>';
	}

	var isMac = /Mac|iPhone|iPad/.test( navigator.platform || '' );
	var MOD_LABEL = isMac ? '⌘' : 'Ctrl';

	function addLegend( map ) {
		var legend = window.L.control( { position: 'bottomleft' } );
		legend.onAdd = function () {
			var el = window.L.DomUtil.create( 'div', 'comsup-map__legend' );
			el.innerHTML =
				'<div class="comsup-map__legend-title">Map key</div>' +
				'<div class="comsup-map__legend-row"><span class="comsup-map__legend-dot comsup-map__legend-dot--solid"></span>One person &mdash; city known</div>' +
				'<div class="comsup-map__legend-row"><span class="comsup-map__legend-dot comsup-map__legend-dot--hollow"></span>One person &mdash; country only, placed approximately</div>' +
				'<div class="comsup-map__legend-row comsup-map__legend-row--hint">Zoom: ' + MOD_LABEL + ' + scroll, double-click, or the +/&minus; buttons</div>';
			return el;
		};
		legend.addTo( map );
	}

	function addResetControl( map ) {
		var control = window.L.control( { position: 'topleft' } );
		control.onAdd = function () {
			var el = window.L.DomUtil.create( 'div', 'leaflet-bar comsup-map__reset' );
			var a  = window.L.DomUtil.create( 'a', '', el );
			a.href = '#';
			a.textContent = 'Reset';
			a.title = 'Zoom back out to the full map';
			a.setAttribute( 'role', 'button' );
			a.setAttribute( 'aria-label', 'Reset the map to the full world view' );
			window.L.DomEvent.on( a, 'click', function ( e ) {
				window.L.DomEvent.preventDefault( e );
				map.setView( HOME_CENTER, HOME_ZOOM );
			} );
			window.L.DomEvent.disableClickPropagation( el );
			return el;
		};
		control.addTo( map );
	}

	/**
	 * Wheel zoom only while ⌘ (or Ctrl) is held; a plain scroll over the map
	 * briefly shows a hint instead of hijacking the page scroll.
	 */
	function addModifierWheelZoom( map, container ) {
		var hint = document.createElement( 'div' );
		hint.className = 'comsup-map__wheel-hint';
		hint.textContent = 'Hold ' + MOD_LABEL + ' and scroll to zoom the map';
		container.appendChild( hint );
		var hideTimer = null;

		function setModifier( on ) {
			if ( on ) {
				map.scrollWheelZoom.enable();
			} else {
				map.scrollWheelZoom.disable();
			}
		}

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Meta' === e.key || 'Control' === e.key ) {
				setModifier( true );
			}
		} );
		document.addEventListener( 'keyup', function ( e ) {
			if ( 'Meta' === e.key || 'Control' === e.key ) {
				setModifier( false );
			}
		} );
		window.addEventListener( 'blur', function () {
			setModifier( false );
		} );

		container.addEventListener( 'wheel', function ( e ) {
			if ( e.metaKey || e.ctrlKey ) {
				// Leaflet's scrollWheelZoom (enabled above) handles the zoom;
				// keep the browser from pinch-zooming the page on Ctrl+wheel.
				e.preventDefault();
				return;
			}
			hint.classList.add( 'is-visible' );
			clearTimeout( hideTimer );
			hideTimer = setTimeout( function () {
				hint.classList.remove( 'is-visible' );
			}, 1200 );
		}, { passive: false } );
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

		var map = window.L.map( el.id, { worldCopyJump: true, scrollWheelZoom: false } )
			.setView( HOME_CENTER, HOME_ZOOM );
		el._comsupMap = map; // for tests/debugging

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
				weight: 1.5,
				fillColor: isCity ? '#3858E9' : '#fff',
				fillOpacity: isCity ? 0.85 : 0.9,
			} );

			dot.bindPopup( popupHtml( m ), { className: 'comsup-popup', closeButton: true } );
			dot.bindTooltip( m.name, { direction: 'top', offset: [ 0, -4 ] } );
			dot.addTo( layer );
			dots.push( dot );
		} );

		map.on( 'zoomend', function () {
			var r = radiusForZoom( map.getZoom() );
			dots.forEach( function ( d ) {
				d.setRadius( r );
			} );
		} );

		addLegend( map );
		addResetControl( map );
		addModifierWheelZoom( map, el );
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
