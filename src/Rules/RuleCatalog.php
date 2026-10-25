<?php
/**
 * WCAG rule metadata catalog.
 *
 * Maps axe-core rule identifiers (and supplemental custom rules) to
 * human-readable WCAG references, categories, default severities,
 * remediation guidance and documentation links.
 *
 * @package EsmoAccessibilityScanner
 */

declare(strict_types=1);

namespace EsmoAccessibilityScanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Provides metadata for every supported accessibility rule.
 */
final class RuleCatalog {

	/**
	 * Cached rule map.
	 *
	 * @var array<string, array{wcag:string,category:string,severity:string,fix:string,doc:string}>|null
	 */
	private ?array $map = null;

	/**
	 * Get metadata for a single rule id, or a sensible default.
	 *
	 * @param string $rule_id    axe-core or custom rule id.
	 * @param string $impact     axe impact level used to derive a fallback severity.
	 * @return array{wcag:string,category:string,severity:string,fix:string,doc:string}
	 */
	public function get( string $rule_id, string $impact = '' ): array {
		$map = $this->all();

		if ( isset( $map[ $rule_id ] ) ) {
			return $map[ $rule_id ];
		}

		return array(
			'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
			'category' => 'general',
			'severity' => $this->severity_from_impact( $impact ),
			'fix'      => __( 'Review this element against WCAG 2.2 guidelines and apply the recommended remediation.', 'esmo-accessibility-scanner' ),
			'doc'      => 'https://dequeuniversity.com/rules/axe/4.10/' . rawurlencode( $rule_id ),
		);
	}

	/**
	 * Catalog of available one-click automatic fixes.
	 *
	 * Each entry: key => [ label, description ]. The key is also the settings
	 * flag stored under esmoas_settings['fixes'].
	 *
	 * @return array<string, array{label:string,description:string}>
	 */
	public function fixes(): array {
		return array(
			'add_html_lang'           => array(
				'label'       => __( 'Add missing language attribute', 'esmo-accessibility-scanner' ),
				'description' => __( 'Sets a lang attribute on the <html> element when one is missing (WCAG 3.1.1).', 'esmo-accessibility-scanner' ),
			),
			'add_skip_link'           => array(
				'label'       => __( 'Add a skip-to-content link', 'esmo-accessibility-scanner' ),
				'description' => __( 'Inserts a keyboard skip link at the top of every page on classic themes; block themes already get one from WordPress (WCAG 2.4.1).', 'esmo-accessibility-scanner' ),
			),
			'add_focus_outline'       => array(
				'label'       => __( 'Add a visible focus outline', 'esmo-accessibility-scanner' ),
				'description' => __( 'Ensures links and controls show a clear outline when focused with the keyboard (WCAG 2.4.7).', 'esmo-accessibility-scanner' ),
			),
			'underline_links'         => array(
				'label'       => __( 'Underline links in content', 'esmo-accessibility-scanner' ),
				'description' => __( 'Underlines in-content links so they are not distinguished by color alone (WCAG 1.4.1).', 'esmo-accessibility-scanner' ),
			),
			'new_window_warning'      => array(
				'label'       => __( 'Warn about links opening new windows', 'esmo-accessibility-scanner' ),
				'description' => __( 'Appends a hidden "(opens in a new window)" notice to target="_blank" links (WCAG 3.2.5).', 'esmo-accessibility-scanner' ),
			),
			'fix_viewport'            => array(
				'label'       => __( 'Allow zooming (fix viewport)', 'esmo-accessibility-scanner' ),
				'description' => __( 'Removes user-scalable=no and maximum-scale limits so users can zoom (WCAG 1.4.4).', 'esmo-accessibility-scanner' ),
			),
			'remove_positive_tabindex' => array(
				'label'       => __( 'Remove positive tabindex values', 'esmo-accessibility-scanner' ),
				'description' => __( 'Resets tabindex values greater than 0 to keep a natural focus order (WCAG 2.4.3).', 'esmo-accessibility-scanner' ),
			),
			'label_search_form'       => array(
				'label'       => __( 'Add labels to search forms', 'esmo-accessibility-scanner' ),
				'description' => __( 'Adds an accessible label to the classic get_search_form() field; the Search block is already labelled (WCAG 3.3.2).', 'esmo-accessibility-scanner' ),
			),
			'remove_title_attr'       => array(
				'label'       => __( 'Remove redundant title attributes', 'esmo-accessibility-scanner' ),
				'description' => __( 'Strips title attributes from links, buttons and fields that already have another accessible name.', 'esmo-accessibility-scanner' ),
			),
		);
	}

	/**
	 * Resolve which automatic fix (if any) can address a given rule.
	 *
	 * @param string $rule_id Rule identifier.
	 * @return array{key:string,label:string}|null
	 */
	public function auto_fix_for( string $rule_id ): ?array {
		$map = array(
			'html-has-lang'         => 'add_html_lang',
			'html-lang-valid'       => 'add_html_lang',
			'bypass'                => 'add_skip_link',
			'link-in-text-block'    => 'underline_links',
			'esmoas-new-window-warning' => 'new_window_warning',
			'meta-viewport'         => 'fix_viewport',
			'tabindex'              => 'remove_positive_tabindex',
		);

		if ( ! isset( $map[ $rule_id ] ) ) {
			return null;
		}

		$key   = $map[ $rule_id ];
		$fixes = $this->fixes();

		return array(
			'key'   => $key,
			'label' => $fixes[ $key ]['label'] ?? $key,
		);
	}

	/**
	 * Map an axe impact level to a plugin severity bucket.
	 *
	 * @param string $impact axe impact level.
	 */
	public function severity_from_impact( string $impact ): string {
		return match ( $impact ) {
			'critical' => 'critical',
			'serious'  => 'major',
			'moderate' => 'minor',
			default    => 'warning',
		};
	}

	/**
	 * Return the full rule metadata map.
	 *
	 * @return array<string, array{wcag:string,category:string,severity:string,fix:string,doc:string}>
	 */
	public function all(): array {
		if ( null !== $this->map ) {
			return $this->map;
		}

		$axe = 'https://dequeuniversity.com/rules/axe/4.10/';

		$rules = array(
			// Images.
			'image-alt' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'critical',
				'fix'      => __( 'Add a meaningful alt attribute describing the image purpose. Use alt="" for purely decorative images.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'image-alt',
			),
			'input-image-alt' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'critical',
				'fix'      => __( 'Add an alt attribute to the image input describing the button action.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'input-image-alt',
			),
			'image-redundant-alt' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'minor',
				'fix'      => __( 'Remove redundant text that duplicates adjacent visible text or the alt value.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'image-redundant-alt',
			),
			'svg-img-alt' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'critical',
				'fix'      => __( 'Provide an accessible name for the SVG via <title>, aria-label, or role="img" with a label.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'svg-img-alt',
			),
			'object-alt' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'major',
				'fix'      => __( 'Provide alternative text for the <object> element.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'object-alt',
			),
			'area-alt' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'critical',
				'fix'      => __( 'Add alt text to each <area> within an image map.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'area-alt',
			),

			// Color contrast.
			'color-contrast' => array(
				'wcag'     => __( '1.4.3 Contrast (Minimum)', 'esmo-accessibility-scanner' ),
				'category' => 'contrast',
				'severity' => 'major',
				'fix'      => __( 'Increase the contrast ratio between text and background to at least 4.5:1 (3:1 for large text).', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'color-contrast',
			),

			// Headings.
			'empty-heading' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'headings',
				'severity' => 'minor',
				'fix'      => __( 'Provide discernible text inside the heading, or remove the empty heading element.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'empty-heading',
			),
			'heading-order' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'headings',
				'severity' => 'minor',
				'fix'      => __( 'Do not skip heading levels. Use a logical, sequential heading hierarchy.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'heading-order',
			),
			'page-has-heading-one' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'headings',
				'severity' => 'minor',
				'fix'      => __( 'Add a single descriptive <h1> that identifies the main content of the page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'page-has-heading-one',
			),

			// Links.
			'link-name' => array(
				'wcag'     => __( '2.4.4 Link Purpose (In Context)', 'esmo-accessibility-scanner' ),
				'category' => 'links',
				'severity' => 'major',
				'fix'      => __( 'Provide descriptive link text or an accessible name (aria-label) that conveys the link purpose.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'link-name',
			),
			'link-in-text-block' => array(
				'wcag'     => __( '1.4.1 Use of Color', 'esmo-accessibility-scanner' ),
				'category' => 'links',
				'severity' => 'major',
				'fix'      => __( 'Distinguish links from surrounding text using more than color (for example, an underline).', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'link-in-text-block',
			),
			'identical-links-same-purpose' => array(
				'wcag'     => __( '2.4.4 Link Purpose (In Context)', 'esmo-accessibility-scanner' ),
				'category' => 'links',
				'severity' => 'minor',
				'fix'      => __( 'Links with identical names should point to the same destination, or be given distinct accessible names.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'identical-links-same-purpose',
			),

			// Forms.
			'label' => array(
				'wcag'     => __( '3.3.2 Labels or Instructions', 'esmo-accessibility-scanner' ),
				'category' => 'forms',
				'severity' => 'critical',
				'fix'      => __( 'Associate a visible <label> with the form control using for/id, or add an aria-label.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'label',
			),
			'label-title-only' => array(
				'wcag'     => __( '3.3.2 Labels or Instructions', 'esmo-accessibility-scanner' ),
				'category' => 'forms',
				'severity' => 'minor',
				'fix'      => __( 'Provide a real label; do not rely solely on the title attribute.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'label-title-only',
			),
			'form-field-multiple-labels' => array(
				'wcag'     => __( '3.3.2 Labels or Instructions', 'esmo-accessibility-scanner' ),
				'category' => 'forms',
				'severity' => 'minor',
				'fix'      => __( 'Ensure each form field is associated with a single label.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'form-field-multiple-labels',
			),
			'autocomplete-valid' => array(
				'wcag'     => __( '1.3.5 Identify Input Purpose', 'esmo-accessibility-scanner' ),
				'category' => 'forms',
				'severity' => 'minor',
				'fix'      => __( 'Use a valid autocomplete token appropriate for the input purpose.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'autocomplete-valid',
			),
			'select-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'forms',
				'severity' => 'critical',
				'fix'      => __( 'Give the select element an accessible name via a label or aria-label.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'select-name',
			),

			// ARIA.
			'aria-allowed-attr' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Remove ARIA attributes that are not allowed for the element role.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-allowed-attr',
			),
			'aria-allowed-role' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'minor',
				'fix'      => __( 'Use an ARIA role that is allowed for this element.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-allowed-role',
			),
			'aria-required-attr' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'critical',
				'fix'      => __( 'Add the ARIA attributes required by this role.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-required-attr',
			),
			'aria-required-children' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'critical',
				'fix'      => __( 'Ensure the role contains its required child roles.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-required-children',
			),
			'aria-required-parent' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'critical',
				'fix'      => __( 'Ensure the role is contained within its required parent role.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-required-parent',
			),
			'aria-roles' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Use a valid ARIA role value.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-roles',
			),
			'aria-valid-attr' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Correct the misspelled or invalid ARIA attribute name.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-valid-attr',
			),
			'aria-valid-attr-value' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Provide a valid value for the ARIA attribute.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-valid-attr-value',
			),
			'aria-hidden-body' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'critical',
				'fix'      => __( 'Do not set aria-hidden="true" on the document <body>.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-hidden-body',
			),
			'aria-hidden-focus' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Do not place focusable elements inside aria-hidden="true" containers.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-hidden-focus',
			),
			'aria-command-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Provide an accessible name for the ARIA command (button, link, menuitem).', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-command-name',
			),
			'aria-input-field-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Provide an accessible name for the ARIA input field.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-input-field-name',
			),
			'aria-toggle-field-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Provide an accessible name for the ARIA toggle field.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-toggle-field-name',
			),

			// Duplicate / structure.
			'duplicate-id-aria' => array(
				'wcag'     => __( '4.1.1 Parsing', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'minor',
				'fix'      => __( 'Ensure id values referenced by ARIA are unique within the document.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'duplicate-id-aria',
			),
			'duplicate-id-active' => array(
				'wcag'     => __( '4.1.1 Parsing', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'minor',
				'fix'      => __( 'Ensure id values on active elements are unique within the document.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'duplicate-id-active',
			),
			'duplicate-id' => array(
				'wcag'     => __( '4.1.1 Parsing', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'minor',
				'fix'      => __( 'Ensure every id attribute is unique within the document.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'duplicate-id',
			),

			// Tables.
			'td-headers-attr' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'tables',
				'severity' => 'major',
				'fix'      => __( 'Ensure the headers attribute references valid header cell ids in the same table.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'td-headers-attr',
			),
			'th-has-data-cells' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'tables',
				'severity' => 'major',
				'fix'      => __( 'Ensure each <th> is associated with data cells, or use a layout that does not rely on it.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'th-has-data-cells',
			),
			'scope-attr-valid' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'tables',
				'severity' => 'major',
				'fix'      => __( 'Use a valid scope value (row, col, rowgroup, colgroup) on header cells.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'scope-attr-valid',
			),
			'table-duplicate-name' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'tables',
				'severity' => 'minor',
				'fix'      => __( 'Ensure the table caption and summary do not duplicate each other.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'table-duplicate-name',
			),
			'empty-table-header' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'tables',
				'severity' => 'minor',
				'fix'      => __( 'Provide text content for table header cells.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'empty-table-header',
			),

			// Language.
			'html-has-lang' => array(
				'wcag'     => __( '3.1.1 Language of Page', 'esmo-accessibility-scanner' ),
				'category' => 'language',
				'severity' => 'major',
				'fix'      => __( 'Add a lang attribute to the <html> element identifying the page language.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'html-has-lang',
			),
			'html-lang-valid' => array(
				'wcag'     => __( '3.1.1 Language of Page', 'esmo-accessibility-scanner' ),
				'category' => 'language',
				'severity' => 'major',
				'fix'      => __( 'Use a valid BCP 47 language code in the lang attribute.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'html-lang-valid',
			),
			'html-xml-lang-mismatch' => array(
				'wcag'     => __( '3.1.1 Language of Page', 'esmo-accessibility-scanner' ),
				'category' => 'language',
				'severity' => 'minor',
				'fix'      => __( 'Ensure lang and xml:lang attributes agree.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'html-xml-lang-mismatch',
			),
			'valid-lang' => array(
				'wcag'     => __( '3.1.2 Language of Parts', 'esmo-accessibility-scanner' ),
				'category' => 'language',
				'severity' => 'major',
				'fix'      => __( 'Use a valid language code on inline lang attributes.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'valid-lang',
			),

			// Landmarks / regions.
			'region' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Wrap page content in landmark regions (main, nav, header, footer).', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'region',
			),
			'landmark-one-main' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Provide exactly one <main> landmark on the page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-one-main',
			),
			'landmark-no-duplicate-main' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Avoid more than one main landmark on the page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-no-duplicate-main',
			),
			'landmark-unique' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Give landmarks of the same type unique accessible names.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-unique',
			),

			// Buttons.
			'button-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'buttons',
				'severity' => 'critical',
				'fix'      => __( 'Provide discernible text or an aria-label for the button.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'button-name',
			),
			'nested-interactive' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'buttons',
				'severity' => 'major',
				'fix'      => __( 'Do not nest interactive controls inside other interactive controls.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'nested-interactive',
			),

			// Document.
			'document-title' => array(
				'wcag'     => __( '2.4.2 Page Titled', 'esmo-accessibility-scanner' ),
				'category' => 'document',
				'severity' => 'major',
				'fix'      => __( 'Provide a non-empty, descriptive <title> for the page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'document-title',
			),
			'bypass' => array(
				'wcag'     => __( '2.4.1 Bypass Blocks', 'esmo-accessibility-scanner' ),
				'category' => 'keyboard',
				'severity' => 'major',
				'fix'      => __( 'Provide a skip link or landmarks so keyboard users can bypass repeated content.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'bypass',
			),
			'frame-title' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'document',
				'severity' => 'major',
				'fix'      => __( 'Provide a descriptive title attribute for each <iframe>.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'frame-title',
			),
			'meta-viewport' => array(
				'wcag'     => __( '1.4.4 Resize Text', 'esmo-accessibility-scanner' ),
				'category' => 'responsive',
				'severity' => 'major',
				'fix'      => __( 'Remove user-scalable=no and maximum-scale limits so users can zoom the page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'meta-viewport',
			),
			'tabindex' => array(
				'wcag'     => __( '2.4.3 Focus Order', 'esmo-accessibility-scanner' ),
				'category' => 'keyboard',
				'severity' => 'major',
				'fix'      => __( 'Avoid positive tabindex values; rely on natural DOM order for focus.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'tabindex',
			),

			// Lists.
			'list' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'major',
				'fix'      => __( 'Make sure <ul> and <ol> elements only directly contain <li>, <script> or <template> elements.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'list',
			),
			'listitem' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'major',
				'fix'      => __( 'Place every <li> element inside a <ul>, <ol> or an element with role="list".', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'listitem',
			),
			'definition-list' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'major',
				'fix'      => __( 'Make sure <dl> elements only contain properly ordered <dt> and <dd> groups, <script>, <template> or <div> elements.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'definition-list',
			),
			'dlitem' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'major',
				'fix'      => __( 'Place <dt> and <dd> elements inside a <dl> element.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'dlitem',
			),
			'p-as-heading' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'headings',
				'severity' => 'major',
				'fix'      => __( 'Use real heading elements (<h2>, <h3>, ...) instead of styling paragraphs to look like headings.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'p-as-heading',
			),

			// Controls and keyboard.
			'input-button-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'buttons',
				'severity' => 'critical',
				'fix'      => __( 'Give input buttons discernible text with the value attribute, aria-label or aria-labelledby.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'input-button-name',
			),
			'summary-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'buttons',
				'severity' => 'major',
				'fix'      => __( 'Give every <summary> element discernible text.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'summary-name',
			),
			'scrollable-region-focusable' => array(
				'wcag'     => __( '2.1.1 Keyboard', 'esmo-accessibility-scanner' ),
				'category' => 'keyboard',
				'severity' => 'major',
				'fix'      => __( 'Make scrollable regions reachable by keyboard, for example with tabindex="0" and an accessible name.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'scrollable-region-focusable',
			),
			'frame-focusable-content' => array(
				'wcag'     => __( '2.1.1 Keyboard', 'esmo-accessibility-scanner' ),
				'category' => 'keyboard',
				'severity' => 'major',
				'fix'      => __( 'Do not set tabindex="-1" on frames that contain focusable content.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'frame-focusable-content',
			),
			'target-size' => array(
				'wcag'     => __( '2.5.8 Target Size (Minimum)', 'esmo-accessibility-scanner' ),
				'category' => 'responsive',
				'severity' => 'major',
				'fix'      => __( 'Make touch targets at least 24 by 24 CSS pixels, or leave enough space around smaller targets.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'target-size',
			),
			'css-orientation-lock' => array(
				'wcag'     => __( '1.3.4 Orientation', 'esmo-accessibility-scanner' ),
				'category' => 'responsive',
				'severity' => 'major',
				'fix'      => __( 'Do not lock content to portrait or landscape orientation with CSS.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'css-orientation-lock',
			),
			'avoid-inline-spacing' => array(
				'wcag'     => __( '1.4.12 Text Spacing', 'esmo-accessibility-scanner' ),
				'category' => 'responsive',
				'severity' => 'major',
				'fix'      => __( 'Do not use !important on inline line-height, letter-spacing or word-spacing styles, so users can adjust text spacing.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'avoid-inline-spacing',
			),

			// Images and media.
			'role-img-alt' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'major',
				'fix'      => __( 'Give elements with role="img" an accessible name using aria-label or aria-labelledby.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'role-img-alt',
			),
			'server-side-image-map' => array(
				'wcag'     => __( '2.1.1 Keyboard', 'esmo-accessibility-scanner' ),
				'category' => 'images',
				'severity' => 'minor',
				'fix'      => __( 'Replace server-side image maps with client-side image maps or regular links.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'server-side-image-map',
			),
			'video-caption' => array(
				'wcag'     => __( '1.2.2 Captions (Prerecorded)', 'esmo-accessibility-scanner' ),
				'category' => 'media',
				'severity' => 'critical',
				'fix'      => __( 'Provide captions for video with a <track kind="captions"> element.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'video-caption',
			),
			'audio-caption' => array(
				'wcag'     => __( '1.2.1 Audio-only and Video-only (Prerecorded)', 'esmo-accessibility-scanner' ),
				'category' => 'media',
				'severity' => 'critical',
				'fix'      => __( 'Provide a transcript or captions for audio content.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'audio-caption',
			),
			'blink' => array(
				'wcag'     => __( '2.2.2 Pause, Stop, Hide', 'esmo-accessibility-scanner' ),
				'category' => 'media',
				'severity' => 'major',
				'fix'      => __( 'Remove <blink> elements; blinking content distracts and cannot be paused.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'blink',
			),
			'marquee' => array(
				'wcag'     => __( '2.2.2 Pause, Stop, Hide', 'esmo-accessibility-scanner' ),
				'category' => 'media',
				'severity' => 'major',
				'fix'      => __( 'Remove <marquee> elements or replace them with content users can pause.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'marquee',
			),
			'meta-refresh' => array(
				'wcag'     => __( '2.2.1 Timing Adjustable', 'esmo-accessibility-scanner' ),
				'category' => 'document',
				'severity' => 'critical',
				'fix'      => __( 'Do not refresh or redirect the page automatically with <meta http-equiv="refresh">.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'meta-refresh',
			),

			// Tables.
			'td-has-header' => array(
				'wcag'     => __( '1.3.1 Info and Relationships', 'esmo-accessibility-scanner' ),
				'category' => 'tables',
				'severity' => 'critical',
				'fix'      => __( 'Associate every data cell in large tables with a header cell (<th> or headers attribute).', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'td-has-header',
			),

			// ARIA widgets.
			'aria-meter-name' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Give elements with role="meter" an accessible name.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-meter-name',
			),
			'aria-progressbar-name' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Give elements with role="progressbar" an accessible name.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-progressbar-name',
			),
			'aria-tooltip-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Give elements with role="tooltip" discernible text.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-tooltip-name',
			),
			'aria-treeitem-name' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'major',
				'fix'      => __( 'Give elements with role="treeitem" an accessible name.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-treeitem-name',
			),
			'aria-deprecated-role' => array(
				'wcag'     => __( '4.1.2 Name, Role, Value', 'esmo-accessibility-scanner' ),
				'category' => 'aria',
				'severity' => 'minor',
				'fix'      => __( 'Replace deprecated ARIA roles with a current role.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'aria-deprecated-role',
			),

			// Best-practice landmark rules.
			'landmark-banner-is-top-level' => array(
				'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Do not nest the banner (site header) landmark inside another landmark.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-banner-is-top-level',
			),
			'landmark-contentinfo-is-top-level' => array(
				'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Do not nest the contentinfo (site footer) landmark inside another landmark.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-contentinfo-is-top-level',
			),
			'landmark-main-is-top-level' => array(
				'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Do not nest the main landmark inside another landmark.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-main-is-top-level',
			),
			'landmark-complementary-is-top-level' => array(
				'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Keep complementary (aside) landmarks at the top level of the page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-complementary-is-top-level',
			),
			'landmark-no-duplicate-banner' => array(
				'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Use only one banner (site header) landmark per page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-no-duplicate-banner',
			),
			'landmark-no-duplicate-contentinfo' => array(
				'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
				'category' => 'landmarks',
				'severity' => 'minor',
				'fix'      => __( 'Use only one contentinfo (site footer) landmark per page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'landmark-no-duplicate-contentinfo',
			),
			'skip-link' => array(
				'wcag'     => __( 'Best practice', 'esmo-accessibility-scanner' ),
				'category' => 'structure',
				'severity' => 'minor',
				'fix'      => __( 'Make sure the skip link points to an element that exists on the page.', 'esmo-accessibility-scanner' ),
				'doc'      => $axe . 'skip-link',
			),

			// Supplemental custom JS rules (prefixed esmoas-).
			'esmoas-generic-link-text' => array(
				'wcag'     => __( '2.4.4 Link Purpose (In Context)', 'esmo-accessibility-scanner' ),
				'category' => 'links',
				'severity' => 'minor',
				'fix'      => __( 'Replace generic link text such as "click here" or "read more" with text that describes the destination.', 'esmo-accessibility-scanner' ),
				'doc'      => 'https://www.w3.org/WAI/WCAG22/Understanding/link-purpose-in-context.html',
			),
			'esmoas-placeholder-as-label' => array(
				'wcag'     => __( '3.3.2 Labels or Instructions', 'esmo-accessibility-scanner' ),
				'category' => 'forms',
				'severity' => 'minor',
				'fix'      => __( 'Do not rely on placeholder text as the only label. Add a persistent visible label.', 'esmo-accessibility-scanner' ),
				'doc'      => 'https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html',
			),
			'esmoas-new-window-warning' => array(
				'wcag'     => __( '3.2.5 Change on Request', 'esmo-accessibility-scanner' ),
				'category' => 'links',
				'severity' => 'warning',
				'fix'      => __( 'Warn users when a link opens in a new window, for example by appending "(opens in new window)" to the link text.', 'esmo-accessibility-scanner' ),
				'doc'      => 'https://www.w3.org/WAI/WCAG22/Understanding/change-on-request.html',
			),
			'esmoas-pdf-link' => array(
				'wcag'     => __( '1.1.1 Non-text Content', 'esmo-accessibility-scanner' ),
				'category' => 'documents',
				'severity' => 'warning',
				'fix'      => __( 'Linked PDF detected. Verify the PDF is tagged and accessible; automated checks cannot validate PDF contents.', 'esmo-accessibility-scanner' ),
				'doc'      => 'https://www.w3.org/WAI/WCAG22/Understanding/non-text-content.html',
			),
		);

		$this->map = $rules;

		return $this->map;
	}
}
