/**
 * Capture the WordPress.org screenshots from a running WordPress Playground.
 *
 * 1. Build and unpack the plugin:   python bin/build-zip.py --dir .build
 * 2. Start Playground (see README "Regenerating screenshots"), using
 *    bin/screenshots/blueprint.json and mounting bin/screenshots at /wordpress/esmoas-tools.
 * 3. Run:  node bin/screenshots/capture.js [baseUrl] [outDir]
 *    (requires the `playwright` package and its Chromium build).
 *
 * The script scans the demo site three times, publishing corrected demo pages
 * and enabling a few automatic fixes in between (so the dashboard shows a real
 * trend), then saves screenshot-1..4.png.
 */
'use strict';

const path = require( 'path' );
const { chromium } = require( 'playwright' );

const base = ( process.argv[ 2 ] || 'http://127.0.0.1:9400' ).replace( /\/$/, '' );
const outDir = path.resolve( process.argv[ 3 ] || path.join( __dirname, '..', '..', 'wp-org-assets' ) );
const admin = ( page ) => `${ base }/wp-admin/admin.php?page=${ page }`;

async function runScan( page ) {
	console.log( 'running a full-site scan…' );
	await page.goto( admin( 'esmo-accessibility-scanner-scan' ) );
	await page.click( '#esmoas-start-scan' );
	await page.waitForSelector( '#esmoas-scan-result:not([hidden])', { timeout: 10 * 60 * 1000 } );
}

/**
 * Save settings in a throwaway tab: headless Chromium stops painting a tab
 * after the settings form's POST + redirect, which makes later screenshots hang.
 */
async function enableFixes( context, keys ) {
	console.log( 'enabling fixes:', keys.join( ', ' ) );
	const page = await context.newPage();
	await page.goto( admin( 'esmo-accessibility-scanner-settings' ) );
	for ( const key of keys ) {
		await page.check( `input[name="fixes[]"][value="${ key }"]` );
	}
	await page.locator( '#esmoas_settings_submit' ).click();
	await page.waitForLoadState( 'load' );
	await page.close();
}

/**
 * Corrected versions of the demo pages, as a site owner would publish them
 * after reading the report. Applied through the REST API between scans so
 * the dashboard shows a real improvement over time.
 */
const FIXED_PAGES = {
	'about-us': `<h2>Our story</h2>
<p>Riverside Bakery has baked sourdough by the river since 1998. Every loaf is shaped by hand.</p>
<img src="/wp-includes/images/w-logo-blue.png" width="80" height="80" alt="Riverside Bakery logo">
<h3>Opening hours</h3>
<p>Monday to Saturday, 7:00 &ndash; 18:00. Closed on Sundays.</p>
<p>Want to know more? <a href="/menu/">See the flours we use in our bread</a>.</p>`,
	contact: `<h2>Write to us</h2>
<form action="#" method="post">
<p><label for="c-name">Your name</label><br><input type="text" id="c-name" name="name"></p>
<p><label for="c-email">Email address</label><br><input type="email" id="c-email" name="email"></p>
<p><label for="c-message">Message</label><br><textarea id="c-message" name="message"></textarea></p>
<p><button type="submit">Send message</button></p>
</form>
<p>Find us on <a href="https://www.openstreetmap.org/" target="_blank">the map</a>.</p>`,
};

async function fixPage( page, slug ) {
	console.log( 'publishing a corrected version of', slug );
	await page.goto( admin( 'esmo-accessibility-scanner' ) );
	const ok = await page.evaluate(
		async ( { slug: pageSlug, content } ) => {
			const nonce = await ( await fetch( '/wp-admin/admin-ajax.php?action=rest-nonce' ) ).text();
			const headers = { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' };
			const [ item ] = await ( await fetch( `/wp-json/wp/v2/pages?slug=${ pageSlug }&context=edit`, { headers } ) ).json();
			const res = await fetch( `/wp-json/wp/v2/pages/${ item.id }`, { method: 'POST', headers, body: JSON.stringify( { content } ) } );
			return res.ok;
		},
		{ slug, content: FIXED_PAGES[ slug ] }
	);
	if ( ! ok ) {
		throw new Error( `Could not update page ${ slug }` );
	}
}

async function shot( page, name, options = {} ) {
	const file = path.join( outDir, name );
	await page.mouse.move( 0, 0 );
	await page.screenshot( { path: file, ...options } );
	console.log( 'saved', file );
}

( async () => {
	const browser = await chromium.launch();
	const context = await browser.newContext( { viewport: { width: 1280, height: 800 }, colorScheme: 'light' } );
	const page = await context.newPage();

	// Playground's "login": true signs the first visitor in automatically.
	await page.goto( `${ base }/` );
	await page.goto( admin( 'esmo-accessibility-scanner' ) );
	if ( page.url().includes( 'wp-login.php' ) ) {
		throw new Error( 'Not logged in. Start Playground with the bundled blueprint (it sets "login": true).' );
	}

	// Three scans with real improvements in between, so "Progress over time" has a trend.
	await runScan( page );
	await fixPage( page, 'about-us' );
	await runScan( page );
	await fixPage( page, 'contact' );
	await enableFixes( context, [ 'new_window_warning', 'underline_links', 'add_focus_outline' ] );
	await runScan( page );
	await shot( page, 'screenshot-2.png' );

	await page.goto( admin( 'esmo-accessibility-scanner' ) );
	await shot( page, 'screenshot-1.png' );

	await page.goto( admin( 'esmo-accessibility-scanner-issues' ) );
	await shot( page, 'screenshot-3.png' );

	await page.setViewportSize( { width: 1280, height: 1000 } );
	await page.goto( admin( 'esmo-accessibility-scanner-settings' ) );
	await shot( page, 'screenshot-4.png' );

	await browser.close();
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );
