# WordPress.org submission work

Status: not submitted/approved based on the maintainer's current information. GitHub setup does not establish directory compliance.

## Required review before submission

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
