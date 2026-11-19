# MCP Capability Extender 0.3.0

Install by uploading this ZIP and choosing WordPress's replacement option for the existing plugin. Keep the existing `sternlabs-commerce-seo` directory, entry file, MCP server URL and ability names. No database migration or credential reset is required. Current write permission and Google settings are retained. Reconnect the AI client if its tool cache needs refreshing. This release is not automatically installed by creating the ZIP.

## Dashboard

Local Stern Labs logo, pale lavender gradient, responsive glass-style cards, readable configuration states, expandable technical diagnostics, section navigation, recent extension activity and separate permission/reporting forms. No remote fonts, scripts or analytics are loaded by the dashboard. Configuration is distinguished from verified provider access. The dashboard does not claim that an external MCP client is connected merely because WordPress is available.

## Background security controls

- Recheck authentication and capabilities in each ability execution callback; WordPress schema validation is repeated at execution.
- Recheck registered write capabilities at mutation time and product/SEO permissions immediately before each bulk save.
- Consume previews using an atomic persistent unique claim in addition to the existing user-bound, payload-bound, expiring preview. Claims remain until expiry to prevent concurrent reuse.
- Bound nesting, request complexity, field sizes (256 KiB) and total encoded requests (4 MiB). Normal batches remain capped at 20 products per tool call.
- Per-user, per-minute limits: 60 write-tool requests (including previews), 180 read-tool requests. A normal 100-product batch needs five previews and five applies. Excess requests return a retryable error; no deliberate delay is added to ordinary requests. Existing resource locks remain in use.
- Generic unexpected execution/save errors; redacted sensitive audit fields and authorization strings. Provider errors expose HTTP status and troubleshooting guidance rather than raw response messages.
- Approved HTTPS Google destinations reject URL credentials, nonstandard ports and fragments. Token responses are bounded; SEO crawling rejects encoded protected routes and dot-segment/backslash paths, including redirect destinations through the existing safe-request checks.
- Audit summaries include product IDs and counts without retaining product copy or credentials. Last 100 extension entries retained; not a tamper-proof or comprehensive WordPress activity log.
- Hourly bounded cleanup of plugin-owned expired previews/results/reports and security claims. WordPress cron must run. Transient TTLs also apply with an external object cache. Deactivation removes the cleanup schedule; uninstall removes local plugin-owned security state.

## Validation and timing

Existing and new isolated PHP fixtures passed 268 assertions, including copy replacement, Yoast/Rank Math compatibility, protected field preservation, partial failures/receipt recovery, stale previews, permission denials, atomic claim reuse rejection, input boundaries, redaction, request limits and separate-form settings retention. Tests use namespace-local in-memory persistence and do not edit live products or settings. Dashboard PHP was rendered through a fixture harness and inspected with local assets. This is not an independent security audit or a full end-to-end installed-site test.

Five isolated 100-product runs, each with five preview/apply pairs: v0.2.0 median 63.90 ms; v0.3.0 median 70.22 ms; difference about 6.32 ms. The runs verify all saves against in-memory persistence. They exclude real database, HTTP, hosting load and content generation costs, and are not a prediction or guarantee of live-site timing.

Existing authorized access still permits intentional supported changes. These controls do not restrict other MCP tools, WordPress APIs or stolen administrator credentials. Bulk updates remain sequential with no automatic rollback. Maintain tested backups.
