/**
 * Render the WordPress.org icon and banner PNGs from their sources.
 *
 *   node bin/screenshots/render-brand.js
 *
 * Inputs:  bin/screenshots/icon.html, bin/screenshots/brand.html (both use the
 *          logo files in bin/screenshots/brand/)
 * Outputs: wp-org-assets/icon-128x128.png, icon-256x256.png,
 *          banner-772x250.png, banner-1544x500.png
 * Requires the `playwright` package and its Chromium build.
 */
'use strict';

const path = require( 'path' );
const { pathToFileURL } = require( 'url' );
const { chromium } = require( 'playwright' );

const root = path.resolve( __dirname, '..', '..' );
const assets = path.join( root, 'wp-org-assets' );

( async () => {
	const browser = await chromium.launch();

	for ( const size of [ 128, 256 ] ) {
		// The icon page fills the viewport, so one page serves both sizes.
		const page = await browser.newPage( { viewport: { width: size, height: size } } );
		await page.goto( pathToFileURL( path.join( __dirname, 'icon.html' ) ).href );
		await page.waitForLoadState( 'load' );
		const file = path.join( assets, `icon-${ size }x${ size }.png` );
		await page.screenshot( { path: file } );
		console.log( 'saved', file );
		await page.close();
	}

	for ( const scale of [ 1, 2 ] ) {
		const page = await browser.newPage( { viewport: { width: 772, height: 250 }, deviceScaleFactor: scale } );
		await page.goto( pathToFileURL( path.join( __dirname, 'brand.html' ) ).href );
		await page.waitForLoadState( 'load' );
		const file = path.join( assets, `banner-${ 772 * scale }x${ 250 * scale }.png` );
		await page.screenshot( { path: file } );
		console.log( 'saved', file );
		await page.close();
	}

	await browser.close();
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );
