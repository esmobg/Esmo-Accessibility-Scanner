<?php
/**
 * Dashboard page template.
 *
 * @package EsmoAccessibilityScanner
 *
 * @var array<string,mixed> $args Template context.
 *
 * @var array<string,mixed>|null                         $esmoas_latest
 * @var array<int,array<string,mixed>>                   $esmoas_recent
 * @var array<int,array<string,mixed>>                   $esmoas_history
 * @var array<string,int>                                $esmoas_severity_counts
 * @var array<string,int>                                $esmoas_category_counts
 * @var array<int,array<string,mixed>>                   $esmoas_top_rules
 * @var array{label:string,key:string}                   $esmoas_band
 * @var string                                           $esmoas_scan_url
 */

defined( 'ABSPATH' ) || exit;

// Template context passed by AdminMenu::render() via load_template().
$esmoas_latest          = $args['latest'] ?? null;
$esmoas_recent          = $args['recent'] ?? array();
$esmoas_history         = $args['history'] ?? array();
$esmoas_severity_counts = $args['severity_counts'] ?? array();
$esmoas_category_counts = $args['category_counts'] ?? array();
$esmoas_top_rules       = $args['top_rules'] ?? array();
$esmoas_band            = $args['band'] ?? array( 'label' => '', 'key' => 'poor' );
$esmoas_scan_url        = $args['scan_url'] ?? '';

$esmoas_score    = null !== $esmoas_latest ? (int) $esmoas_latest['score'] : 0;
$esmoas_errors   = null !== $esmoas_latest ? (int) $esmoas_latest['errors'] : 0;
$esmoas_warnings = null !== $esmoas_latest ? (int) $esmoas_latest['warnings'] : 0;
$esmoas_passes   = null !== $esmoas_latest ? (int) $esmoas_latest['passes'] : 0;
$esmoas_total    = null !== $esmoas_latest ? (int) $esmoas_latest['total_urls'] : 0;

$esmoas_severity_total = array_sum( $esmoas_severity_counts );
$esmoas_status_labels  = array(
	'running'   => __( 'Running', 'esmo-accessibility-scanner' ),
	'complete'  => __( 'Complete', 'esmo-accessibility-scanner' ),
	'cancelled' => __( 'Cancelled', 'esmo-accessibility-scanner' ),
	'failed'    => __( 'Failed', 'esmo-accessibility-scanner' ),
);
$esmoas_history_json   = wp_json_encode(
	array_map(
		static fn ( array $row ): array => array(
			'score' => (int) $row['score'],
			'date'  => $row['created_at'],
		),
		$esmoas_history
	)
);

$esmoas_export_base = wp_nonce_url(
	admin_url( 'admin-post.php?action=esmoas_export&scan_id=' . ( null !== $esmoas_latest ? (int) $esmoas_latest['id'] : 0 ) ),
	'esmoas_export'
);
?>
<div class="wrap esmoas-wrap">
	<h1 class="esmoas-title">
		<span class="dashicons dashicons-universal-access-alt" aria-hidden="true"></span>
		<?php esc_html_e( 'Esmo Accessibility Scanner', 'esmo-accessibility-scanner' ); ?>
	</h1>
	<hr class="wp-header-end">

	<?php if ( null === $esmoas_latest ) : ?>
		<div class="esmoas-empty">
			<p><?php esc_html_e( 'No scans yet. Run your first accessibility scan to see results here.', 'esmo-accessibility-scanner' ); ?></p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( $esmoas_scan_url ); ?>">
				<?php esc_html_e( 'Run your first scan', 'esmo-accessibility-scanner' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="esmoas-actions">
			<a class="button button-primary" href="<?php echo esc_url( $esmoas_scan_url ); ?>">
				<?php esc_html_e( 'Run new scan', 'esmo-accessibility-scanner' ); ?>
			</a>
			<span class="esmoas-actions__export">
				<?php esc_html_e( 'Export:', 'esmo-accessibility-scanner' ); ?>
				<a class="button" href="<?php echo esc_url( $esmoas_export_base . '&format=csv' ); ?>">CSV</a>
				<a class="button" href="<?php echo esc_url( $esmoas_export_base . '&format=json' ); ?>">JSON</a>
			</span>
		</div>

		<div class="esmoas-cards">
			<div class="esmoas-card esmoas-card--score esmoas-score--<?php echo esc_attr( $esmoas_band['key'] ); ?>">
				<div class="esmoas-score__circle" role="img"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %d: score. */ __( 'Accessibility score %d out of 100', 'esmo-accessibility-scanner' ), $esmoas_score ) ); ?>"
					style="--esmoas-score: <?php echo esc_attr( (string) $esmoas_score ); ?>">
					<span class="esmoas-score__value"><?php echo esc_html( (string) $esmoas_score ); ?></span>
					<span class="esmoas-score__max">/100</span>
				</div>
				<p class="esmoas-score__band"><?php echo esc_html( $esmoas_band['label'] ); ?></p>
				<?php if ( $esmoas_total > 1 ) : ?>
					<p class="esmoas-score__note esmoas-muted"><?php esc_html_e( 'Site score is the average across scanned pages.', 'esmo-accessibility-scanner' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="esmoas-card">
				<span class="esmoas-card__label"><?php esc_html_e( 'Pages scanned', 'esmo-accessibility-scanner' ); ?></span>
				<span class="esmoas-card__value"><?php echo esc_html( (string) $esmoas_total ); ?></span>
			</div>
			<div class="esmoas-card esmoas-card--error">
				<span class="esmoas-card__label"><?php esc_html_e( 'Errors', 'esmo-accessibility-scanner' ); ?></span>
				<span class="esmoas-card__value"><?php echo esc_html( (string) $esmoas_errors ); ?></span>
			</div>
			<div class="esmoas-card esmoas-card--warning">
				<span class="esmoas-card__label"><?php esc_html_e( 'Warnings', 'esmo-accessibility-scanner' ); ?></span>
				<span class="esmoas-card__value"><?php echo esc_html( (string) $esmoas_warnings ); ?></span>
			</div>
			<div class="esmoas-card esmoas-card--pass">
				<span class="esmoas-card__label"><?php esc_html_e( 'Passed checks', 'esmo-accessibility-scanner' ); ?></span>
				<span class="esmoas-card__value"><?php echo esc_html( (string) $esmoas_passes ); ?></span>
			</div>
		</div>

		<div class="esmoas-grid">
			<section class="esmoas-panel" aria-labelledby="esmoas-severity-heading">
				<h2 id="esmoas-severity-heading"><?php esc_html_e( 'Issues by severity', 'esmo-accessibility-scanner' ); ?></h2>
				<ul class="esmoas-bars">
					<?php
					$esmoas_severity_labels = array(
						'critical' => __( 'Critical', 'esmo-accessibility-scanner' ),
						'major'    => __( 'Major', 'esmo-accessibility-scanner' ),
						'minor'    => __( 'Minor', 'esmo-accessibility-scanner' ),
						'warning'  => __( 'Warning', 'esmo-accessibility-scanner' ),
						'review'   => __( 'Needs review', 'esmo-accessibility-scanner' ),
					);
					foreach ( $esmoas_severity_labels as $esmoas_key => $esmoas_label ) :
						$esmoas_value      = (int) ( $esmoas_severity_counts[ $esmoas_key ] ?? 0 );
						$esmoas_percent    = $esmoas_severity_total > 0 ? round( ( $esmoas_value / $esmoas_severity_total ) * 100 ) : 0;
						$esmoas_issues_url = admin_url( 'admin.php?page=esmo-accessibility-scanner-issues&severity=' . $esmoas_key );
						?>
						<li class="esmoas-bars__row">
							<a class="esmoas-bars__link" href="<?php echo esc_url( $esmoas_issues_url ); ?>"
								aria-label="<?php echo esc_attr( sprintf( /* translators: 1: count, 2: severity label. */ __( 'View %1$d %2$s issues', 'esmo-accessibility-scanner' ), $esmoas_value, $esmoas_label ) ); ?>">
								<span class="esmoas-bars__label"><?php echo esc_html( $esmoas_label ); ?></span>
								<span class="esmoas-bars__track">
									<span class="esmoas-bars__fill esmoas-bars__fill--<?php echo esc_attr( $esmoas_key ); ?>"
										style="width: <?php echo esc_attr( (string) $esmoas_percent ); ?>%;"></span>
								</span>
								<span class="esmoas-bars__value"><?php echo esc_html( (string) $esmoas_value ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="esmoas-panel" aria-labelledby="esmoas-category-heading">
				<h2 id="esmoas-category-heading"><?php esc_html_e( 'WCAG category distribution', 'esmo-accessibility-scanner' ); ?></h2>
				<?php if ( empty( $esmoas_category_counts ) ) : ?>
					<p class="esmoas-muted"><?php esc_html_e( 'No issues recorded.', 'esmo-accessibility-scanner' ); ?></p>
				<?php else : ?>
					<ul class="esmoas-bars">
						<?php
						$esmoas_cat_max = max( $esmoas_category_counts );
						foreach ( $esmoas_category_counts as $esmoas_cat => $esmoas_cat_value ) :
							$esmoas_cat_percent = $esmoas_cat_max > 0 ? round( ( $esmoas_cat_value / $esmoas_cat_max ) * 100 ) : 0;
							?>
							<li class="esmoas-bars__row">
								<span class="esmoas-bars__label"><?php echo esc_html( ucfirst( (string) $esmoas_cat ) ); ?></span>
								<span class="esmoas-bars__track">
									<span class="esmoas-bars__fill esmoas-bars__fill--category"
										style="width: <?php echo esc_attr( (string) $esmoas_cat_percent ); ?>%;"></span>
								</span>
								<span class="esmoas-bars__value"><?php echo esc_html( (string) $esmoas_cat_value ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<section class="esmoas-panel" aria-labelledby="esmoas-trend-heading">
				<h2 id="esmoas-trend-heading"><?php esc_html_e( 'Progress over time', 'esmo-accessibility-scanner' ); ?></h2>
				<div id="esmoas-trend" class="esmoas-trend"
					data-history="<?php echo esc_attr( (string) $esmoas_history_json ); ?>"
					data-empty="<?php echo esc_attr__( 'No history yet.', 'esmo-accessibility-scanner' ); ?>"></div>
			</section>

			<section class="esmoas-panel" aria-labelledby="esmoas-top-heading">
				<h2 id="esmoas-top-heading"><?php esc_html_e( 'Most common problems', 'esmo-accessibility-scanner' ); ?></h2>
				<?php if ( empty( $esmoas_top_rules ) ) : ?>
					<p class="esmoas-muted"><?php esc_html_e( 'No issues recorded.', 'esmo-accessibility-scanner' ); ?></p>
				<?php else : ?>
					<table class="widefat striped esmoas-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Rule', 'esmo-accessibility-scanner' ); ?></th>
								<th scope="col"><?php esc_html_e( 'WCAG', 'esmo-accessibility-scanner' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Severity', 'esmo-accessibility-scanner' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Count', 'esmo-accessibility-scanner' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $esmoas_top_rules as $esmoas_rule ) : ?>
								<tr>
									<td><code><?php echo esc_html( (string) $esmoas_rule['rule_id'] ); ?></code></td>
									<td><?php echo esc_html( (string) $esmoas_rule['wcag_ref'] ); ?></td>
									<td><span class="esmoas-pill esmoas-pill--<?php echo esc_attr( (string) $esmoas_rule['severity'] ); ?>"><?php echo esc_html( $esmoas_severity_labels[ (string) $esmoas_rule['severity'] ] ?? ucfirst( (string) $esmoas_rule['severity'] ) ); ?></span></td>
									<td><?php echo esc_html( (string) $esmoas_rule['total'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>

		<section class="esmoas-panel" aria-labelledby="esmoas-recent-heading">
			<h2 id="esmoas-recent-heading"><?php esc_html_e( 'Recent scans', 'esmo-accessibility-scanner' ); ?></h2>
			<table class="widefat striped esmoas-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Date', 'esmo-accessibility-scanner' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'esmo-accessibility-scanner' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Pages', 'esmo-accessibility-scanner' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Score', 'esmo-accessibility-scanner' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Errors', 'esmo-accessibility-scanner' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Warnings', 'esmo-accessibility-scanner' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'esmo-accessibility-scanner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $esmoas_recent as $esmoas_row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $esmoas_row['started_at'] ); ?></td>
							<td><?php echo esc_html( ucfirst( (string) $esmoas_row['scan_type'] ) ); ?></td>
							<td><?php echo esc_html( (string) $esmoas_row['total_urls'] ); ?></td>
							<td><?php echo 'complete' === (string) $esmoas_row['status'] ? esc_html( (string) $esmoas_row['score'] ) : '&ndash;'; ?></td>
							<td><?php echo esc_html( (string) $esmoas_row['errors'] ); ?></td>
							<td><?php echo esc_html( (string) $esmoas_row['warnings'] ); ?></td>
							<td><span class="esmoas-pill esmoas-pill--<?php echo esc_attr( (string) $esmoas_row['status'] ); ?>"><?php echo esc_html( $esmoas_status_labels[ (string) $esmoas_row['status'] ] ?? ucfirst( (string) $esmoas_row['status'] ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
