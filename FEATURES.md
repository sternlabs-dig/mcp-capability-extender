# Release coverage

Status below distinguishes implementation from verification. Implemented does not mean production-certified. Full end-to-end WordPress installation and private provider authorization remain untested.

| Area | Included in 0.2.0 | Limits / remaining work |
|---|---|---|
| Product lookup | Known category ID or slug; all/subset statuses in one paginated query; optional compact fields | Parent products, separate variation endpoint; trash excluded; per-product permissions apply |
| Categories | List hierarchy, create category, append/remove/replace assignments | No taxonomy deletion/rename or historical categories |
| Inventory | Parent and variation SKU, stock settings, backorders, quantities | No Zoho or Square synchronization |
| Prices | Simple/external and individual variation prices, sales dates | Parent variable/grouped prices are protected |
| Attributes | Global listing, terms, create attribute/options, assign custom/global values, defaults and visibility | Removing variation options/disabling Used for variations on an existing variable attribute is blocked |
| Variations | List, update or create explicit combinations | No deletion or automatic all-combinations generator |
| Bulk edits | Up to 20 products with descriptions and SEO fields in one preview/save pair; copy-only tool protects main titles and commercial fields; fresh read-back verification and compact hashes | Sequential, not transactional; partial failures are explicit; no automatic rollback |
| Lost-response recovery | User-bound per-item receipts, updated during each batch; query with original preview token | One-hour retention; pending/writing items need reconciliation; receipts do not reapply changes |
| Sales reports | Products, variations, categories, coupons, refunds, repeat-within-period counts | Date/refund/attribution definitions in report; no profit or lifetime customer classification |
| Inventory reports | Low/out-of-stock indicators, missing SKU and price flags | No historical inventory ledger or stock-cover forecast |
| Traffic | Direct GA4 overview, pages, landing pages, channels, devices, countries, products and events | Requires existing tracking and Google authorization; no native tracking or user-level funnel |
| Search | Direct Search Console performance, sitemaps and indexed URL inspection | API limits; no full Search Console UI replication |
| On-page SEO | HTML metadata, H1s, canonicals, robots directives, image/link review, JSON-LD syntax, content candidates | No semantic search-intent scoring, full heading outline, schema eligibility or product-offer reconciliation |
| Technical SEO | Bounded crawling, redirect traces, HTTP status, mixed content, sitemap entry points, duplicates and limited orphan candidates | No browser rendering, full robots evaluation, log-file analysis or exhaustive faceted-navigation audit |
| Performance | Direct Lighthouse/PageSpeed and CrUX URL queries | Needs provider access; no automatic timeline or origin fallback |
| Yoast/Rank Math | Read/edit supported saved post metadata; Yoast indexable refresh | No full old extension parity, global settings bridge, redirection management, premium modules or score reproduction |
| Native SEO output | Independent audits work with no SEO plugin | No frontend title/meta/schema/sitemap writer in this release |
| Off-page SEO | Not included | Needs provider integration or a future import workflow; no fabricated backlinks/authority metrics |
| Plugin maintenance | Installed/update listing and preview-protected update | YITH excluded; no backups or rollback |
| Reports/export | Structured MCP JSON with paginated details | AI client can make files; no native Excel/PDF dashboard/export engine |
| Automation | Resumable jobs driven by client calls | No scheduled crawling, delivery or alert system |
| Multiple websites | Separate installation and connection per site | No centralized multi-site dashboard or network-wide cleanup |

## Permissions

| Tool group | Required capability |
|---|---|
| Commerce lookup and editing | `manage_woocommerce`, plus `edit_post` on target products; batches containing SEO also require `edit_others_posts` |
| Category/attribute creation | `manage_product_terms` |
| Sales reports | `view_woocommerce_reports` |
| Crawling, Google reporting, settings and change log | `manage_options` |
| SEO post metadata | `edit_others_posts`, plus `edit_post` for target |
| Image alt editing | `upload_files`, plus `edit_post` for attachment |
| Plugin listing/updates | `update_plugins`; multisite writes also require super administrator |

All mutation abilities also require the global write switch and a fresh user-bound preview token. No arbitrary PHP execution, SQL, unrestricted file read/write, account management or filesystem deletion is exposed.

## Next implementation priorities

1. End-to-end staging validation against WooCommerce, Yoast and Rank Math versions actually deployed.
2. Original SternLabs source reconciliation and any missing settings/SEO functionality.
3. Native SEO output and optional consent-aware GA4 instrumentation as separate opt-in modules.
4. Browser-rendered crawling, deeper schema/technical checks and authenticated report exports.
5. Backlink provider integration, saved trend history, scheduled reports and alerts.

These are not marked complete or hidden behind nonfunctional placeholder tools.
