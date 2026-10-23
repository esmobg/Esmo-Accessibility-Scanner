# Security policy

## Supported versions

Security fixes are released for the latest version of Esmo Accessibility Scanner. Please update before reporting an issue.

| Version | Supported |
| --- | --- |
| 1.3.x (Esmo Accessibility Scanner) | Yes |
| 1.2.x and earlier (Accessibility Guardian) | No: please update to 1.3 |

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report it privately through GitHub instead: [**Report a vulnerability**](https://github.com/esmobg/Esmo-Accessibility-Scanner/security/advisories/new) (Security tab → Advisories → Report a vulnerability).

Please include:

- the plugin, WordPress and PHP versions;
- the steps to reproduce, or a proof of concept;
- the impact you expect, for example which user role is needed.

You can expect a first answer within a week. Confirmed issues are fixed in a new release, and the advisory is published once that release is out. Reporters are credited unless they prefer otherwise.

## Scope

The plugin runs only in wp-admin, for users with the configured capability (`manage_options` by default), and adds optional front-end fixes. It makes no external requests: axe-core is bundled.

Good candidates for a report:

- missing capability or nonce checks;
- SQL injection;
- cross-site scripting in the admin pages or exports;
- CSV formula injection;
- anything that lets a lower-privileged user read scan data or change settings.

Pages are scanned in the administrator's own browser session, so scripts that already run on your site also run during a scan. That is expected, not a plugin vulnerability.
