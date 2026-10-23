/**
 * Esmo Accessibility Scanner - front-end automatic fixes.
 *
 * Applies opt-in DOM remediations based on flags supplied by the server.
 * CSS-only fixes are handled via body classes, not here. Fixes are re-applied
 * to content inserted later (for example by the Interactivity API router or
 * "load more" buttons) through a MutationObserver.
 */
( function () {
	'use strict';

	var config = window.esmoasFixes || {};
	var flags = config.flags || {};
	var i18n = config.i18n || {};

	function each( root, selector, callback ) {
		var scope = root && root.querySelectorAll ? root : document;
		Array.prototype.forEach.call( scope.querySelectorAll( selector ), callback );
		if ( scope !== document && scope.matches && scope.matches( selector ) ) {
			callback( scope );
		}
	}

	function ensureHtmlLang() {
		var html = document.documentElement;
		if ( ! html.getAttribute( 'lang' ) && config.lang ) {
			html.setAttribute( 'lang', config.lang );
		}
	}

	function fixViewport() {
		var meta = document.querySelector( 'meta[name="viewport"]' );
		if ( ! meta ) {
			return;
		}
		var content = meta.getAttribute( 'content' ) || '';
		content = content
			.replace( /,?\s*user-scalable\s*=\s*(no|0)/gi, '' )
			.replace( /,?\s*maximum-scale\s*=\s*[0-9.]+/gi, '' );
		if ( ! /maximum-scale/i.test( content ) ) {
			content += ', maximum-scale=5.0';
		}
		meta.setAttribute( 'content', content.replace( /^[,\s]+/, '' ) );
	}

	function removePositiveTabindex( root ) {
		each( root, '[tabindex]', function ( el ) {
			var value = parseInt( el.getAttribute( 'tabindex' ), 10 );
			if ( ! isNaN( value ) && value > 0 ) {
				el.setAttribute( 'tabindex', '0' );
			}
		} );
	}

	/**
	 * Whether a form field has an accessible name other than its title.
	 */
	function fieldHasOtherName( field ) {
		if ( field.getAttribute( 'aria-label' ) || field.getAttribute( 'aria-labelledby' ) || field.closest( 'label' ) ) {
			return true;
		}
		if ( field.labels && field.labels.length ) {
			return true;
		}
		var type = ( field.getAttribute( 'type' ) || '' ).toLowerCase();
		return ( type === 'submit' || type === 'button' || type === 'reset' ) && !! ( field.value || '' ).trim();
	}

	function removeTitleAttr( root ) {
		each( root, 'a[title], button[title], input[title]', function ( el ) {
			var redundant = el.nodeName === 'INPUT'
				? fieldHasOtherName( el )
				: !! ( el.textContent || '' ).trim();
			if ( redundant ) {
				el.removeAttribute( 'title' );
			}
		} );
	}

	function newWindowWarning( root ) {
		var notice = i18n.newWindow || '(opens in a new window)';
		each( root, 'a[target="_blank"]', function ( link ) {
			if ( link.getAttribute( 'data-esmoas-new-window' ) ) {
				return;
			}
			var label = ( link.getAttribute( 'aria-label' ) || link.textContent || '' ).toLowerCase();
			if ( /new window|new tab|opens in/.test( label ) ) {
				return;
			}
			var span = document.createElement( 'span' );
			span.className = 'screen-reader-text esmoas-new-window-note';
			span.textContent = ' ' + notice;
			link.appendChild( span );
			if ( ! /noopener/.test( link.getAttribute( 'rel' ) || '' ) ) {
				link.setAttribute( 'rel', ( ( link.getAttribute( 'rel' ) || '' ) + ' noopener' ).trim() );
			}
			link.setAttribute( 'data-esmoas-new-window', '1' );
		} );
	}

	/**
	 * Fixes that apply to individual elements and must be re-run on new content.
	 */
	function applyElementFixes( root ) {
		if ( flags.removePositiveTabindex ) {
			removePositiveTabindex( root );
		}
		if ( flags.removeTitleAttr ) {
			removeTitleAttr( root );
		}
		if ( flags.newWindowWarning ) {
			newWindowWarning( root );
		}
	}

	function observe() {
		if ( ! window.MutationObserver || ! document.body ) {
			return;
		}
		if ( ! flags.removePositiveTabindex && ! flags.removeTitleAttr && ! flags.newWindowWarning ) {
			return;
		}

		var pending = [];
		var scheduled = false;

		var observer = new window.MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.prototype.forEach.call( mutation.addedNodes, function ( node ) {
					if ( node.nodeType === 1 && ! ( node.classList && node.classList.contains( 'esmoas-new-window-note' ) ) ) {
						pending.push( node );
					}
				} );
			} );
			if ( ! pending.length || scheduled ) {
				return;
			}
			scheduled = true;
			// setTimeout rather than requestAnimationFrame: rAF is paused in background tabs.
			window.setTimeout( function () {
				var nodes = pending;
				pending = [];
				scheduled = false;
				nodes.forEach( function ( node ) {
					if ( node.isConnected ) {
						applyElementFixes( node );
					}
				} );
			}, 50 );
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	function init() {
		if ( flags.ensureHtmlLang ) {
			ensureHtmlLang();
		}
		if ( flags.fixViewport ) {
			fixViewport();
		}
		applyElementFixes( document );
		observe();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
