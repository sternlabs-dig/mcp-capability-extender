# MCP Capability Extender 0.4.0

WordPress.org preparation release, preserving the dashboard, settings and MCP ability names.

Changes: translatable UI/tool/error messages and escaped output; translation template; Google/MCP services, privacy and retention disclosures; site-owner privacy-policy guidance; asset licence notice; nonce/capability-protected scalar settings validation; safe Google authorization redirect; production-only deterministic ZIP; official Plugin Check in GitHub checks.

Maintenance downloads are restricted to HTTPS WordPress.org packages, including a check immediately before download. Other providers may still appear in the update list; use their normal update route.

Validation: official Plugin Check 2.2.0 has no errors or warnings; 280 functional fixture assertions and six packaging tests passed; real disposable WordPress 7.1.4 / PHP 8.2.29 / WooCommerce 11.3.0 smoke test passed. See TESTING.md for limitations.

Install mcp-capability-extender-0.4.0.zip through WordPress Plugins > Add New > Upload Plugin and replace the existing plugin. Do not delete the existing plugin before replacing it. Test on staging first.

The GitHub source ZIP is for repository root, preserving subfolders, and is not a WordPress installer. It includes the updated .github/workflows/checks.yml. If browser upload hides it, edit that existing workflow in GitHub's file editor and paste its updated contents.

The connected GitHub integration denied writes, so these changes are prepared locally and have not been committed or published. WordPress.org submission/approval and automatic publishing remain pending. No production website changes were made.
