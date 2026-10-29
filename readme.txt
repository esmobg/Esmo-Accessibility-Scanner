=== Esmo Accessibility Scanner ===
Contributors: esmobg
Tags: accessibility, wcag, a11y, accessibility checker, audit
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Check your site against WCAG 2.2 AA with axe-core, right in wp-admin. Get a score, prioritized issues, fix guidance and optional auto-fixes.

== Description ==

Esmo Accessibility Scanner (formerly Accessibility Guardian) checks your WordPress site against **WCAG 2.2 Level A and AA** from the admin dashboard. It runs the widely used [axe-core](https://github.com/dequelabs/axe-core) engine in your browser against the fully rendered front end of each page, so it sees what visitors see: computed colour contrast, ARIA, headings, landmarks, form labels, images, links and more.

No external service, no account, no Node.js or headless browser on the server. If your site runs WordPress, it can be scanned.

= What you get =

* **Full-site or single-page scans.** Posts, pages, public custom post types (including WooCommerce products) and, optionally, category and tag archives. Start a single-page scan from the "Scan accessibility" link in the toolbar while viewing any published page.
* **Real rendered-page analysis** with axe-core 4.10 plus extra checks for generic link text ("click here"), placeholder-only form fields, links that open new windows without warning, and linked PDF documents.
* **Best-practice checks** (optional, on by default) for headings, landmarks and page structure. They are reported as warnings because they are not strict WCAG failures.
* **Clear, actionable issues.** Every finding has a severity (Critical, Major, Minor, Warning), the WCAG success criterion, the affected page, the element and its HTML, a recommended fix and a link to documentation.
* **An accessibility score** from 0 to 100 for every scan, with Excellent, Good, Needs Improvement and Poor bands, and a progress-over-time chart.
* **Dashboard** with issues by severity, WCAG category distribution, the most common problems and recent scans.
* **Optional automatic fixes** you can switch on one by one: skip link, visible keyboard focus, underlined content links, a language attribute, new-window warnings, zoom-friendly viewport, removal of positive tabindex and redundant title attributes, and search-form labels.
* **CSV and JSON export** of every scan for your team, agency or audit records.
* **Works on single sites and multisite networks**, including network activation.

= What automated testing can and cannot do =

Automated tools, including this one, find a meaningful share of accessibility problems, but not all of them. Many WCAG requirements (meaningful alternative text, logical reading order, captions, keyboard-only workflows, plain language) still need a human review. Esmo Accessibility Scanner helps you find and fix issues faster; it does not certify that a site is accessible or legally compliant with the ADA, the European Accessibility Act, EN 301 549 or Section 508.

= Privacy =

* Scans run on your own site and in your own browser. Page content and results are never sent to the plugin author or any third party.
* Results are stored in your WordPress database and removed when you delete the plugin.
* The issues list links to rule documentation on dequeuniversity.com and w3.org. Those sites are only contacted if you click a link.
* While scanning, pages are loaded in a hidden frame as the logged-in administrator, so scripts on your pages run just as they do when you open them yourself. Analytics or ads on your site may count those page views.
* Automatic fixes only change your site's front end, and only the ones you enable.

= Third-party library =

This plugin bundles [axe-core 4.10.2](https://github.com/dequelabs/axe-core/tree/v4.10.2) by Deque Systems, licensed under the Mozilla Public License 2.0, which is compatible with the GPL. The minified file is `assets/js/axe.min.js`; the unminified source is included as `assets/js/axe.js`.

= For developers =

Filters and actions let you change the scan queue, the axe-core options, the capabilities needed for reports and for settings, and more. See the [developer documentation on GitHub](https://github.com/esmobg/Esmo-Accessibility-Scanner#developer-hooks).

== Installation ==

1. In your dashboard go to **Plugins > Add New Plugin**, search for "Esmo Accessibility Scanner", then click **Install Now** and **Activate**.
2. Open **Accessibility Scanner > Run Scan** and click **Start full site scan**. Keep the tab open until the scan finishes; you can cancel at any time.
3. Review the results under **Accessibility Scanner > Issues** and the **Dashboard**.
4. Optionally choose which content to scan and enable automatic fixes under **Accessibility Scanner > Settings**.

If you used Accessibility Guardian 1.2 or earlier, activating Esmo Accessibility Scanner imports its scans and settings and deactivates it. You can then delete Accessibility Guardian.

To install manually, upload the `esmo-accessibility-scanner` folder to `/wp-content/plugins/` and activate it on the Plugins screen.

== Frequently Asked Questions ==

= Does this make my site compliant with the ADA or the European Accessibility Act? =

No tool can do that on its own. Automated checks catch many common problems quickly, but some WCAG requirements can only be verified by a person. Use the report to fix what is found, then test key pages with a keyboard and a screen reader.

= Does scanning need anything installed on the server? =

No. The scan runs in the administrator's browser using a hidden frame on your own site, so standard WordPress hosting is all you need.

= Why must I keep the tab open during a scan? =

The axe-core engine runs in your browser, one page at a time. If you close the tab, the scan stops. A scan that has made no progress for 15 minutes is marked as failed when the next scan starts.

= A page could not be scanned. Why? =

The scan frame must be allowed to load your own pages. Security headers such as `X-Frame-Options: DENY` or `Content-Security-Policy: frame-ancestors 'none'` block this. `X-Frame-Options: SAMEORIGIN` and `frame-ancestors 'self'` work. A very strict `script-src` policy can also stop the scanner from loading axe-core in the frame. Other pages in the scan continue normally.

= Will the WordPress toolbar show up in my results? =

No. Pages are scanned as the logged-in administrator, so the toolbar is present, but it is excluded from all checks.

= Which standards are covered? =

WCAG 2.0, 2.1 and 2.2 at Level A and AA (choose A or AA in Settings), plus optional axe-core best practices. Each issue shows the related WCAG success criterion.

= How is the score calculated? =

Each scanned page starts at 100 and loses points per issue: Critical 10, Major 5, Minor 2, Warning 1, never going below 0. "Needs review" items, which axe-core cannot decide on its own, are listed for you to check but cost nothing. The site score is the average of all scanned pages, so a few problem pages do not hide an otherwise good site.

= How many pages can a full-site scan cover? =

Up to 1,000 URLs by default: the home page, then published content in ID order. Developers can raise or lower the limit with the `esmoas_scan_url_limit` filter.

= Are the automatic fixes safe? =

Each fix is off by default and changes only one thing, using WordPress hooks rather than editing your theme. Turn on one fix at a time and check your site afterwards. Fixing problems in your content and theme is always the best long-term solution.

= Does it work with multisite? =

Yes. Activate it on individual sites or network-activate it. Each site keeps its own settings and results, and deleting the plugin removes the data from every site.

= I used Accessibility Guardian. What happens to my data? =

Esmo Accessibility Scanner is the same plugin under a new name. When you activate it, it copies the scans, issues, score history and settings of Accessibility Guardian 1.2 and earlier, and deactivates the old plugin so the automatic fixes are not applied twice. Delete Accessibility Guardian afterwards; that also removes its old copy of the data. Code that used the old `accg_` hooks keeps working, with a deprecation notice when debugging is on.

= Where is the axe-core source code? =

The unminified axe-core 4.10.2 source is included as `assets/js/axe.js`. The project is maintained at https://github.com/dequelabs/axe-core (tag v4.10.2).

== Screenshots ==

1. Dashboard: site score, severity breakdown, WCAG categories and progress over time.
2. A completed full-site scan with the score summary and per-page log.
3. The Issues page: every finding with its WCAG reference, element, HTML and recommended fix.
4. Settings: content to scan, WCAG level, best-practice checks and optional automatic fixes.

== Changelog ==

= 1.3.1 =
* Fixed: pages are scanned at rest. The scanner waits briefly after a page loads (filter `esmoas_scan_settle_delay`, 500 ms by default) and finishes running transitions and animations, so an element that is still fading in, such as a cookie bar, is no longer reported for low contrast.
* Fixed: the WordPress toolbar is removed from the scanned copy of the page, so a correct skip link is no longer reported as content outside landmarks on every page.
* Fixed: links that open a new window are accepted when they warn in the site's language. Visually hidden text, the title and aria-describedby count too. Built-in phrases cover English, Bulgarian, Russian, German, French, Spanish, Italian, Portuguese, Dutch, Polish and Romanian; filter `esmoas_new_window_phrases` adds more.
* Changed: "Needs review" results (axe-core could not decide, for example contrast over a background image) are a separate category. They are still listed, but no longer lower the score.
* Fixed: the "Most common problems" table shows translated severity names.

= 1.3.0 =
* Renamed from "Accessibility Guardian" to "Esmo Accessibility Scanner" for the WordPress.org directory. The slug and text domain are now `esmo-accessibility-scanner`.
* New: activating the plugin imports the scans, issues, score history and settings of Accessibility Guardian 1.2 and deactivates it. If the old plugin is still active, only its automatic fixes run, so nothing is applied twice. On multisite, network-activate it when the old plugin is network-active.
* New: developer hooks use the `esmoas_` prefix. The `accg_` names still work but are deprecated.
* New: `esmoas_settings_capability` filter. Settings, including the automatic fixes, stay limited to administrators even when the reports are opened to other roles.
* Security: scan results sent by the browser are checked on the server. The page must be one the scan queued on your site, each page can be saved only once, and the number of rules, elements and characters is limited.
* Security: single-page scans only accept content with a front-end view that the current user can read.
* Security: exports send `X-Content-Type-Options: nosniff`, the scan frame can no longer submit forms, and only the bundled axe-core is used.
* Fixed: finishing the same scan twice no longer adds two history entries.
* Fixed: a long scan is marked as failed only after 15 minutes without progress, instead of one hour after it started.
* Fixed: an emoji cut in half in an HTML snippet could lose all results of that page.
* Fixed: page titles with quotes or ampersands show correctly in the scan log.
* Fixed: on multisite, the correct site's tables are used after switching sites.
* Fixed: long HTML snippets on the Issues page wrap instead of scrolling sideways, so they can be read without a mouse.

= 1.2.0 =
* Tested with WordPress 7.1 and PHP 8.0 to 8.5.
* New: optional best-practice checks for headings, landmarks and page structure (on by default, reported as warnings).
* New: "Scan accessibility" toolbar link for a one-click single-page scan.
* New: full multisite support, including network activation, new sites and site deletion.
* New: developer hooks `accg_scan_urls`, `accg_axe_options`, `accg_required_capability` and `accg_scan_completed` (renamed to `esmoas_` in 1.3.0).
* New: the dashboard chart shows how your score changes across scans.
* Improved: the WordPress toolbar is no longer included in scan results.
* Improved: issue messages and fix suggestions keep element names such as `<ul>` and `<main>`.
* Improved: HTML snippets are kept for form fields, SVGs and frames.
* Improved: rule details for lists, touch target size, media captions, keyboard access and more.
* Improved: automatic fixes work with block themes (no duplicate skip link), use `:focus-visible`, and also apply to content loaded later.
* Improved: the plugin's own admin screens pass axe-core WCAG 2.2 AA checks.
* Fixed: cancelling a scan no longer saves it as the latest complete report.
* Fixed: CSV export deprecation on PHP 8.4 and later; exported cells cannot be interpreted as spreadsheet formulas.
* Fixed: pages that the browser refuses to show in a frame are reported as failed instead of scanning the browser's error page.
* Removed: an unused status endpoint and the unused "batch size" setting.

= 1.1.1 =
* Fixed: site score for multi-page scans is the average of per-page scores.

= 1.1.0 =
* First public release (GitHub).

== Upgrade Notice ==

= 1.3.1 =
Fewer false positives: pages are scanned once animations have finished, new-window warnings are recognised in more languages, and "needs review" items no longer lower the score. Run a new scan after updating.

= 1.3.0 =
Accessibility Guardian is now Esmo Accessibility Scanner. Activate it to import your scans and settings, then delete the old plugin. Also adds server-side validation of scan results.

= 1.2.0 =
Adds best-practice checks, multisite support and a toolbar shortcut for single-page scans, and is tested with WordPress 7.1 and PHP 8.5. Run a new scan after updating.
