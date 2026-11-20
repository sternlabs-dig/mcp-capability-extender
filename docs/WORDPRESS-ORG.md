# WordPress.org submission work

Status: not submitted/approved based on the maintainer's current information. GitHub setup does not establish directory compliance.

## Completed for 0.4.0

- Official Plugin Check 2.2.0 passed with runtime checks on WordPress 7.1.4 / PHP 8.2.29.
- Added UI/tool/error translations, output escaping, translation template and privacy-policy guidance.
- Documented optional services, request data, conditions, terms and privacy links.
- Restricted maintenance downloads to WordPress.org packages; no self-updater is bundled.
- Added scalar form validation and a scoped Google authorization redirect allowlist.
- Maintainer confirmed logo ownership/distribution authority and GPL v2-or-later permission; notice ships with the image.
- Added official Plugin Check to GitHub checks, prepared locally pending source upload.
- Verified production ZIP, six packaging tests, 280 functional assertions and a real disposable WooCommerce copy update.

## Remaining before submission

- Obtain the actual WordPress.org contributor username. None has been supplied; no account name is assumed.
- Confirm proposed/approved slug and upgrade path. Existing folder/text domain remain sternlabs-commerce-seo for manual in-place updates.
- Establish a private security reporting route, complete relevant staging checks and follow reviewer feedback.

## Ongoing review checklist

- Run the official Plugin Check tool and address relevant findings. Record versions, full results and explanations for genuine false positives. PHP syntax/package checks in CI do not replace Plugin Check.
- Complete translation coverage for visible labels, notices and errors using literal text domains and suitable escaping.
- Review every request field, nonce/capability check, output context, external request and database query. Test the safeguards in a staging installation.
- Document Google Analytics, Search Console, OAuth, PageSpeed and CrUX: destinations, circumstances, data sent, account requirements, terms/privacy links and authorization/consent. Add appropriate WordPress privacy-policy guidance.
- Review the plugin-update ability: WordPress's updater may receive third-party package URLs from other plugins. Determine whether the directory edition must restrict it to WordPress.org packages. No external self-updater is bundled.
- Confirm the contributor's actual WordPress.org username rather than assuming `sternlabs` is owned.
- Confirm the approved directory slug, text domain and upgrade path from the installed `sternlabs-commerce-seo` folder before deployment. Do not rename the live folder blindly.
- Verify logo licensing/ownership and add the asset notice described in ASSETS.md.
- Establish an appropriate private security reporting contact/route.
- Validate readme formatting, stable tag and compatibility metadata. Add `Tested up to` only after testing that WordPress version. Describe optional dependencies accurately.
- Test multisite cleanup, external caches, WordPress cron, permission changes, WooCommerce variations and supported Yoast/Rank Math versions.
- Audit the release ZIP for unnecessary files and credentials. No tests, workflows, build outputs or site data should be installed.

## Official references

- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- https://developer.wordpress.org/plugins/wordpress-org/common-issues/
- https://wordpress.org/plugins/plugin-check/
- https://wordpress.org/plugins/developers/add/

Follow directory reviewers' instructions and resolve feedback before publishing. Passing automated checks does not guarantee approval.
