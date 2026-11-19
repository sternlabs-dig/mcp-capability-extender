# MCP Capability Extender

WordPress MCP tools for WooCommerce, SEO and reporting, maintained by Stern Labs.

**Current baseline: 0.3.0. WordPress.org submission and approval are pending.** This repository is the development source, not a WordPress.org listing. No GitHub-to-WordPress.org deployment is enabled yet.

## Features

- Product, category, attribute and variation tools with existing WordPress permission checks.
- Combined description and SEO copy replacement, up to 20 products per preview/save pair.
- Saved-field verification and recoverable bulk receipts.
- WooCommerce sales and inventory reports, public-page HTML SEO audits and optional direct Google reports.
- Responsive admin dashboard with local Stern Labs branding and background security controls.

Read [FEATURES.md](FEATURES.md) and [INSTALL.md](INSTALL.md) for implemented features and limitations. This does not install an MCP transport or visitor tracking. Yoast or Rank Math is needed for supported saved SEO metadata; Google reporting requires your own account authorization and existing analytics tracking.

## Requirements

- WordPress 6.9+ and PHP 8.1+.
- An MCP adapter exposing WordPress abilities.
- WooCommerce for commerce tools only.
- PHP OpenSSL for encrypted Google credentials; DOM support for HTML auditing.

## Build and install

Run `python3 scripts/build_zip.py` from a checkout. It creates `dist/mcp-capability-extender-0.3.0.zip` using the version declared in the plugin header. Upload the resulting ZIP through WordPress's plugin uploader and use its replacement flow for an existing installation.

The ZIP preserves the existing `sternlabs-commerce-seo/` directory and `sternlabs-commerce-seo.php` entry. **Do not install GitHub's automatic source archive as an update:** its wrapper folder can differ. Existing MCP ability names and settings are deliberately retained. Do not delete the installed plugin before replacing it, as uninstall removes its data.

## Everyday development

1. Create a branch and make changes.
2. Run syntax and packaging checks; test on staging and run the regression fixtures in a WordPress/WooCommerce test environment.
3. Open a pull request. GitHub Actions checks PHP syntax on 8.1 and 8.3, JavaScript syntax and ZIP packaging. These checks do not replace installed-site or security tests.
4. For a release, increment the plugin header, `SCI_VERSION`, readme stable tag and changelog together.
5. Build and verify the ZIP before release.

## WordPress.org publishing

After approval, GitHub Actions can deploy tagged releases to the **approved** WordPress.org SVN repository. WordPress.org then distributes updates to installed sites. This repository currently has **no publishing workflow**, no deployment secrets and no GitHub update checker in the plugin. Uploading source or creating a GitHub release alone will not create WordPress dashboard updates.

See [the submission checklist](docs/WORDPRESS-ORG.md) and [release setup](docs/RELEASING.md). The approved slug, text domain and migration from existing installation paths need to be settled before the first directory release.

## Privacy and security

No visitor tracking or remote dashboard assets are installed. Requested product/report/audit data is returned to the authorized AI client. Google requests and data retention are described in [readme.txt](readme.txt). Use a dedicated least-privilege account and revocable application passwords. Never commit site configuration, database exports, real customer reports, passwords, tokens, private keys or Google credentials.

See [SECURITY.md](SECURITY.md). Previous isolated 0.3.0 regression validation passed 268 assertions; see [TESTING.md](TESTING.md). That is not independent security certification or a current WordPress.org Plugin Check result.

## Licence

Plugin code is licensed under GNU GPL version 2 or, at your option, any later version (SPDX `GPL-2.0-or-later`). [LICENSE](LICENSE) contains the unmodified GPL v2 text. Asset licensing and trademark documentation must be completed before directory submission; see [ASSETS.md](ASSETS.md).
