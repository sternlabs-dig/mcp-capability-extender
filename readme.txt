=== MCP Capability Extender ===
Contributors: sternlabs
Tags: woocommerce, mcp, seo, analytics
Requires at least: 6.9
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Direct commerce tools, independent SEO audits and direct Google reporting for a WordPress MCP adapter.

== Description ==

This is an installable staging beta, not the finished scope of a full analytics/SEO suite.
It does not bundle an MCP transport. Keep the existing WordPress MCP adapter and server URL.
No MonsterInsights, Site Kit, Beehive, Yoast or Rank Math is needed for sales reporting or HTML SEO audits.
WooCommerce is required only for commerce operations. Google reporting requires your own authorization.

Read-only by default. Write abilities require an administrator to enable writes and the calling user to have the appropriate existing WordPress capability. Each write also requires a record-specific preview token.

Read INSTALL.md, FEATURES.md and TESTING.md before use.

== Installation ==

1. On staging, upload this ZIP using Plugins > Add New > Upload Plugin.
2. Activate MCP Capability Extender.
3. Open Tools > MCP Capability Extender. Leave writes disabled during initial verification.
4. Reload the AI client tools and discover sternlabs/status.
5. Configure Google only if traffic, search or performance reports are needed.

== Privacy ==

No visitor tracking is installed. Nothing is sent to Google until an authorized user requests a Google operation or connects an account. PageSpeed and CrUX requests send the selected public URL. Google OAuth requests use read-only Analytics and Search Console scopes.
Google credentials are encrypted at rest using keys derived from WordPress salts. Local report jobs include hashed customer identity solely to count repeat customers within a period; no customer contact details are returned. Reports expire after 24 hours. Hashing does not make stored records anonymous.
The AI client receives requested reporting data, product data and audit results through MCP. Control access with WordPress user capabilities and application passwords.

== Changelog ==

= 0.3.0 =
Modern responsive dashboard with local Stern Labs branding, real configuration states, activity table and collapsed diagnostics. Existing settings, connection and ability names preserved. Execution permission rechecks, bounded inputs, per-user request limits, atomic single-use preview claims, bulk permission rechecks, redacted errors and summaries, product-ID audit scope and hourly cleanup.

= 0.2.0 =
Renamed to MCP Capability Extender. Combined product descriptions and Yoast/Rank Math content metadata in one bulk preview/save pair. Added a copy-only tool that rejects main-title, price and inventory changes, server-side saved-field verification, one-hour recoverable batch receipts, all-status/category-ID lookups and compact product responses. Existing settings, Google authorization, plugin paths and sternlabs/ tool names are retained.

= 0.1.0 =
First staging beta. Product/variation/category/attribute operations, sales and inventory reports, independent HTML SEO auditing, direct Google reporting and controlled plugin updates.

