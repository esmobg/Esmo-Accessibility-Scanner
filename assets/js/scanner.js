/**
 * Esmo Accessibility Scanner - browser scan orchestrator.
 *
 * Loads each queued URL into a hidden same-origin iframe, runs axe-core
 * (plus supplemental custom rules) against the rendered DOM, and streams
 * normalized results back to the server in batches.
 */
( function () {
	'use strict';

	var settings = window.esmoasScanner || {};
	var ajaxUrl = settings.ajaxUrl;
	var nonce = settings.nonce;
	var i18n = settings.i18n || {};

	var state = {
		scanId: 0,
		queue: [],
		index: 0,
		passes: 0,
		failures: 0,
		issuesFound: 0,
		running: false,
		cancelled: false
	};

	var els = {};

	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	/**
	 * POST to admin-ajax and resolve with parsed JSON. Rejects on network
	 * failures, HTTP errors and non-JSON responses (e.g. PHP fatals) so the
	 * UI can always recover instead of hanging.
	 */
	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', nonce );
		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( res ) {
			return res.json().catch( function () {
				throw new Error( 'invalid-json' );
			} );
		} );
	}

	function setProgress( done, total, message ) {
		var percent = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		if ( els.bar ) {
			els.bar.style.width = percent + '%';
		}
		if ( els.track ) {
			els.track.setAttribute( 'aria-valuenow', String( percent ) );
		}
		if ( els.count ) {
			els.count.textContent = done + ' / ' + total;
		}
		if ( els.message && message ) {
			els.message.textContent = message;
		}
	}

	function logLine( text, type ) {
		if ( ! els.log ) {
			return;
		}
		var li = document.createElement( 'li' );
		li.className = 'esmoas-log__item' + ( type ? ' esmoas-log__item--' + type : '' );
		li.textContent = text;
		els.log.appendChild( li );
		els.log.scrollTop = els.log.scrollHeight;
	}

	/**
	 * Run axe-core inside the iframe document, then merge custom rule results.
	 */
	function analyze( frameWindow, frameDocument ) {
		var axe = frameWindow.axe;
		if ( ! axe ) {
			return Promise.reject( new Error( 'axe-core unavailable in frame' ) );
		}

		var options = settings.axeOptions && typeof settings.axeOptions === 'object' ? settings.axeOptions : {};
		if ( ! options.runOnly ) {
			options.runOnly = { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] };
		}
		options.resultTypes = [ 'violations', 'incomplete', 'passes' ];

		// The frame is loaded as the logged-in admin; never report on the WordPress toolbar.
		var context = { exclude: [ [ '#wpadminbar' ] ] };

		return axe.run( context, options ).then( function ( results ) {
			var custom = window.esmoasCustomRules ? window.esmoasCustomRules.run( frameDocument ) : { violations: [] };

			return {
				violations: results.violations.concat( custom.violations || [] ),
				incomplete: results.incomplete || [],
				passes: ( results.passes || [] ).length
			};
		} );
	}

	/**
	 * Shorten text by code points so an emoji is never split in half: a lone
	 * surrogate makes the JSON invalid for PHP and the whole page would be lost.
	 */
	function clip( text, max ) {
		var value = String( text || '' );
		if ( typeof value.toWellFormed === 'function' ) {
			value = value.toWellFormed();
		}
		if ( value.length <= max ) {
			return value;
		}
		return Array.from( value ).slice( 0, max ).join( '' );
	}

	/**
	 * Detail kept per page, from full to minimal. The first level is what the
	 * server stores at most (150 rules per group, 25 elements per rule); the
	 * last one always fits the server's 512 KB limit.
	 */
	var PAYLOAD_LEVELS = [
		{ rules: 150, nodes: 25, html: 2000, text: 1000, help: 500, target: 1000 },
		{ rules: 150, nodes: 5, html: 300, text: 300, help: 500, target: 500 },
		{ rules: 100, nodes: 1, html: 150, text: 100, help: 200, target: 300 },
		{ rules: 50, nodes: 1, html: 0, text: 0, help: 200, target: 200 }
	];
	var PAYLOAD_LIMIT = 500000;

	/**
	 * An axe target (selectors, nested for shadow DOM) as one shortened selector
	 * path; the server joins the parts the same way.
	 */
	function clipTarget( target, max ) {
		var parts = ( Array.isArray( target ) ? target : [ target ] ).map( function ( part ) {
			return Array.isArray( part ) ? part.join( ' ' ) : String( part || '' );
		} );
		return [ clip( parts.join( ' > ' ), max ) ];
	}

	function compactResult( result, level ) {
		return ( result || [] ).slice( 0, level.rules ).map( function ( rule ) {
			var tags = rule.tags || [];
			return {
				id: rule.id,
				impact: rule.impact || '',
				help: clip( rule.help, level.help ),
				bestPractice: tags.indexOf( 'best-practice' ) !== -1 && ! tags.some( function ( tag ) {
					return /^wcag/.test( tag );
				} ),
				nodes: ( rule.nodes || [] ).slice( 0, level.nodes ).map( function ( node ) {
					return {
						html: clip( node.html, level.html ),
						target: clipTarget( node.target, level.target ),
						failureSummary: clip( node.failureSummary, level.text )
					};
				} )
			};
		} );
	}

	/**
	 * JSON body for one page: the most detailed level that stays under the limit.
	 */
	function payloadFor( entry, results ) {
		var body = '';

		for ( var i = 0; i < PAYLOAD_LEVELS.length; i++ ) {
			body = JSON.stringify( {
				url: entry.url,
				post_id: entry.post_id,
				violations: compactResult( results.violations, PAYLOAD_LEVELS[ i ] ),
				incomplete: compactResult( results.incomplete, PAYLOAD_LEVELS[ i ] )
			} );

			if ( new TextEncoder().encode( body ).length <= PAYLOAD_LIMIT ) {
				break;
			}
		}

		return body;
	}

	/**
	 * Inject the bundled axe-core into the frame document (by URL, same-origin)
	 * and wait until the axe global is available inside the frame. A copy of
	 * axe that the page itself provides is never trusted.
	 */
	function injectAxe( ctx ) {
		return new Promise( function ( resolve, reject ) {
			var doc = ctx.doc;
			var script = doc.createElement( 'script' );
			script.src = settings.axeUrl;
			script.onload = function () {
				resolve( ctx );
			};
			script.onerror = function () {
				reject( new Error( 'axe load failed' ) );
			};
			( doc.head || doc.documentElement ).appendChild( script );
		} );
	}

	/**
	 * Let the page come to rest before axe looks at it. Scripts that finish after
	 * the load event (block hydration such as the Interactivity API) get a moment,
	 * then running transitions and finite animations jump to their end state: the
	 * hidden frame may not animate at all, and a half-faded element (a cookie bar
	 * fading in) would otherwise be reported with a contrast it never has at rest.
	 * Infinite animations are left alone. The delay is the esmoas_scan_settle_delay filter.
	 * The WordPress toolbar (there because the frame loads pages as the logged-in
	 * administrator, and never reported on) is removed from the frame's copy of the
	 * page: visitors do not have it, and its links before the page's own would make
	 * axe stop treating the skip link as one ("region" on every page).
	 */
	function settle( ctx ) {
		var delay = parseInt( settings.settleDelay, 10 );
		delay = isNaN( delay ) ? 500 : Math.max( 0, delay );

		return new Promise( function ( resolve ) {
			window.setTimeout( function () {
				try {
					var toolbar = ctx.doc.getElementById( 'wpadminbar' );
					if ( toolbar && toolbar.parentNode ) {
						toolbar.parentNode.removeChild( toolbar );
					}
				} catch ( err ) {
					// Scanned with the toolbar, which axe excludes anyway.
				}
				try {
					if ( typeof ctx.doc.getAnimations === 'function' ) {
						ctx.doc.getAnimations().forEach( function ( animation ) {
							var timing = animation.effect && animation.effect.getComputedTiming ? animation.effect.getComputedTiming() : null;
							if ( 'running' === animation.playState && timing && isFinite( timing.endTime ) ) {
								animation.finish();
							}
						} );
					}
				} catch ( err ) {
					// A page that breaks the Web Animations API is scanned as it is.
				}
				resolve( ctx );
			}, delay );
		} );
	}

	/**
	 * Load a URL into the hidden iframe and resolve with its document once ready.
	 */
	function loadFrame( url ) {
		return new Promise( function ( resolve, reject ) {
			var frame = els.frame;
			var settled = false;

			var timer = window.setTimeout( function () {
				if ( ! settled ) {
					settled = true;
					reject( new Error( 'timeout' ) );
				}
			}, 30000 );

			frame.onload = function () {
				if ( settled ) {
					return;
				}
				settled = true;
				window.clearTimeout( timer );

				try {
					// Reading location throws for cross-origin and browser error pages
					// (e.g. a page refused by X-Frame-Options), so those are reported as failures.
					var loc = frame.contentWindow.location;
					if ( loc.origin !== window.location.origin || loc.href === 'about:blank' ) {
						throw new Error( 'blocked' );
					}
					var doc = frame.contentDocument || frame.contentWindow.document;
					resolve( { win: frame.contentWindow, doc: doc } );
				} catch ( err ) {
					reject( err );
				}
			};

			frame.src = url;
		} );
	}

	function processNext() {
		if ( state.cancelled || state.index >= state.queue.length ) {
			return finish();
		}

		var entry = state.queue[ state.index ];
		setProgress( state.index, state.queue.length, t( 'scanning', 'Scanning' ) + ': ' + entry.label );

		loadFrame( entry.url )
			.then( settle )
			.then( injectAxe )
			.then( function ( ctx ) {
				return analyze( ctx.win, ctx.doc );
			} )
			.then( function ( results ) {
				state.passes += results.passes;

				return post( 'esmoas_save_results', {
					scan_id: state.scanId,
					payload: payloadFor( entry, results )
				} ).then( function ( res ) {
					if ( ! res || ! res.success ) {
						state.failures += 1;
						logLine( entry.label + ' — ' + t( 'saveFailed', 'results could not be saved' ), 'error' );
						return;
					}
					var found = res.data.inserted || 0;
					state.issuesFound += found;
					logLine( entry.label + ' — ' + found + ' ' + t( 'issues', 'issues' ), found > 0 ? 'warn' : 'ok' );
				} );
			} )
			.catch( function () {
				state.failures += 1;
				logLine( entry.label + ' — ' + t( 'failed', 'could not be scanned' ), 'error' );
			} )
			.then( function () {
				state.index += 1;
				setProgress( state.index, state.queue.length );
				processNext();
			} );
	}

	function finish() {
		post( 'esmoas_finish_scan', {
			scan_id: state.scanId,
			passes: state.passes,
			cancelled: state.cancelled ? 1 : 0
		} ).then( function ( res ) {
			if ( res && res.success ) {
				var doneLabel = state.cancelled ? t( 'cancelled', 'Scan cancelled' ) : t( 'complete', 'Scan complete' );
				setProgress( state.index, state.queue.length, doneLabel );
				logLine( t( 'score', 'Score' ) + ': ' + res.data.score + '/100 (' + res.data.band.label + ')', 'ok' );

				if ( state.failures > 0 ) {
					logLine( state.failures + ' ' + t( 'pagesFailed', 'pages could not be scanned' ), 'warn' );
				}

				showResult( res.data );
			} else {
				logLine( t( 'finishError', 'Scan could not be finalized.' ), 'error' );
			}
		} ).catch( function () {
			logLine( t( 'networkError', 'Connection problem while talking to the server.' ), 'error' );
		} ).then( function () {
			state.running = false;
			toggleControls( false );
		} );
	}

	/**
	 * Render the completion summary panel with the score and severity counts.
	 */
	function showResult( data ) {
		if ( ! els.result ) {
			return;
		}

		var counts = data.counts || {};
		var fill = function ( id, value ) {
			var node = document.getElementById( id );
			if ( node ) {
				node.textContent = String( value );
			}
		};

		fill( 'esmoas-result-score', data.score + '/100' );
		fill( 'esmoas-result-band', data.band && data.band.label ? data.band.label : '' );
		fill( 'esmoas-result-critical', counts.critical || 0 );
		fill( 'esmoas-result-major', counts.major || 0 );
		fill( 'esmoas-result-minor', counts.minor || 0 );
		fill( 'esmoas-result-warning', counts.warning || 0 );
		fill( 'esmoas-result-review', counts.review || 0 );

		// Point "Review issues" at this scan, which matters for cancelled (partial) scans.
		var issuesLink = document.getElementById( 'esmoas-result-issues' );
		if ( issuesLink && state.scanId ) {
			var href = issuesLink.getAttribute( 'href' ) || '';
			issuesLink.setAttribute( 'href', href.split( '&scan_id=' )[ 0 ] + '&scan_id=' + encodeURIComponent( state.scanId ) );
		}

		var badge = document.getElementById( 'esmoas-result-badge' );
		if ( badge && data.band && data.band.key ) {
			badge.className = 'esmoas-result__badge esmoas-result__badge--' + data.band.key;
		}

		els.result.hidden = false;
		els.result.focus();
	}

	function toggleControls( running ) {
		if ( els.start ) {
			els.start.disabled = running;
		}
		if ( els.cancel ) {
			els.cancel.hidden = ! running;
			els.cancel.disabled = false;
		}
		// The progress block stays visible after a run so the final state remains readable.
		if ( els.progressWrap && running ) {
			els.progressWrap.hidden = false;
		}
	}

	function start( scanType, postId ) {
		if ( state.running ) {
			return;
		}

		state.running = true;
		state.cancelled = false;
		state.index = 0;
		state.passes = 0;
		state.failures = 0;
		state.issuesFound = 0;

		if ( els.log ) {
			els.log.innerHTML = '';
		}
		if ( els.result ) {
			els.result.hidden = true;
		}
		toggleControls( true );
		setProgress( 0, 1, t( 'preparing', 'Preparing scan…' ) );

		post( 'esmoas_start_scan', { scan_type: scanType, post_id: postId || 0 } )
			.then( function ( res ) {
				if ( ! res || ! res.success || ! res.data || ! res.data.queue ) {
					throw new Error( ( res && res.data && res.data.message ) || '' );
				}

				state.scanId = res.data.scan_id;
				state.queue = res.data.queue;
				setProgress( 0, state.queue.length );
				logLine( t( 'queued', 'Queued URLs' ) + ': ' + state.queue.length, 'ok' );
				processNext();
			} )
			.catch( function ( err ) {
				state.running = false;
				toggleControls( false );
				logLine( ( err && err.message ) || t( 'startError', 'Could not start scan.' ), 'error' );
			} );
	}

	function init() {
		els.frame = document.getElementById( 'esmoas-scan-frame' );
		els.start = document.getElementById( 'esmoas-start-scan' );
		els.cancel = document.getElementById( 'esmoas-cancel-scan' );
		els.bar = document.getElementById( 'esmoas-progress-bar' );
		els.track = document.getElementById( 'esmoas-progress-track' );
		els.count = document.getElementById( 'esmoas-progress-count' );
		els.message = document.getElementById( 'esmoas-progress-message' );
		els.progressWrap = document.getElementById( 'esmoas-progress' );
		els.log = document.getElementById( 'esmoas-scan-log' );
		els.result = document.getElementById( 'esmoas-scan-result' );

		if ( ! els.frame || ! els.start ) {
			return;
		}

		els.start.addEventListener( 'click', function () {
			var scanType = els.start.getAttribute( 'data-scan-type' ) || 'full';
			var postId = parseInt( els.start.getAttribute( 'data-post-id' ) || '0', 10 );
			start( scanType, postId );
		} );

		if ( els.cancel ) {
			els.cancel.addEventListener( 'click', function () {
				state.cancelled = true;
				els.cancel.disabled = true;
				logLine( t( 'cancelling', 'Cancelling after current page…' ), 'warn' );
			} );
		}

		// Warn before leaving mid-scan so a closed tab does not strand the scan.
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( state.running ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
