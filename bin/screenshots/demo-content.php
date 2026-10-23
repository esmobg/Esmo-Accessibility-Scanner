<?php
/**
 * Demo content for screenshots and manual testing in WordPress Playground.
 *
 * Creates a small "Riverside Bakery" site whose pages contain typical
 * accessibility problems (missing alt text, generic link text, placeholder-only
 * fields, low contrast, skipped heading levels, PDF and new-window links).
 *
 * Only runs when loaded by bin/screenshots/blueprint.json, which defines
 * ESMOAS_DEMO_CONTENT first. Requested over HTTP it does nothing.
 *
 * @package EsmoAccessibilityScanner
 */

if ( ! defined( 'ESMOAS_DEMO_CONTENT' ) ) {
	exit;
}

require_once '/wordpress/wp-load.php';

kses_remove_filters();
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'blogname', 'Riverside Bakery' );
update_option( 'blogdescription', 'Fresh bread every morning' );

$esmoas_demo_items = array(
	array(
		'page',
		'About Us',
		'<h2>Our story</h2>
<p>Riverside Bakery has baked sourdough by the river since 1998. Every loaf is shaped by hand.</p>
<img src="/wp-includes/images/w-logo-blue.png" width="80" height="80">
<h5>Opening hours</h5>
<p style="color:#b5b5b5">Monday to Saturday, 7:00 &ndash; 18:00. Closed on Sundays.</p>
<p>Want to know more about our flour? <a href="/menu/">Click here</a>.</p>',
	),
	array(
		'page',
		'Contact',
		'<h2>Write to us</h2>
<form action="#" method="post">
<p><input type="text" name="name" placeholder="Your name"></p>
<p><input type="email" name="email" placeholder="Email address"></p>
<p><textarea name="message" placeholder="Message"></textarea></p>
<p><button type="submit"></button></p>
</form>
<p>Find us on <a href="https://www.openstreetmap.org/" target="_blank">the map</a>.</p>',
	),
	array(
		'page',
		'Menu',
		'<h2>Breads</h2>
<ul><li>Sourdough &ndash; &euro;4.50</li><li>Rye &ndash; &euro;4.00</li><li>Baguette &ndash; &euro;2.20</li></ul>
<h2>Pastries</h2>
<p>Croissants, cinnamon rolls and seasonal tarts.</p>
<p><a href="/wp-content/uploads/menu-2026.pdf">Download the full menu</a> or <a href="/contact/">read more</a>.</p>',
	),
	array(
		'page',
		'Accessibility statement',
		'<h2>Our commitment</h2>
<p>We want everyone to be able to use this website. If you find a problem, please <a href="/contact/">contact us</a>.</p>',
	),
	array(
		'post',
		'Spring sourdough workshop',
		'<p>Join our head baker for a hands-on sourdough workshop this April.</p>
<img src="/wp-includes/images/media/default.svg" width="120" height="120">
<p><a href="/contact/">More</a></p>',
	),
	array(
		'post',
		'New rye loaf',
		'<p>Our new 100% rye loaf is dense, tangy and keeps for a week.</p>
<p><a href="https://example.org/rye-guide.pdf" target="_blank">Rye baking guide</a></p>',
	),
);

foreach ( $esmoas_demo_items as $esmoas_demo_item ) {
	list( $esmoas_demo_type, $esmoas_demo_title, $esmoas_demo_content ) = $esmoas_demo_item;

	if ( get_page_by_path( sanitize_title( $esmoas_demo_title ), OBJECT, $esmoas_demo_type ) ) {
		continue;
	}

	wp_insert_post(
		array(
			'post_type'    => $esmoas_demo_type,
			'post_status'  => 'publish',
			'post_title'   => $esmoas_demo_title,
			'post_content' => $esmoas_demo_content,
		)
	);
}

flush_rewrite_rules();
