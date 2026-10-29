<?php
/**
 * Issues listing page, categorized by severity.
 *
 * @package EsmoAccessibilityScanner
 *
 * @var array<string,mixed> $args Template context.
 *
 * @var int                                       $esmoas_scan_id
 * @var string                                    $esmoas_severity
 * @var int                                       $esmoas_paged
 * @var int                                       $esmoas_per_page
 * @var array<string,int>                         $esmoas_severity_counts
 * @var array<int,array<string,mixed>>            $esmoas_issues
 * @var \EsmoAccessibilityScanner\Rules\RuleCatalog  $esmoas_catalog
 * @var string                                    $esmoas_base_url
 * @var string                                    $esmoas_settings_url
 */

defined( 'ABSPATH' ) || exit;

// Template context passed by AdminMenu::render() via load_template().
$esmoas_scan_id         = $args['scan_id'] ?? 0;
$esmoas_severity        = $args['severity'] ?? '';
$esmoas_paged           = $args['paged'] ?? 1;
$esmoas_per_page        = $args['per_page'] ?? 25;
$esmoas_severity_counts = $args['severity_counts'] ?? array();
$esmoas_issues          = $args['issues'] ?? array();
$esmoas_catalog         = $args['catalog'] ?? null;
$esmoas_base_url        = $args['base_url'] ?? '';
$esmoas_settings_url    = $args['settings_url'] ?? '';

$esmoas_severity_labels = array(
	'critical' => __( 'Critical', 'esmo-accessibility-scanner' ),
	'major'    => __( 'Major', 'esmo-accessibility-scanner' ),
	'minor'    => __( 'Minor', 'esmo-accessibility-scanner' ),
	'warning'  => __( 'Warning', 'esmo-accessibility-scanner' ),
	'review'   => __( 'Needs review', 'esmo-accessibility-scanner' ),
);

$esmoas_total = array_sum( $esmoas_severity_counts );
?>
<div class="wrap esmoas-wrap">
	<h1 class="esmoas-title">
		<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
		<?php esc_html_e( 'Accessibility Issues', 'esmo-accessibility-scanner' ); ?>
	</h1>
	<hr class="wp-header-end">

	<?php if ( 0 === $esmoas_scan_id ) : ?>
		<div class="esmoas-empty">
			<p><?php esc_html_e( 'No scans yet. Run a scan to see categorized issues here.', 'esmo-accessibility-scanner' ); ?></p>
		</div>
	<?php else : ?>
		<ul class="esmoas-sev-tabs">
			<li>
				<a class="esmoas-sev-tab <?php echo '' === $esmoas_severity ? 'is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( array( 'scan_id' => $esmoas_scan_id ), $esmoas_base_url ) ); ?>">
					<?php esc_html_e( 'All', 'esmo-accessibility-scanner' ); ?>
					<span class="esmoas-sev-tab__count"><?php echo esc_html( (string) $esmoas_total ); ?></span>
				</a>
			</li>
			<?php foreach ( $esmoas_severity_labels as $esmoas_key => $esmoas_label ) : ?>
				<li>
					<a class="esmoas-sev-tab esmoas-sev-tab--<?php echo esc_attr( $esmoas_key ); ?> <?php echo $esmoas_severity === $esmoas_key ? 'is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( array( 'scan_id' => $esmoas_scan_id, 'severity' => $esmoas_key ), $esmoas_base_url ) ); ?>">
						<?php echo esc_html( $esmoas_label ); ?>
						<span class="esmoas-sev-tab__count"><?php echo esc_html( (string) ( $esmoas_severity_counts[ $esmoas_key ] ?? 0 ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( empty( $esmoas_issues ) ) : ?>
			<p class="esmoas-muted"><?php esc_html_e( 'No issues in this category. Great work!', 'esmo-accessibility-scanner' ); ?></p>
		<?php else : ?>
			<div class="esmoas-issues">
				<?php foreach ( $esmoas_issues as $esmoas_issue ) : ?>
					<?php
					$esmoas_sev      = (string) $esmoas_issue['severity'];
					$esmoas_rule     = (string) $esmoas_issue['rule_id'];
					$esmoas_auto_fix = $esmoas_catalog->auto_fix_for( $esmoas_rule );
					?>
					<article class="esmoas-issue esmoas-issue--<?php echo esc_attr( $esmoas_sev ); ?>">
						<header class="esmoas-issue__head">
							<span class="esmoas-pill esmoas-pill--<?php echo esc_attr( $esmoas_sev ); ?>"><?php echo esc_html( $esmoas_severity_labels[ $esmoas_sev ] ?? ucfirst( $esmoas_sev ) ); ?></span>
							<code class="esmoas-issue__rule"><?php echo esc_html( $esmoas_rule ); ?></code>
							<span class="esmoas-issue__wcag"><?php echo esc_html( (string) $esmoas_issue['wcag_ref'] ); ?></span>
						</header>

						<p class="esmoas-issue__message"><?php echo esc_html( (string) $esmoas_issue['message'] ); ?></p>

						<?php if ( '' !== (string) $esmoas_issue['url'] ) : ?>
							<p class="esmoas-issue__meta">
								<strong><?php esc_html_e( 'Page:', 'esmo-accessibility-scanner' ); ?></strong>
								<a href="<?php echo esc_url( (string) $esmoas_issue['url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( (string) $esmoas_issue['url'] ); ?>
									<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'esmo-accessibility-scanner' ); ?></span>
								</a>
							</p>
						<?php endif; ?>

						<?php if ( '' !== (string) $esmoas_issue['dom_path'] ) : ?>
							<p class="esmoas-issue__meta">
								<strong><?php esc_html_e( 'Element:', 'esmo-accessibility-scanner' ); ?></strong>
								<code><?php echo esc_html( (string) $esmoas_issue['dom_path'] ); ?></code>
							</p>
						<?php endif; ?>

						<?php if ( '' !== (string) $esmoas_issue['html_snippet'] ) : ?>
							<pre class="esmoas-issue__snippet"><code><?php echo esc_html( (string) $esmoas_issue['html_snippet'] ); ?></code></pre>
						<?php endif; ?>

						<div class="esmoas-issue__fix">
							<strong><?php esc_html_e( 'Recommended fix:', 'esmo-accessibility-scanner' ); ?></strong>
							<span><?php echo esc_html( (string) $esmoas_issue['fix_suggestion'] ); ?></span>
						</div>

						<footer class="esmoas-issue__foot">
							<?php if ( null !== $esmoas_auto_fix ) : ?>
								<span class="esmoas-autofix-badge">
									<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
									<?php
									printf(
										/* translators: %s: auto-fix option name. */
										esc_html__( 'Auto-fix available: %s', 'esmo-accessibility-scanner' ),
										esc_html( $esmoas_auto_fix['label'] )
									);
									?>
									<?php if ( '' !== $esmoas_settings_url ) : ?>
										<a href="<?php echo esc_url( $esmoas_settings_url ); ?>"><?php esc_html_e( 'Enable', 'esmo-accessibility-scanner' ); ?></a>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<?php if ( '' !== (string) $esmoas_issue['doc_link'] ) : ?>
								<a class="esmoas-issue__doc" href="<?php echo esc_url( (string) $esmoas_issue['doc_link'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Documentation', 'esmo-accessibility-scanner' ); ?>
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'esmo-accessibility-scanner' ); ?></span>
								</a>
							<?php endif; ?>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>

			<?php
			$esmoas_filtered_total = '' !== $esmoas_severity ? (int) ( $esmoas_severity_counts[ $esmoas_severity ] ?? 0 ) : $esmoas_total;
			$esmoas_total_pages    = (int) max( 1, (int) ceil( $esmoas_filtered_total / $esmoas_per_page ) );
			if ( $esmoas_total_pages > 1 ) :
				$esmoas_page_links = paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', add_query_arg( array_filter( array( 'scan_id' => $esmoas_scan_id, 'severity' => $esmoas_severity ) ), $esmoas_base_url ) ),
						'format'    => '',
						'current'   => $esmoas_paged,
						'total'     => $esmoas_total_pages,
						'prev_text' => __( '&laquo; Previous', 'esmo-accessibility-scanner' ),
						'next_text' => __( 'Next &raquo;', 'esmo-accessibility-scanner' ),
					)
				);
				if ( $esmoas_page_links ) :
					?>
					<nav class="esmoas-pagination" aria-label="<?php esc_attr_e( 'Issues pagination', 'esmo-accessibility-scanner' ); ?>">
						<?php echo wp_kses_post( $esmoas_page_links ); ?>
					</nav>
					<?php
				endif;
			endif;
			?>
		<?php endif; ?>
	<?php endif; ?>
</div>
