# Changelog

All notable changes to Esmo Accessibility Scanner, called Accessibility Guardian up to 1.2.0. The WordPress.org changelog lives in [`readme.txt`](readme.txt).

## 1.3.0 — 2026-10-23

Renamed to **Esmo Accessibility Scanner** for the WordPress.org directory, plus a security hardening pass.

### Renamed
- Plugin name "Esmo Accessibility Scanner", slug and text domain `esmo-accessibility-scanner`, main file `esmo-accessibility-scanner.php`, repository `esmobg/Esmo-Accessibility-Scanner`, website https://esmobg.github.io/Esmo-Accessibility-Scanner/.
- Prefixes: `accg_` → `esmoas_` (tables, options, AJAX actions, nonces, hooks), `ACCG_` → `ESMOAS_`, namespace `AccessibilityGuardian\` → `EsmoAccessibilityScanner\`, CSS classes and ids `ag-` / `accg-` → `esmoas-`, custom rule ids `ag-…` → `esmoas-…`.
- Admin menu label "Accessibility Scanner".

### Upgrading from Accessibility Guardian
- Activation copies the 1.2.0 scans, issues, score history and settings (portable `INSERT … SELECT`, which also works on SQLite), renames stored custom rule ids, and deactivates Accessibility Guardian so automatic fixes are not applied twice. The old data stays until Accessibility Guardian is deleted.
- A notice on the Plugins screen (also the network Plugins screen) and the plugin's pages explains that the old plugin can be deleted, or, while an import is still pending, warns not to delete it yet. On multisite it checks every site, because deleting the old plugin removes its data on all of them.
- While Accessibility Guardian still runs (for example reactivated later), 1.3.0 skips its own front-end fixes so they are never applied twice, and the notice says that scans made with the old plugin are not imported.
- On a multisite where Accessibility Guardian is network-active, 1.3.0 can only be network-activated (per-site activation stops with an explanation), so the old plugin cannot keep writing scans next to it.
- The six `accg_` hooks keep working through `apply_filters_deprecated()` / `do_action_deprecated()`.
- Uninstall also removes leftover `accg_` tables and options, but only when Accessibility Guardian is no longer installed.
- The import is recorded separately (`esmoas_legacy_import`, plus the tables already copied in `esmoas_legacy_import_tables`); if a copy fails, for example because of a database timeout, it is retried at most once an hour, or right away when a scan is started, and copies only the rows that are still missing. New scans wait until the import is complete, so imported and new rows never collide.

### Security
- `esmoas_save_results` validates everything it receives: the page URL must be an http(s) URL on the site and one the scan queued (new `esmoas_scan_pages` table); each queued page is claimed once, atomically, in the same transaction as its rows (a request that fails or dies part-way leaves no rows behind), and takes its post id from the queue; nothing is accepted after the scan has finished; at most 150 rules per group, 25 elements per rule and 2,500 rows per page; rule ids, impacts and text lengths are checked; non-scalar values are ignored.
- Single-page scans accept only content with a front-end view that the current user may read (`read_post`); otherwise the Run Scan page falls back to a full scan.
- URLs added through `esmoas_scan_urls` must be http(s) URLs on the site; labels are plain text.
- New `esmoas_settings_capability` filter (default `manage_options`), so opening the reports to other roles no longer opens the settings and automatic fixes.
- Exports send `X-Content-Type-Options: nosniff`.
- The scan frame no longer allows form submission, and the scanner always injects the bundled axe-core instead of reusing a copy that the page provides.
- The "Settings saved" notice comes from the redirect instead of a site-wide transient.
- `finish_scan` is atomic: only the request that moves a scan out of `running` adds the history row and fires `esmoas_scan_completed`. The `passes` count is clamped.
- CI: every action pinned to a commit SHA, `persist-credentials: false`, Plugin Check runs on the built artifact in a job that executes no repository scripts, Pages permissions only in the deploy job, Dependabot for actions and Composer.
- Releases: no asset overwrite on re-pushed tags, `.sha256` checksum and build provenance attestation.
- Build tools: the zip contains only git-tracked files and no symlinks; `build-site.py` validates the release version and refuses to delete folders outside the repository or with tracked files.
- `SECURITY.md` with private vulnerability reporting.

### Fixed
- A running scan is marked as failed only after 15 minutes without progress (new `updated_at` column), not one hour after it started, so long scans are no longer lost when another scan starts.
- HTML snippets are cut by code points: an emoji split in half used to make the whole page's results invalid JSON.
- Very noisy pages send less detail, step by step, until the results fit the 512 KB limit, instead of losing the page.
- Page titles and term names in the scan log no longer show HTML entities.
- Repositories resolve the table prefix per query, so they follow `switch_to_blog()`.
- Long HTML snippets on the Issues page wrap instead of scrolling sideways (axe `scrollable-region-focusable`, WCAG 1.4.10 reflow).

### Website
- Esmo brand blue (`#2356EB`) palette, new icon and banners.
- Content Security Policy (`default-src 'self'`, no inline scripts or styles) and referrer policy; the pre-paint theme script is now `assets/js/theme-init.js`.

### Removed
- Unused code: `ACCG_PLUGIN_BASENAME`, the `dashboardUrl`, `issuesUrl` and `axeVersion` scanner settings, and `severityFor()` in the custom rules.

## 1.2.0 — 2026-10-08

Compatibility and WordPress.org release preparation.

### Compatibility
- Tested with WordPress 6.4 (minimum), 7.1 (latest) and 7.2-alpha, and with PHP 8.0, 8.3, 8.4 and 8.5.
- Plugin Check 2.1.0 (all categories): no errors or warnings.
- CSV export passes every `fputcsv()` argument explicitly (omitting `$escape` is deprecated since PHP 8.4).
- Filter callbacks no longer use strict types that another plugin returning `null` could turn into a fatal error.
- Request values are read without `(string)` casts, so array input cannot trigger "Array to string conversion" warnings.
- Scripts load with `strategy => defer`; admin assets are enqueued by screen hook suffix; axe-core has its own cache-busting version.
- Templates receive their data through `load_template()` arguments instead of `extract()`.
- Admin notices use the `success` type and every screen has `wp-header-end`.

### Added
- Best-practice checks (headings, landmarks, page structure), on by default, reported as warnings.
- "Scan accessibility" toolbar link for one-click single-page scans.
- Multisite support: network activation, setup for new sites, table removal when a site is deleted, uninstall on every site.
- Developer hooks: `accg_scan_urls`, `accg_axe_options`, `accg_required_capability`, `accg_scan_completed`.
- Catalog entries for 40 more axe-core rules (lists, target size, captions, keyboard access, landmarks and more).
- Progress chart scaled to the data, with a point per scan and a "from → to" summary.
- Build script (`bin/build-zip.py`), screenshot and asset tooling (`bin/screenshots/`), CI and release workflows.

### Changed
- The WordPress toolbar is excluded from axe-core and the supplemental checks.
- Issue messages and fix suggestions keep element names such as `<ul>` and `<main>`; exports contain plain text.
- HTML snippets are stored as text, so snippets of form fields, SVGs and frames are no longer lost.
- Automatic fixes: no duplicate skip link on block themes, `:focus-visible` focus outline, link underlining skips buttons and menus, a field labelled only by `title` keeps it, and fixes re-apply to content added later.
- Front-end classes use the `accg-` prefix.
- The scan frame is sandboxed (no top-level navigation or pop-ups) and pages the browser refuses to frame are reported as failures.
- Admin colours meet WCAG AA contrast; the plugin's own screens pass axe-core.

### Fixed
- Cancelling a scan saved it as complete and made it the latest report; it is now stored as `cancelled` and kept out of the history.
- The Cancel button was always visible because core button styles overrode the `hidden` attribute.
- Settings checkboxes for post types ran together on one line.
- Exported CSV cells starting with `=`, `+`, `-` or `@` could be run as spreadsheet formulas.

### Removed
- Migration and clean-up of the pre-1.1 `ag_*` tables and options (never published on WordPress.org; the short prefix could match another plugin's data).
- The unused `accg_scan_status` AJAX endpoint and the unused "batch size" setting.

## 1.1.1 — 2026-08-17
- Site score for multi-page scans is the average of per-page scores instead of penalizing the total issue count.
- Scan results show scores as N/100.

## 1.1.0 — 2026-08-13
- WordPress.org readiness: `accg_` prefix, GPLv2 license file, unminified axe-core source, translatable rule catalog, PHP 8.0 requirement.
- axe "serious" impact maps to Major severity.
- Hardened settings saving, payload size limits and uninstall.
- Full-site scans capped at 1,000 URLs by default (`accg_scan_url_limit`).

## 1.0.0 — 2026-06-04
- Initial release: single-page and full-site scans with axe-core, supplemental checks, scoring, dashboard, CSV/JSON export.
