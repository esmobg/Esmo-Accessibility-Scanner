/* Apply a saved theme before first paint. Without JS the page follows the system setting. */
( function () {
	try {
		var theme = localStorage.getItem( 'esmo-theme' );
		if ( theme === 'light' || theme === 'dark' ) {
			document.documentElement.setAttribute( 'data-theme', theme );
		}
	} catch ( e ) {}
}() );
