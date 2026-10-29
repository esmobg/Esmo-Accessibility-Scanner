<?php
/**
 * Run-scan page template.
 *
 * @package EsmoAccessibilityScanner
 *
 * @var array<string,mixed> $args Template context.
 *
 * @var int    $esmoas_post_id
 * @var string $esmoas_scan_type
 * @var string $esmoas_post_title
 */

defined( 'ABSPATH' ) || exit;

// Template context passed by AdminMenu::render() via load_template().
$esmoas_post_id    = $args['post_id'] ?? 0;
$esmoas_scan_type  = $args['scan_type'] ?? 'full';
$esmoas_post_title = $args['post_title'] ?? '';
?>
<div class="wrap esmoas-wrap">
	<h1 class="esmoas-title">
		<span class="dashicons dashicons-search" aria-hidden="true"></span>
		<?php esc_html_e( 'Run Accessibility Scan', 'esmo-accessibility-scanner' ); ?>
	</h1>
	<hr class="wp-header-end">

	<p class="esmoas-intro">
		<?php esc_html_e( 'Scanning runs in your browser using axe-core against the rendered front-end of each page. Keep this tab open until the scan completes.', 'esmo-accessibility-scanner' ); ?>
		<?php
		echo ' ';
		printf(
			/* translators: %d: maximum URLs in a full-site scan. */
			esc_html__( 'A full-site scan includes at most %d URLs (filter: esmoas_scan_url_limit).', 'esmo-accessibility-scanner' ),
			(int) \EsmoAccessibilityScanner\Scan\UrlProvider::resolve_url_limit()
		);
		?>
	</p>

	<div class="esmoas-scan-controls">
		<?php if ( 'single' === $esmoas_scan_type && $esmoas_post_id > 0 ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: post title. */
					esc_html__( 'Single page scan: %s', 'esmo-accessibility-scanner' ),
					'<strong>' . esc_html( $esmoas_post_title ) . '</strong>'
				);
				?>
			</p>
			<button type="button" class="button button-primary button-hero" id="esmoas-start-scan"
				data-scan-type="single" data-post-id="<?php echo esc_attr( (string) $esmoas_post_id ); ?>">
				<?php esc_html_e( 'Start single page scan', 'esmo-accessibility-scanner' ); ?>
			</button>
		<?php else : ?>
			<button type="button" class="button button-primary button-hero" id="esmoas-start-scan"
				data-scan-type="full" data-post-id="0">
				<?php esc_html_e( 'Start full site scan', 'esmo-accessibility-scanner' ); ?>
			</button>
		<?php endif; ?>

		<button type="button" class="button" id="esmoas-cancel-scan" hidden>
			<?php esc_html_e( 'Cancel', 'esmo-accessibility-scanner' ); ?>
		</button>
	</div>

	<div id="esmoas-progress" class="esmoas-progress" hidden>
		<div class="esmoas-progress__head">
			<span id="esmoas-progress-message" class="esmoas-progress__message"></span>
			<span id="esmoas-progress-count" class="esmoas-progress__count">0 / 0</span>
		</div>
		<div id="esmoas-progress-track" class="esmoas-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
			aria-label="<?php esc_attr_e( 'Scan progress', 'esmo-accessibility-scanner' ); ?>">
			<div id="esmoas-progress-bar" class="esmoas-progress__bar" style="width: 0;"></div>
		</div>
	</div>

	<div id="esmoas-scan-result" class="esmoas-result" hidden tabindex="-1" aria-live="polite">
		<div class="esmoas-result__head">
			<span id="esmoas-result-badge" class="esmoas-result__badge">
				<strong id="esmoas-result-score" class="esmoas-result__score">&ndash;/100</strong>
				<span id="esmoas-result-band" class="esmoas-result__band"></span>
			</span>
			<h2 class="esmoas-result__title"><?php esc_html_e( 'Scan finished', 'esmo-accessibility-scanner' ); ?></h2>
		</div>

		<ul class="esmoas-result__counts">
			<li class="esmoas-result__count esmoas-result__count--critical">
				<span class="esmoas-result__num" id="esmoas-result-critical">0</span>
				<?php esc_html_e( 'Critical', 'esmo-accessibility-scanner' ); ?>
			</li>
			<li class="esmoas-result__count esmoas-result__count--major">
				<span class="esmoas-result__num" id="esmoas-result-major">0</span>
				<?php esc_html_e( 'Major', 'esmo-accessibility-scanner' ); ?>
			</li>
			<li class="esmoas-result__count esmoas-result__count--minor">
				<span class="esmoas-result__num" id="esmoas-result-minor">0</span>
				<?php esc_html_e( 'Minor', 'esmo-accessibility-scanner' ); ?>
			</li>
			<li class="esmoas-result__count esmoas-result__count--warning">
				<span class="esmoas-result__num" id="esmoas-result-warning">0</span>
				<?php esc_html_e( 'Warnings', 'esmo-accessibility-scanner' ); ?>
			</li>
			<li class="esmoas-result__count esmoas-result__count--review">
				<span class="esmoas-result__num" id="esmoas-result-review">0</span>
				<?php esc_html_e( 'Needs review (not scored)', 'esmo-accessibility-scanner' ); ?>
			</li>
		</ul>

		<p class="esmoas-result__actions">
			<a class="button button-primary" id="esmoas-result-issues" href="<?php echo esc_url( admin_url( 'admin.php?page=esmo-accessibility-scanner-issues' ) ); ?>">
				<?php esc_html_e( 'Review issues', 'esmo-accessibility-scanner' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=esmo-accessibility-scanner' ) ); ?>">
				<?php esc_html_e( 'Open dashboard', 'esmo-accessibility-scanner' ); ?>
			</a>
		</p>
	</div>

	<ul id="esmoas-scan-log" class="esmoas-log" aria-live="polite"></ul>

	<iframe id="esmoas-scan-frame" class="esmoas-scan-frame" title="<?php esc_attr_e( 'Accessibility scan frame', 'esmo-accessibility-scanner' ); ?>" aria-hidden="true" tabindex="-1" sandbox="allow-scripts allow-same-origin"></iframe>
</div>
