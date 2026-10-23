# Submitting Esmo Accessibility Scanner 1.3.0 to WordPress.org

This is the step-by-step path from this repository to a live listing at `https://wordpress.org/plugins/esmo-accessibility-scanner/`. The steps that need the **esmobg** WordPress.org account must be done by the account owner.

## History

| Date | Event |
| --- | --- |
| 2026-10 | 1.2.0 submitted as **Accessibility Guardian** (`accessibility-guardian`). |
| 2026-10 | Review paused: the name was too close to "Accessibility Guard" and "Garsen Accessibility Guard". The Plugins Team asked for a name that starts with a distinctive term, the new name in the readme and plugin header, an updated text domain, a new upload, and a reply with the exact slug. |
| 2026-10-23 | 1.3.0: renamed to **Esmo Accessibility Scanner**, slug and text domain `esmo-accessibility-scanner`. |

## 0. Pre-flight (done for 1.3.0)

| Check | Result |
| --- | --- |
| Name and header | `Plugin Name: Esmo Accessibility Scanner`, `Text Domain: esmo-accessibility-scanner`, readme title `=== Esmo Accessibility Scanner ===` |
| Text domain | Every translation call uses `esmo-accessibility-scanner`; `languages/esmo-accessibility-scanner.pot` regenerated with WP-CLI |
| Slug `esmo-accessibility-scanner` | Free (`plugins_api` returns "Plugin not found") |
| Upgrade from 1.2.0 on WP 7.1.3 + PHP 8.5 | Scans, issues, history and settings imported; old plugin deactivated; deleting the old plugin keeps the new data |
| Security regression test (direct AJAX requests) | Foreign and `javascript:` URLs rejected; saves capped per page and per scan; double finish adds one history entry; exports send `nosniff` |
| debug.log during all of the above | No entries from the plugin |
| Plugin Check, all categories, errors + warnings | Runs in CI on the built zip (see the PR checks) |
| `readme.txt` | Short description under 150 characters, 5 tags, `Stable tag` = `Version` = 1.3.0, `Tested up to: 7.1` |

Re-run these after any code change. CI runs PHP lint (8.0–8.5), PHPUnit and Plugin Check on every push.

## 1. Get the zip

Use the release zip, never GitHub's "Download ZIP":

- From the GitHub release: `https://github.com/esmobg/Esmo-Accessibility-Scanner/releases/tag/v1.3.0` → `esmo-accessibility-scanner-1.3.0.zip` (a `.sha256` checksum and a build provenance attestation are published with it), or
- build it locally: `python bin/build-zip.py` → `dist/esmo-accessibility-scanner-1.3.0.zip`.

The zip contains only the plugin (46 files, about 460 KB) inside an `esmo-accessibility-scanner/` folder. Tests, docs, tooling, screenshots and banners are excluded by `.distignore`.

## 2. Answer the review (account owner)

1. Sign in at https://wordpress.org/plugins/developers/add/ as **esmobg**.
2. Upload `esmo-accessibility-scanner-1.3.0.zip` as the new version of the pending submission, using the upload form for the plugin in review.
3. Reply in the same email thread from the account's address:

```text
Hello,

Thank you for the review. I have renamed the plugin as requested:

- New name: Esmo Accessibility Scanner ("Esmo" is my brand and username, esmobg)
- Requested slug: esmo-accessibility-scanner
- Plugin header and readme now use "Esmo Accessibility Scanner"
- Text domain changed to esmo-accessibility-scanner in every translation call and in the .pot file
- All prefixes changed to esmoas_ / ESMOAS_ / EsmoAccessibilityScanner\

I have uploaded the new version (1.3.0) as esmo-accessibility-scanner-1.3.0.zip.

Please use the slug: esmo-accessibility-scanner

Best regards,
esmobg
```

### If the reviewer asks questions

| Topic | Answer |
| --- | --- |
| Bundled library | axe-core 4.10.2 by Deque, MPL-2.0 (GPL-compatible). Minified `assets/js/axe.min.js` plus unminified source `assets/js/axe.js`. Declared in readme under "Third-party library". |
| External services / remote calls | None. No `wp_remote_*`, no CDN, no tracking. Documentation links are plain links opened only on click. |
| Data stored | Three prefixed tables (`{prefix}esmoas_*`) and two options (`esmoas_settings`, `esmoas_db_version`); all removed in `uninstall.php`, on every site of a network, together with any leftovers from Accessibility Guardian (`accg_*`). |
| Prefixing | PHP namespace `EsmoAccessibilityScanner\`, constants `ESMOAS_*`, options/tables/hooks/AJAX/nonces `esmoas_*`, handles, CSS classes and ids `esmoas-*`, JS globals `esmoas*`. The only `accg_` names are the deprecated hook aliases and the one-time import from 1.2.0. |
| Deactivating another plugin | On activation the plugin deactivates only `accessibility-guardian/accessibility-guardian.php`, its own previous version (same author, same code under the old name), so that the automatic fixes are not applied twice. |
| Security | Every AJAX/admin-post handler checks the capability and a nonce. Input is unslashed, sanitized and validated (scan results are capped and their URL must belong to the site); output is escaped; SQL uses `$wpdb->prepare()`. |
| Front-end impact | Nothing loads on the front end unless the site owner enables an automatic fix. |
| Claims | The readme says clearly that automated testing does not certify compliance. |

If the reviewer requests changes: fix them in this repo, bump to 1.3.1 if code changed, rebuild the zip and reply with it in the same email thread.

## 3. After approval: publish with SVN (account owner)

You receive the SVN URL `https://plugins.svn.wordpress.org/esmo-accessibility-scanner/`. Use your WordPress.org username and an **SVN password** (set it in your WordPress.org profile under "Account & Security").

```bash
svn checkout https://plugins.svn.wordpress.org/esmo-accessibility-scanner esmoas-svn
cd esmoas-svn

# Plugin code: the contents of the zip's esmo-accessibility-scanner/ folder go into trunk/.
unzip ../esmo-accessibility-scanner-1.3.0.zip -d /tmp/esmoas
cp -R /tmp/esmoas/esmo-accessibility-scanner/. trunk/

# Listing assets: icons, banners, screenshots and the Live Preview blueprint.
cp ../Esmo-Accessibility-Scanner/wp-org-assets/icon.svg \
   ../Esmo-Accessibility-Scanner/wp-org-assets/icon-128x128.png \
   ../Esmo-Accessibility-Scanner/wp-org-assets/icon-256x256.png \
   ../Esmo-Accessibility-Scanner/wp-org-assets/banner-772x250.png \
   ../Esmo-Accessibility-Scanner/wp-org-assets/banner-1544x500.png \
   ../Esmo-Accessibility-Scanner/wp-org-assets/screenshot-*.png assets/
mkdir -p assets/blueprints
cp ../Esmo-Accessibility-Scanner/wp-org-assets/blueprints/blueprint.json assets/blueprints/

# Tag the release (Stable tag in readme.txt points to it).
svn add --force trunk assets
svn copy trunk tags/1.3.0

svn status            # review
svn commit -m "Release 1.3.0"
```

Notes:

- SVN `assets/` is for the listing only and never ships in the plugin zip.
- The listing shows `screenshot-N.png` with the captions from the `== Screenshots ==` section of `readme.txt` (1 = Dashboard, 2 = completed scan, 3 = Issues, 4 = Settings).
- `assets/blueprints/blueprint.json` adds a **Live Preview** button. It installs the plugin from WordPress.org, creates a small demo site with typical problems and opens the Run Scan page. It only works once the plugin is published.
- The listing updates within a few minutes; the download zip within about 15 minutes.

## 4. Every later release

1. Update the version (plugin header, `ESMOAS_VERSION`, `Stable tag`), `readme.txt` changelog and `CHANGELOG.md`.
2. Push to `main`, wait for CI, then push a tag `vX.Y.Z` (the release workflow builds the zip and creates the GitHub release).
3. In SVN: copy the new files into `trunk/`, `svn copy trunk tags/X.Y.Z`, commit.
4. When a new WordPress version is released, test it and bump `Tested up to` (a readme-only change can be committed to `trunk/` and the current tag without a new version).
