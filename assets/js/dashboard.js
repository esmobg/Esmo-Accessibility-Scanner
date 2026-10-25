/**
 * Esmo Accessibility Scanner - dashboard enhancements.
 *
 * Renders a lightweight inline SVG trend line for the score history without
 * pulling in an external charting library.
 */
( function () {
	'use strict';

	var SVG_NS = 'http://www.w3.org/2000/svg';

	function format( template, values ) {
		var index = 0;
		return template.replace( /%(\d+\$)?d/g, function ( match, position ) {
			var value = position ? values[ parseInt( position, 10 ) - 1 ] : values[ index++ ];
			return String( value );
		} );
	}

	function el( name, attrs ) {
		var node = document.createElementNS( SVG_NS, name );
		Object.keys( attrs ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		return node;
	}

	function renderTrend( container ) {
		var raw = container.getAttribute( 'data-history' );
		if ( ! raw ) {
			return;
		}

		var points;
		try {
			points = JSON.parse( raw );
		} catch ( e ) {
			return;
		}

		var i18n = window.esmoasDashboard && window.esmoasDashboard.i18n ? window.esmoasDashboard.i18n : {};

		if ( ! points || points.length === 0 ) {
			container.textContent = i18n.noHistory || container.getAttribute( 'data-empty' ) || 'No history yet.';
			return;
		}

		var scores = points.map( function ( point ) {
			return Math.max( 0, Math.min( 100, parseInt( point.score, 10 ) || 0 ) );
		} );
		var first = scores[ 0 ];
		var last = scores[ scores.length - 1 ];

		// Scale to the data (never below 0, always up to 100) so small improvements stay visible.
		var width = Math.max( 160, Math.round( container.clientWidth || 300 ) );
		var height = 90;
		var pad = 8;
		var low = Math.max( 0, Math.min.apply( null, scores ) - 10 );
		var span = 100 - low || 1;
		var stepX = scores.length > 1 ? ( width - 2 * pad ) / ( scores.length - 1 ) : 0;

		var coords = scores.map( function ( score, index ) {
			var x = scores.length > 1 ? pad + index * stepX : width / 2;
			var y = pad + ( ( 100 - score ) / span ) * ( height - 2 * pad );
			return [ x, y ];
		} );

		var svg = el( 'svg', {
			viewBox: '0 0 ' + width + ' ' + height,
			width: String( width ),
			height: String( height ),
			class: 'esmoas-trend__svg',
			role: 'img',
		} );

		var label = scores.length > 1
			? format( i18n.trendLabel || 'Accessibility score changed from %1$d to %2$d over the last %3$d scans', [ first, last, scores.length ] )
			: format( i18n.scoreLabel || 'Latest accessibility score %d out of 100', [ last ] );
		svg.setAttribute( 'aria-label', label );

		svg.appendChild( el( 'line', { x1: pad, x2: width - pad, y1: pad, y2: pad, class: 'esmoas-trend__grid' } ) );
		svg.appendChild( el( 'line', { x1: pad, x2: width - pad, y1: height - pad, y2: height - pad, class: 'esmoas-trend__grid' } ) );

		if ( coords.length > 1 ) {
			svg.appendChild( el( 'polyline', {
				points: coords.map( function ( c ) {
					return c[ 0 ].toFixed( 1 ) + ',' + c[ 1 ].toFixed( 1 );
				} ).join( ' ' ),
				fill: 'none',
				stroke: 'currentColor',
				'stroke-width': '2',
				'stroke-linejoin': 'round',
			} ) );
		}

		coords.forEach( function ( c ) {
			svg.appendChild( el( 'circle', { cx: c[ 0 ].toFixed( 1 ), cy: c[ 1 ].toFixed( 1 ), r: '3.5', fill: 'currentColor' } ) );
		} );

		container.appendChild( svg );

		var caption = document.createElement( 'p' );
		caption.className = 'esmoas-trend__caption';
		if ( scores.length > 1 ) {
			var delta = last - first;
			caption.textContent = first + ' → ' + last + ' (' + ( delta > 0 ? '+' : '' ) + delta + ') · ' +
				format( i18n.scansLabel || '%d scans', [ scores.length ] );
		} else {
			caption.textContent = last + ' / 100';
		}
		caption.setAttribute( 'aria-hidden', 'true' );
		container.appendChild( caption );
	}

	function init() {
		var trend = document.getElementById( 'esmoas-trend' );
		if ( trend ) {
			renderTrend( trend );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
