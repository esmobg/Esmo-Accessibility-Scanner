<?php
/**
 * Settings page template.
 *
 * @package EsmoAccessibilityScanner
 *
 * @var array<string,mixed> $args Template context.
 *
 * @var array<string,mixed>                                       $esmoas_settings
 * @var array<string,\WP_Post_Type>                               $esmoas_post_types
 * @var array<string,array{label:string,description:string}>      $esmoas_fix_catalog
 */

defined( 'ABSPATH' ) || exit;

// Template context passed by AdminMenu::render() via load_template().
$esmoas_settings    = $args['settings'] ?? array();
$esmoas_post_types  = $args['post_types'] ?? array();
$esmoas_fix_catalog = $args['fix_catalog'] ?? array();

$esmoas_selected_types = isset( $esmoas_settings['include_post_types'] ) && is_array( $esmoas_settings['include_post_types'] )
	? array_map( 'strval', $esmoas_settings['include_post_types'] )
	: array();
$esmoas_include_terms  = ! empty( $esmoas_settings['include_terms'] );
$esmoas_wcag_level     = isset( $esmoas_settings['wcag_level'] ) ? (string) $esmoas_settings['wcag_level'] : 'aa';
$esmoas_best_practice  = ! isset( $esmoas_settings['best_practice'] ) || ! empty( $esmoas_settings['best_practice'] );
$esmoas_enabled_fixes  = isset( $esmoas_settings['fixes'] ) && is_array( $esmoas_settings['fixes'] ) ? $esmoas_settings['fixes'] : array();
?>
<div class="wrap esmoas-wrap">
	<h1 class="esmoas-title">
		<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
		<?php esc_html_e( 'Esmo Accessibility Scanner Settings', 'esmo-accessibility-scanner' ); ?>
	</h1>
	<hr class="wp-header-end">

	<?php settings_errors( 'esmoas_settings' ); ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'esmoas_save_settings' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Content to scan', 'esmo-accessibility-scanner' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Post types to include', 'esmo-accessibility-scanner' ); ?></legend>
							<?php foreach ( $esmoas_post_types as $esmoas_type ) : ?>
								<label class="esmoas-checkbox">
									<input type="checkbox" name="include_post_types[]"
										value="<?php echo esc_attr( $esmoas_type->name ); ?>"
										<?php checked( in_array( $esmoas_type->name, $esmoas_selected_types, true ) ); ?> />
									<?php echo esc_html( $esmoas_type->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Choose which public post types are included in full-site scans.', 'esmo-accessibility-scanner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Term archives', 'esmo-accessibility-scanner' ); ?></th>
					<td>
						<label class="esmoas-checkbox">
							<input type="checkbox" name="include_terms" value="1" <?php checked( $esmoas_include_terms ); ?> />
							<?php esc_html_e( 'Include category and tag archive pages', 'esmo-accessibility-scanner' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="esmoas-wcag-level"><?php esc_html_e( 'WCAG level', 'esmo-accessibility-scanner' ); ?></label></th>
					<td>
						<select id="esmoas-wcag-level" name="wcag_level">
							<option value="a" <?php selected( $esmoas_wcag_level, 'a' ); ?>><?php esc_html_e( 'A', 'esmo-accessibility-scanner' ); ?></option>
							<option value="aa" <?php selected( $esmoas_wcag_level, 'aa' ); ?>><?php esc_html_e( 'AA (recommended)', 'esmo-accessibility-scanner' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Best-practice checks', 'esmo-accessibility-scanner' ); ?></th>
					<td>
						<label class="esmoas-checkbox">
							<input type="checkbox" name="best_practice" value="1" <?php checked( $esmoas_best_practice ); ?> />
							<?php esc_html_e( 'Also check headings, landmarks and other axe-core best practices', 'esmo-accessibility-scanner' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'These rules are not strict WCAG failures but catch common structural problems such as skipped heading levels, a missing main heading or content outside landmarks.', 'esmo-accessibility-scanner' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Automatic fixes', 'esmo-accessibility-scanner' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Enable safe, site-wide remediations applied automatically on the front end. Each fix is optional; test your site after enabling.', 'esmo-accessibility-scanner' ); ?>
		</p>
		<fieldset class="esmoas-fixes">
			<legend class="screen-reader-text"><?php esc_html_e( 'Automatic fixes', 'esmo-accessibility-scanner' ); ?></legend>
			<?php foreach ( $esmoas_fix_catalog as $esmoas_fix_key => $esmoas_fix ) : ?>
				<label class="esmoas-fix-option">
					<input type="checkbox" name="fixes[]" value="<?php echo esc_attr( $esmoas_fix_key ); ?>"
						<?php checked( ! empty( $esmoas_enabled_fixes[ $esmoas_fix_key ] ) ); ?> />
					<span class="esmoas-fix-option__label"><?php echo esc_html( $esmoas_fix['label'] ); ?></span>
					<span class="esmoas-fix-option__desc"><?php echo esc_html( $esmoas_fix['description'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<?php submit_button( __( 'Save settings', 'esmo-accessibility-scanner' ), 'primary', 'esmoas_settings_submit' ); ?>
	</form>
</div>
