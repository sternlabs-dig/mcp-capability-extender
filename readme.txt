=== MCP Capability Extender ===
Tags: woocommerce, mcp, seo, analytics
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Direct commerce tools, independent SEO audits and direct Google reporting for a WordPress MCP adapter.

== Description ==

This plugin provides authenticated commerce and reporting abilities for an MCP adapter. It is not a full analytics or SEO suite.
It does not bundle an MCP transport. Keep the existing WordPress MCP adapter and server URL.
No MonsterInsights, Site Kit, Beehive, Yoast or Rank Math is needed for sales reporting or HTML SEO audits.
WooCommerce is required only for commerce operations. Google reporting requires your own authorization.

Read-only by default. Write abilities require an administrator to enable writes and the calling user to have the appropriate existing WordPress capability. Each write also requires a record-specific preview token.

Installation and service disclosures are provided below. Maintainer testing and development notes are available in the source repository.

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

== External services ==

Optional Google services are used only when an authorized user connects/disconnects an account or explicitly requests the corresponding operation. OAuth refresh may occur during a requested operation. Product editing, sales reporting and public-page HTML audits do not require Google.

Google OAuth uses accounts.google.com and oauth2.googleapis.com for authorization and token exchange. Google Analytics requests use analyticsadmin.googleapis.com and analyticsdata.googleapis.com, sending selected property IDs, dates, dimensions, metrics and authorization. Search Console uses www.googleapis.com and searchconsole.googleapis.com with selected property/date/query dimensions or inspection URLs. PageSpeed Insights and CrUX use www.googleapis.com/pagespeedonline and chromeuxreport.googleapis.com, sending the selected public site URL, strategy/form factor and API key. Requested results are returned to the authorized AI client.

Google service documentation: https://developers.google.com/identity/protocols/oauth2 , https://developers.google.com/analytics/devguides/reporting/data/v1 , https://developers.google.com/webmaster-tools , https://developers.google.com/speed/docs/insights/v5/get-started and https://developer.chrome.com/docs/crux/api/
Google terms: https://developers.google.com/terms and https://marketingplatform.google.com/about/analytics/terms/us/
Google privacy: https://policies.google.com/privacy
Google user-data policy: https://developers.google.com/terms/api-services-user-data-policy

The site's separate MCP adapter returns requested site data to whichever AI client/provider the site owner authorizes. The owner must review that provider's own terms/privacy policy. No AI provider is automatically selected or connected by this extension.

An explicit maintenance refresh uses WordPress core's update API. Only WordPress.org packages may be installed through this extension; third-party updates remain available through their provider's own screen. WordPress privacy: https://wordpress.org/about/privacy/ and terms: https://wordpress.org/about/terms-of-service/

See external-services.txt for destinations, conditions, information sent and local retention. WordPress Settings > Privacy includes suggested text for adaptation by the site owner.

== Frequently Asked Questions ==

= Does it track visitors? =
No. Existing analytics tracking has its own collection and consent requirements.

= Does installing it give public users access to my products? =
No. Abilities enforce WordPress authentication and existing user permissions. Write tools also require the write setting and matching preview tokens.

= Can GitHub releases update my site yet? =
No. WordPress.org approval and release deployment are not configured. There is no external self-updater.

== Changelog ==

= 1.1 =
Version updated to 1.1. Functionality is unchanged from 0.4.0.

= 0.4.0 =
Added translatable dashboard/tool/error messages, privacy-policy guidance and detailed external-service disclosures. Restricted maintenance downloads to WordPress.org packages. Hardened scalar form inputs, kept settings/ability names and the existing dashboard workflow, and added directory-check tooling. Directory review and approval remain pending.


= 0.3.0 =
Modern responsive dashboard with local Stern Labs branding, real configuration states, activity table and collapsed diagnostics. Existing settings, connection and ability names preserved. Execution permission rechecks, bounded inputs, per-user request limits, atomic single-use preview claims, bulk permission rechecks, redacted errors and summaries, product-ID audit scope and hourly cleanup.

= 0.2.0 =
Renamed to MCP Capability Extender. Combined product descriptions and Yoast/Rank Math content metadata in one bulk preview/save pair. Added a copy-only tool that rejects main-title, price and inventory changes, server-side saved-field verification, one-hour recoverable batch receipts, all-status/category-ID lookups and compact product responses. Existing settings, Google authorization, plugin paths and sternlabs/ tool names are retained.

= 0.1.0 =
First staging beta. Product/variation/category/attribute operations, sales and inventory reports, independent HTML SEO auditing, direct Google reporting and controlled plugin updates.


== Upgrade Notice ==

= 1.1 =
Version-only update. Existing settings, plugin folder and MCP ability names are preserved.

= 0.4.0 =
Existing settings and MCP ability names are preserved. Third-party update packages are now blocked by the maintenance tool; use those plugins own update screens.
