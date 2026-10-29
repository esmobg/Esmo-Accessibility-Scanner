<p align="center">
  <img src="wp-org-assets/banner-1544x500.png" alt="Esmo Accessibility Scanner: WCAG 2.2 AA audits with axe-core, right inside WordPress" width="772">
</p>

# Esmo Accessibility Scanner for WordPress

[![CI](https://github.com/esmobg/Esmo-Accessibility-Scanner/actions/workflows/ci.yml/badge.svg)](https://github.com/esmobg/Esmo-Accessibility-Scanner/actions/workflows/ci.yml)
![Version](https://img.shields.io/badge/version-1.3.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.4%E2%80%937.1-21759b)
![PHP](https://img.shields.io/badge/PHP-8.0%E2%80%938.5-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)
[![Website](https://img.shields.io/badge/website-esmobg.github.io-2356eb)](https://esmobg.github.io/Esmo-Accessibility-Scanner/)

**Website:** https://esmobg.github.io/Esmo-Accessibility-Scanner/

Esmo Accessibility Scanner is a WordPress plugin that checks your site against **WCAG 2.2 Level A and AA** from the admin dashboard. It runs the [axe-core](https://github.com/dequelabs/axe-core) engine in the administrator's browser against the fully rendered front end of each page. You get a score, a prioritized list of issues with the exact element and a recommended fix, a progress chart, exports and optional one-click fixes.

There is no external service, no account and nothing to install on the server: no Node.js, no headless browser, no API key. If your site runs WordPress, it can be scanned.

> **Formerly Accessibility Guardian.** The plugin was renamed in 1.3.0 for the WordPress.org directory. Activating Esmo Accessibility Scanner imports the data of Accessibility Guardian 1.2 and deactivates it; see [Upgrading from Accessibility Guardian](#upgrading-from-accessibility-guardian).

> **Automated testing is not a compliance certificate.** Tools like this one find a large share of common problems quickly, but some WCAG requirements can only be checked by a person. Use the report to fix what is found, then test key pages with a keyboard and a screen reader.

---

## Contents

- [Screenshots](#screenshots)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Upgrading from Accessibility Guardian](#upgrading-from-accessibility-guardian)
- [Usage](#usage)
- [Scoring](#scoring)
- [Automatic fixes](#automatic-fixes)
- [How it works](#how-it-works)
- [Architecture](#architecture)
- [Developer hooks](#developer-hooks)
- [Privacy](#privacy)
- [Limitations](#limitations)
- [FAQ](#faq)
- [Development](#development)
- [Releasing](#releasing)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)

## Screenshots

All screenshots are taken from a real WordPress 7.1 site (a demo bakery site with typical problems) by [`bin/screenshots/capture.js`](bin/screenshots/capture.js).

| Dashboard | Completed scan |
| --- | --- |
| ![Dashboard with site score, severity breakdown, WCAG categories and progress over time](wp-org-assets/screenshot-1.png) | ![A completed full-site scan with the score summary and per-page log](wp-org-assets/screenshot-2.png) |
| **Issues** | **Settings** |
| ![Issues page listing each finding with WCAG reference, element, HTML snippet and recommended fix](wp-org-assets/screenshot-3.png) | ![Settings page with content to scan, WCAG level, best-practice checks and automatic fixes](wp-org-assets/screenshot-4.png) |

## Features

- **Full-site and single-page scans.** Posts, pages, public custom post types (including WooCommerce products) and optional category and tag archives. A **Scan accessibility** link in the toolbar starts a single-page scan of whatever published page you are viewing.
- **Analysis of the real, rendered page** with axe-core 4.10.2, so computed colours, ARIA states, generated content and theme markup are all taken into account.
- **WCAG 2.0, 2.1 and 2.2** at Level A or AA (your choice).
- **Best-practice checks** (on by default) for headings, landmarks and structure, reported as warnings.
- **Supplemental checks** that axe-core does not cover:
  - generic link text such as "click here", "read more" or "more"
  - form fields that rely on a placeholder instead of a label
  - links that open a new window or tab without saying so
  - linked PDF documents that need a manual accessibility check
- **Actionable issue records:** severity, WCAG success criterion, category, page URL, CSS path to the element, its HTML, a recommended fix and a documentation link.
- **Accessibility score** (0–100) per scan with Excellent / Good / Needs Improvement / Poor bands.
- **Dashboard:** score, pages scanned, errors, warnings and passed checks, issues by severity, WCAG category distribution, a progress-over-time chart, the most common problems and recent scans.
- **Issues page** with severity tabs and pagination.
- **Optional automatic fixes** applied on the front end through WordPress hooks (see [Automatic fixes](#automatic-fixes)).
- **CSV and JSON export** of any scan.
- **Multisite:** per-site or network activation, automatic setup for new sites, clean-up when a site is deleted.
- **Translation-ready** (`esmo-accessibility-scanner` text domain).
- **Accessible itself:** the plugin's admin screens pass axe-core WCAG 2.2 AA and best-practice checks.

## Requirements

| | |
| --- | --- |
| WordPress | 6.4 or later (tested up to 7.1; also checked against 7.2-alpha) |
| PHP | 8.0 or later (tested on 8.0, 8.3, 8.4 and 8.5) |
| Browser | Any current Chrome, Edge, Firefox or Safari for the admin who runs scans |
| Framing | Your pages must be allowed in a same-origin frame (see below) |

The scanner loads each page in a hidden frame on your own domain. That works unless your site sends `X-Frame-Options: DENY` or `Content-Security-Policy: frame-ancestors 'none'`. `X-Frame-Options: SAMEORIGIN` and `frame-ancestors 'self'` are fine. Pages that cannot be framed are reported as "could not be scanned" and the rest of the scan continues.

## Installation

**From WordPress.org** (once the listing is live)

1. Go to **Plugins > Add New Plugin** and search for "Esmo Accessibility Scanner".
2. Click **Install Now**, then **Activate**.

**From a release zip**

1. Download `esmo-accessibility-scanner-<version>.zip` from the [latest GitHub release](https://github.com/esmobg/Esmo-Accessibility-Scanner/releases/latest).
2. Go to **Plugins > Add New Plugin > Upload Plugin**, choose the zip and click **Install Now**, then **Activate**.

> Do not use GitHub's green **Code > Download ZIP** button for installation. That archive is the development repository: it unpacks to a folder called `Esmo-Accessibility-Scanner-main` and includes tests and tooling. Use the release zip instead.

**From git** (for development)

```bash
cd wp-content/plugins
git clone https://github.com/esmobg/Esmo-Accessibility-Scanner.git esmo-accessibility-scanner
```

The plugin includes its own autoloader, so `composer install` is only needed to run the tests.

## Upgrading from Accessibility Guardian

Until version 1.2.0 this plugin was called **Accessibility Guardian** (folder `accessibility-guardian`, prefix `accg_`). WordPress.org asked for a more distinctive name, so 1.3.0 is **Esmo Accessibility Scanner** with the slug `esmo-accessibility-scanner`. For WordPress that is a different plugin, so it does not replace the old one automatically.

1. Install Esmo Accessibility Scanner and activate it. It copies the scans, issues, score history and settings of Accessibility Guardian into its own tables, and deactivates Accessibility Guardian so that the automatic fixes are not applied twice. On multisite, network activation does this on every site.
2. Check the dashboard: your previous scans and score history are there.
3. Delete Accessibility Guardian on the Plugins screen. Its uninstaller removes its old copy of the data; your imported data is not touched.

The import runs once per site and never overwrites data already stored by Esmo Accessibility Scanner. Code that uses the old `accg_` hooks keeps working (see [Developer hooks](#developer-hooks)).

## Usage

1. Open **Accessibility Scanner > Run Scan** and click **Start full site scan**. The progress bar and log show each page as it is checked. Keep the tab open until the scan finishes, or click **Cancel** to stop after the current page (a cancelled scan keeps its partial results but does not replace your latest complete report).
2. To check one page, open it on the front end while logged in and click **Scan accessibility** in the toolbar. You can also go to `wp-admin/admin.php?page=esmo-accessibility-scanner-scan&post_id=123`.
3. Review the findings under **Issues**. Use the severity tabs to focus on Critical and Major problems first. Each card shows the page, the element, its HTML and a recommended fix.
4. Check the **Dashboard** for the score, the breakdown by severity and WCAG category, the progress chart and the most common problems.
5. Export any scan with the **CSV** or **JSON** buttons on the dashboard.
6. Under **Settings**, choose the post types to scan, whether to include term archives, the WCAG level (A or AA), whether to run best-practice checks, and which automatic fixes to enable.

## Scoring

Every scanned page starts at 100 and loses points for each issue, with a minimum of 0:

| Severity | Penalty | Typical examples |
| --- | --- | --- |
| Critical | −10 | missing image alt text, unlabeled buttons, missing document language |
| Major | −5 | low contrast, missing form labels, broken list structure |
| Minor | −2 | generic link text, placeholder-only fields |
| Warning | −1 | best-practice findings, new-window links, PDFs |
| Needs review | 0 | results axe-core cannot decide on its own (for example contrast over a background image or a pseudo-element): listed for a person to check, but not scored (since 1.3.1) |

The **site score** is the average of the per-page scores, so one bad page does not hide an otherwise good site and pages without issues count as 100.

| Band | Score |
| --- | --- |
| Excellent | 95–100 |
| Good | 80–94 |
| Needs Improvement | 60–79 |
| Poor | below 60 |

On the dashboard, **Errors** are Critical + Major issues and **Warnings** are Minor + Warning issues. Needs review items are shown separately.

## Automatic fixes

All fixes are off by default. They use WordPress hooks and small front-end assets; your theme files are never edited. Enable one at a time and check your site.

| Fix | What it does | WCAG | Notes |
| --- | --- | --- | --- |
| Add missing language attribute | Sets `lang` on `<html>` when the theme leaves it out | 3.1.1 | Uses the site language |
| Add a skip-to-content link | Adds a keyboard skip link at the top of every page | 2.4.1 | Classic themes only; block themes already get one from WordPress. Target filterable with `esmoas_skip_link_target` |
| Add a visible focus outline | Shows a clear outline on focused links and controls | 2.4.7 | Uses `:focus-visible`, so mouse clicks are unaffected |
| Underline links in content | Underlines links inside post content | 1.4.1 | Buttons and navigation menus are left alone |
| Warn about links opening new windows | Adds a hidden "(opens in a new window)" to `target="_blank"` links | 3.2.5 | Also adds `rel="noopener"` |
| Allow zooming | Removes `user-scalable=no` and `maximum-scale` limits | 1.4.4 | |
| Remove positive tabindex values | Resets `tabindex` greater than 0 to 0 | 2.4.3 | |
| Add labels to search forms | Adds `aria-label` to the classic `get_search_form()` field | 3.3.2 | The Search block is already labelled |
| Remove redundant title attributes | Removes `title` from links, buttons and fields that already have another accessible name | — | A field labelled only by `title` keeps it |

Fixes that change elements are re-applied to content added after the page loads, for example by the Interactivity API router or "load more" buttons.

## How it works

```mermaid
flowchart LR
  admin[Admin clicks Start scan] --> start["AJAX esmoas_start_scan"]
  start --> urls[UrlProvider builds the URL queue]
  urls --> queue[scan_id + queue returned to the browser]
  queue --> loop[scanner.js takes the next URL]
  loop --> iframe[Load the page in a hidden same-origin frame]
  iframe --> run["Inject axe-core and run it, excluding the toolbar"]
  run --> custom[Run the supplemental checks]
  custom --> save["AJAX esmoas_save_results"]
  save --> norm[ResultNormalizer maps results to issues]
  norm --> store[IssueRepository stores the issues]
  store --> loop
  loop --> done["AJAX esmoas_finish_scan"]
  done --> score[ScoreCalculator stores the score and history]
  score --> dash[Dashboard and Issues pages]
```

1. **Start.** `esmoas_start_scan` builds the queue of URLs (home page, published content in ID order, optional term archives, capped at 1,000) and creates a scan record.
2. **Scan.** For each URL, `assets/js/scanner.js` loads the page in a sandboxed, same-origin frame, lets it settle, injects the bundled axe-core and runs it with the configured WCAG tags. `assets/js/custom-rules.js` adds the supplemental checks. Settling (since 1.3.1): after a short pause (`esmoas_scan_settle_delay`, 500 ms) for scripts that finish after the load event, running transitions and finite animations jump to their end state, so a half-faded element is not reported with a contrast it never has at rest; the WordPress toolbar, which visitors do not have, is removed from the frame's copy of the page.
3. **Save.** The results are sent to `esmoas_save_results`, checked and normalized into issue rows (severity, WCAG reference, category, fix, documentation link) and stored. The server treats the browser's data as untrusted: the page must be one the scan queued (the queue is kept on the server), each page can be saved once, its post id comes from the queue, and at most 150 rules per group, 25 elements per rule and 2,500 rows per page are kept.
4. **Finish.** `esmoas_finish_scan` calculates the per-page and site score, stores the totals and adds a point to the history, or marks the scan as cancelled.

All AJAX requests require the `esmoas_scan` nonce and the capability returned by `esmoas_required_capability` (default `manage_options`). Settings use their own capability, `esmoas_settings_capability` (also `manage_options`).

## Architecture

```text
esmo-accessibility-scanner/
├── esmo-accessibility-scanner.php     Plugin header, constants, autoloader, hooks
├── uninstall.php                  Removes tables and options (every site on multisite)
├── readme.txt                     WordPress.org readme
├── src/
│   ├── Plugin.php                 Service container, hook wiring, capability helper
│   ├── Activation/Installer.php   Tables (dbDelta), default settings, multisite setup
│   ├── Admin/AdminMenu.php        Menu pages, settings form, toolbar scan link
│   ├── Admin/AssetManager.php     Admin scripts and styles, scanner configuration
│   ├── Scan/UrlProvider.php       Builds the URL queue
│   ├── Scan/ScanController.php    AJAX endpoints: start, save, finish
│   ├── Scan/ResultNormalizer.php  axe results → issue rows
│   ├── Scan/ScoreCalculator.php   Page and site scores, bands
│   ├── Rules/RuleCatalog.php      Rule → WCAG reference, category, severity, fix
│   ├── Storage/ScanRepository.php, IssueRepository.php
│   ├── Export/                    CSV and JSON exporters, download endpoint
│   └── Fixes/AutoFixer.php        Optional front-end fixes
├── assets/
│   ├── js/axe.min.js, axe.js      axe-core 4.10.2 (MPL-2.0), minified and source
│   ├── js/scanner.js              Scan orchestrator (runs in wp-admin)
│   ├── js/custom-rules.js         Supplemental checks
│   ├── js/dashboard.js            Progress chart
│   ├── js/frontend-fixes.js       DOM-based automatic fixes
│   └── css/admin.css, frontend-fixes.css
├── templates/                     Dashboard, Run Scan, Issues and Settings views
└── languages/                     esmo-accessibility-scanner.pot
```

Repository-only folders (not in the release zip): `tests/` (PHPUnit), `bin/` (build and screenshot tools), `docs/` (QA notes and the WordPress.org submission guide), `site/` (the project website), `wp-org-assets/` (WordPress.org icons, banners, screenshots and Live Preview blueprint) and `.github/` (CI and releases).

### Stored data

| Name | Type | Contents |
| --- | --- | --- |
| `{prefix}esmoas_scans` | table | One row per scan: type, status (`running`, `complete`, `cancelled`, `failed`), URL counts, score, totals |
| `{prefix}esmoas_issues` | table | One row per finding |
| `{prefix}esmoas_history` | table | Score history for the progress chart (complete scans only) |
| `{prefix}esmoas_scan_pages` | table | Pages queued by running scans (URL hash, post id, saved flag), so each page is saved once; emptied when a scan ends |
| `esmoas_settings` | option | Post types, term archives, WCAG level, best-practice checks, enabled fixes |
| `esmoas_db_version` | option | Installed schema version |
| `esmoas_legacy_import`, `esmoas_legacy_import_tables` | options | Progress of the one-time import from Accessibility Guardian (`settings`, then `done`, and the tables already copied); a failed copy is retried at most once an hour or when a scan starts |

Deleting the plugin removes all of it, on every site of a network. Leftovers from Accessibility Guardian (`accg_` tables and options) are removed too, unless Accessibility Guardian is still installed: then they belong to it.

### Endpoints

| Endpoint | Purpose |
| --- | --- |
| `wp_ajax_esmoas_start_scan` | Build the URL queue and create a scan |
| `wp_ajax_esmoas_save_results` | Store the results for one URL |
| `wp_ajax_esmoas_finish_scan` | Calculate the score and close the scan (`cancelled=1` for a cancelled scan) |
| `admin_post_esmoas_export` | Download a scan as CSV or JSON (nonce `esmoas_export`) |

## Developer hooks

### Filters

| Filter | Default | Description |
| --- | --- | --- |
| `esmoas_scan_url_limit` | `1000` | Maximum number of URLs in a full-site scan (clamped to 1–10,000) |
| `esmoas_scan_urls` | built queue | The full-site URL queue before it is capped. Each entry is `[ 'url' => string, 'post_id' => int, 'label' => string ]`. Second argument: the limit |
| `esmoas_axe_options` | `[ 'runOnly' => [ 'type' => 'tag', 'values' => [...] ] ]` | Options passed to `axe.run()` for every page |
| `esmoas_required_capability` | `manage_options` | Capability needed to view reports, run scans and export |
| `esmoas_settings_capability` | `manage_options` | Capability needed to change the settings, including the site-wide automatic fixes (since 1.3.0) |
| `esmoas_skip_link_target` | `#content` | `href` of the skip link added by the skip-link fix |
| `esmoas_scan_settle_delay` | `500` | Milliseconds a scanned page may settle after loading before axe runs (0–5,000; since 1.3.1) |
| `esmoas_new_window_phrases` | `new window`, `new tab` in the admin's and the site's language | Extra phrases that count as warning that a link opens a new window, besides the built-in English, Bulgarian, Russian, German, French, Spanish, Italian, Portuguese, Dutch, Polish and Romanian ones (since 1.3.1) |

### Actions

| Action | Arguments | Description |
| --- | --- | --- |
| `esmoas_scan_completed` | `int $scan_id`, `array $totals` (`score`, `errors`, `warnings`, `passes`) | Fires after a scan finishes and its score is stored (not for cancelled scans) |

Up to 1.2.0 these hooks were called `accg_…` (for example `accg_scan_urls`). The old names still work: their callbacks run first, and WordPress shows a deprecation notice when `WP_DEBUG` is on.

### Examples

```php
// Also scan a landing page that is not a post, and drop a private section.
add_filter( 'esmoas_scan_urls', function ( array $urls ) {
	$urls[] = array( 'url' => home_url( '/campaign/' ), 'post_id' => 0, 'label' => 'Campaign landing page' );

	return array_values( array_filter( $urls, fn ( $entry ) => ! str_contains( $entry['url'], '/members/' ) ) );
} );

// Ignore a third-party widget and turn off one rule.
add_filter( 'esmoas_axe_options', function ( array $options ) {
	$options['rules'] = array( 'region' => array( 'enabled' => false ) );

	return $options;
} );

// Let editors run scans and see reports. Settings stay with administrators.
add_filter( 'esmoas_required_capability', fn () => 'edit_others_posts' );

// Notify the team when a scan finishes with a low score.
add_action( 'esmoas_scan_completed', function ( int $scan_id, array $totals ) {
	if ( $totals['score'] < 80 ) {
		wp_mail( get_option( 'admin_email' ), 'Accessibility score dropped', sprintf( 'Scan #%d scored %d/100.', $scan_id, $totals['score'] ) );
	}
}, 10, 2 );
```

## Privacy

- Scans run on your own site and in your own browser. Page content and results are never sent to the plugin author or any third party.
- Results are stored in your WordPress database and deleted when the plugin is deleted.
- Documentation links point to dequeuniversity.com and w3.org and are only opened when clicked.
- Pages are scanned as the logged-in administrator, so scripts on those pages run as they do when you open them yourself, and analytics may record the page views.

## Limitations

- Scans run in the browser one page at a time; the admin tab must stay open. Each page has a 30-second timeout.
- Pages are checked at a desktop width (1280 px), so issues that only appear on small screens may be missed.
- Content behind interactions (menus that open on click, modal dialogs, tabs) is checked in its initial state only.
- Very strict `script-src` Content Security Policies can prevent axe-core from loading in the scan frame.
- Automated checks cannot judge things like the quality of alternative text, reading order or captions. Plan a manual review too.

## FAQ

**Does this make my site compliant with the ADA, the EAA, EN 301 549 or Section 508?**
No tool can do that on its own. It finds many common failures quickly; a manual review is still needed.

**Why is a page "could not be scanned"?**
Usually because it cannot be framed (`X-Frame-Options: DENY`, `frame-ancestors 'none'`), it took longer than 30 seconds to load, or a strict Content Security Policy blocked axe-core.

**Will the toolbar or the plugin's own markup show up in the results?**
No. The WordPress toolbar is excluded from every check.

**Does it slow down my site for visitors?**
No. Nothing is loaded on the front end unless you enable an automatic fix. Enabled fixes add one small stylesheet and, for some fixes, one small deferred script (a few KB each).

**Does it work with page builders and block themes?**
Yes. It checks the final rendered HTML, whatever produced it. The automatic fixes are aware of block themes.

## Development

```bash
composer install       # PHPUnit 9.6 (PHP 8.0) or 10.5 (PHP 8.1+)
composer test          # run the unit tests
composer lint          # php -l on every PHP file
```

The PHPUnit suite in `tests/phpunit/` covers the pure-logic classes (`ScoreCalculator`, `ResultNormalizer`, `RuleCatalog`, `UrlProvider`, `CsvExporter`) with lightweight WordPress function stubs, so no WordPress install is needed.

### Running WordPress locally with Playground

[WordPress Playground](https://wordpress.github.io/wordpress-playground/) runs WordPress in Node.js with no database or web server to set up.

```bash
python bin/build-zip.py --dir .build
npx @wp-playground/cli@latest server --port=9400 --login \
  --mount-dir .build/esmo-accessibility-scanner /wordpress/wp-content/plugins/esmo-accessibility-scanner \
  --mount-dir bin/screenshots /wordpress/esmoas-tools \
  --blueprint=bin/screenshots/blueprint.json
```

Open http://127.0.0.1:9400. The blueprint activates the plugin and creates a small demo site with typical accessibility problems. Change `preferredVersions` in the blueprint to test other WordPress and PHP versions (for example `"wp": "6.4", "php": "8.0"`).

### Plugin Check

Before every release, run [Plugin Check](https://wordpress.org/plugins/plugin-check/) on the built plugin (all categories, errors and warnings). CI does this automatically on every push. Locally, install the Plugin Check plugin in Playground and use **Tools > Plugin Check**, or run:

```bash
wp plugin check esmo-accessibility-scanner
```

### Regenerating screenshots and WordPress.org assets

```bash
npm install --no-save playwright && npx playwright install chromium
# with the Playground server from above running:
node bin/screenshots/capture.js http://127.0.0.1:9400 wp-org-assets
node bin/screenshots/render-brand.js     # icon-128/256 and banner-772/1544 from icon.svg and brand.html
```

### Website

The project website (https://esmobg.github.io/Esmo-Accessibility-Scanner/) lives in `site/`: plain HTML, CSS and a little JavaScript, no build tools. `python bin/build-site.py` assembles it into `.site-build/`, copying the icon, banner and screenshots from `wp-org-assets/` and linking the download buttons to the latest published GitHub release that has the zip attached (so a version bump on `main` never produces broken links). The [Website workflow](.github/workflows/pages.yml) deploys it to GitHub Pages whenever `site/` or `wp-org-assets/` changes and after every successful release.

To preview it locally:

```bash
python bin/build-site.py --out .site-build/Esmo-Accessibility-Scanner
python -m http.server 8765 --directory .site-build
# open http://127.0.0.1:8765/Esmo-Accessibility-Scanner/
```

### Translations

Regenerate the template after changing strings:

```bash
wp i18n make-pot . languages/esmo-accessibility-scanner.pot --exclude=tests,bin,docs,dist,.build,vendor,node_modules
```

## Releasing

1. Update the version in `esmo-accessibility-scanner.php` (header and `ESMOAS_VERSION`), `Stable tag` in `readme.txt`, the changelog in `readme.txt` and [`CHANGELOG.md`](CHANGELOG.md).
2. Build the zip: `python bin/build-zip.py`. It reads the version from the plugin header, refuses to build if `Stable tag` does not match, excludes everything listed in [`.distignore`](.distignore) and always uses `esmo-accessibility-scanner/` as the root folder.
3. Push a tag such as `v1.3.0`. The [release workflow](.github/workflows/release.yml) builds the same zip and attaches it to a GitHub release with a `.sha256` checksum and a signed build provenance attestation (check it with `gh attestation verify esmo-accessibility-scanner-1.3.0.zip --repo esmobg/Esmo-Accessibility-Scanner`).
4. Publish to WordPress.org with SVN as described in [`docs/qa/wordpress-org-submit.md`](docs/qa/wordpress-org-submit.md).

## Contributing

Issues and pull requests are welcome. Please:

- follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) (tabs, `array()`, Yoda conditions);
- prefix everything global with `esmoas_` / `ESMOAS_` / `EsmoAccessibilityScanner\`;
- sanitize input, escape output and check nonces and capabilities on every request;
- add or update tests for logic changes and make sure CI passes.

To report a security problem, please do not open a public issue; see [`SECURITY.md`](SECURITY.md).

## Changelog

See [`CHANGELOG.md`](CHANGELOG.md).

## License

Esmo Accessibility Scanner is licensed under the [GPL-2.0-or-later](LICENSE.txt).

It bundles [axe-core](https://github.com/dequelabs/axe-core) 4.10.2 by Deque Systems, licensed under the [Mozilla Public License 2.0](https://www.mozilla.org/MPL/2.0/), which is GPL-compatible. The unminified source is included as `assets/js/axe.js`.
