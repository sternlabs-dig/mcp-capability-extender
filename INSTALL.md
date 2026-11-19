# Installation and setup

## What this release is

MCP Capability Extender 0.2.0 updates SternLabs Commerce & SEO MCP 0.1.0 with combined copy/SEO batches, verified saved results and faster catalog lookups. It remains separate from `Angie Extended by SternLabs`; it does not claim full parity with that different plugin.

The 0.2.0 upgrade has been tested with isolated WordPress/WooCommerce doubles, not installed or activated on David Wej during this build. No live products, orders or Google settings were changed while developing this update.

## Upgrade from 0.1.0

1. Open **Plugins > Add New > Upload Plugin** and select `mcp-capability-extender-0.2.0.zip`.
2. Choose **Replace current with uploaded** when WordPress recognises the existing plugin. Do not delete the old plugin first: uninstalling removes its settings and Google tokens.
3. The visible name becomes **MCP Capability Extender**. The ZIP intentionally keeps the existing `sternlabs-commerce-seo/` directory and entry file for an in-place upgrade.
4. Saved settings, write permissions and encrypted Google credentials retain their existing option keys. The MCP adapter URL and the `sternlabs/` tool names stay unchanged. A new WordPress application password is not needed just for this rename.
5. Reload/reconnect the AI client's tool list. Check `sternlabs/status` for version **0.2.0** and the new bulk capabilities.
6. Preview a small copy batch, review it, then save it. Look for `all_verified:true`. Check a representative frontend page after updates.

The settings page is now **Tools > MCP Capability Extender**. Its internal URL and Google OAuth callback remain compatible with 0.1.0.

## Requirements

- WordPress 6.9+ with the Abilities API.
- An active MCP adapter exposing public WordPress abilities, such as the existing adapter used by Angie.
- PHP 8.1+, OpenSSL for encrypted credentials, and DOM/libxml for HTML audits.
- WooCommerce for product, stock and sales features. Commerce tools fail clearly if it is absent.
- Outbound HTTPS for crawling, Google reporting and plugin update downloads.

## Install

1. Upload the ZIP through **Plugins > Add New > Upload Plugin** on a staging copy.
2. Activate **MCP Capability Extender**.
3. Open **Tools > MCP Capability Extender**. Keep writes disabled initially.
4. Reconnect the AI client or start a new chat. The server URL stays unchanged:
   `https://ng.davidwej.com/wp-json/mcp/mcp-adapter-default-server`
5. Ask the client to discover abilities and run `sternlabs/status`.
6. Verify category counts, one variable product, and a small completed sales period against WooCommerce.
7. Enable writes only when ready to test edits. The user must still have the specific WordPress capability and product/post permissions.

Do not paste application passwords or Google secrets into product fields or report requests. The package contains no production credentials.

## Google authorization without Site Kit

1. Create a Google Cloud project and OAuth web application.
2. Configure the consent screen and permitted users for your app status.
3. Enable **Google Analytics Data API**, **Google Analytics Admin API**, and **Google Search Console API**.
4. Add the exact redirect URI displayed in Tools > MCP Capability Extender to the Google OAuth client. For the usual WordPress installation, this is:
   `https://ng.davidwej.com/wp-admin/admin-post.php?action=sci_google_callback`
5. Enter the OAuth client ID and secret in the WordPress settings page. Save.
6. Click **Connect Google** and authorize an account that can access the intended properties.
7. Discover properties using `sternlabs/google-properties`.
8. Save the numeric GA4 property ID, not the `G-...` measurement ID. Save the exact Search Console property identifier, including its prefix/trailing slash or `sc-domain:` form.
9. Run one GA4 report and one Search Console report to verify access.

Read-only OAuth scopes:

```
https://www.googleapis.com/auth/analytics.readonly
https://www.googleapis.com/auth/webmasters.readonly
```

Google OAuth app verification, testing restrictions, API enablement, quotas and billing are controlled by your Google project. Testing-mode grants can expire. Changing the OAuth client or WordPress salts requires reconnecting. This beta does not copy credentials from Site Kit or other plugins.

## Tracking requirement

GA4 reporting reads already collected data. The beta does not install a GA4 tag, consent system or WooCommerce ecommerce events. A site without tracking needs that instrumentation configured separately. It cannot recover historical visits or events that were never recorded. Existing traffic plugins can remain active if they provide tracking, but are not used as the reporting data source.

## Speed data

Enable PageSpeed Insights API and Chrome UX Report API. Add a Google API key to the settings page. Restrict the key to the required APIs and appropriate server restrictions. PageSpeed may work without a key within provider limits; CrUX requires a key in this implementation. PageSpeed results are cached for one hour. CrUX can legitimately return no eligible URL data.

## Example requests

### Faster copy workflow

Reuse a known category ID. Fetch all editable product statuses together and request only the fields needed for generation:

```json
{"category_id":138,"status":"all","per_page":100,"page":1,"fields":["name","sku","status"]}
```

`id` is always returned. `all` means publish, draft, pending, private and future; it excludes trash. Alternatively provide `statuses:["publish","draft"]`. Do not combine `status` and `statuses`, or `category` and `category_id`. Fetch every page. `total`/`total_pages` describe the underlying query; if `permissions_filtered:true`, some rows are omitted because the current user cannot edit them. This is not evidence those products do not exist.

Generate and validate the entire copy before sending it. Use **`sternlabs/product-copy-bulk-update`** for batches of up to 20:

```json
{
  "dry_run":true,
  "items":[{
    "id":12345,
    "description":"<p>New narrative copy.</p>",
    "short_description":"<p><strong>Key Features:</strong></p><ul><li>A useful feature.</li></ul>",
    "seo":{
      "focus_keyword":"product specific phrase",
      "title":"Separate SEO title",
      "description":"New meta description."
    }
  }]
}
```

The example illustrates the API structure, not a recommended copy length. Use actual resolved IDs. Omit fields you are not changing; an explicit empty string clears a field. The copy-only tool rejects `name`, SKU, status, price, stock, category and attribute fields. SEO accepts only `title`, `description` and `focus_keyword`; canonical/indexing changes remain separate operations. SEO fields require active Yoast or Rank Math and the caller's SEO permissions. Content-only batches work without an SEO plugin.

Review the returned normalised changes and repeat the identical request with `dry_run:false` and the returned `preview_token`. A changed product or SEO record invalidates the preview. The plugin saves product content and all provided SEO fields, rebuilds Yoast's indexable once per product, reads persisted values back, and returns `saved`, `verified`, `expected_hash` and `saved_hash` for each item. For copy-only edits the check includes preservation of the main title and commercial fields. Attribute verification compares actual IDs/options rather than cached display labels.

Use `return_saved_fields:true` in **both** preview and apply to receive the read-back values too. Otherwise receipts stay compact. The legacy `sternlabs/product-bulk-update` also accepts the nested `seo` object, alongside its existing inventory capabilities.

For 80 products, four batches take **eight preview/save requests** for all five fields, excluding discovery, pagination, retries and any frontend sample checks. This reduces round trips; actual runtime still depends on hosting, SEO hooks and batch size.

### Recovering an interrupted connection

Keep the preview token until the batch outcome is known. If the save response is lost, call **`sternlabs/product-bulk-result`**:

```json
{"preview_token":"the-original-batch-preview-token"}
```

The user-bound receipt lasts one hour and is refreshed as each item is processed. `complete:true` means the batch finished, not that every item succeeded; check `all_saved`, `all_verified` and each item. `pending` or `writing` may mean still running or interrupted. Do not blindly repeat the original save. Reconcile those IDs against current values, then generate a fresh preview only for the unfinished/failed items. The endpoint reads results and does not perform writes. Writes remain sequential rather than atomic; there is no automatic rollback.

Category lookup:

```json
{"search":"suits","per_page":100,"page":1}
```

Call `sternlabs/categories`, then `sternlabs/products` with the exact returned slug:

```json
{"category":"suits","per_page":50,"page":1}
```

Use `sternlabs/variations` with the parent ID. A variation edit through `sternlabs/product-update`:

```json
{"id":12345,"regular_price":"292500","sale_price":"","manage_stock":true,"stock_quantity":8,"dry_run":true}
```

Review the returned plan, then repeat the same fields with `dry_run:false` and the returned `preview_token`. IDs above are examples. Never apply them without resolving the actual product.

Global attribute options use existing term IDs expressed as strings:

```json
{"id":12345,"attributes":[{"id":1,"options":["22","23"],"visible":true,"variation":true}],"dry_run":true}
```

Product attribute definitions merge by name, while the options for each selected attribute are replaced. Existing variable attributes cannot have options removed or Used for variations disabled in this beta; this protects existing combinations. Turning Manage stock on/off is supported independently.

## Reports

1. Call `sternlabs/sales-report-start` with `start_date` and `end_date`.
2. Call `sternlabs/sales-report-next` using the returned `report_id` until `complete:true`.
3. Call `sternlabs/sales-report-get` for the summary and `sternlabs/sales-report-rows` for all paginated details.

Read the definitions in every report. Sales are an order-creation-date cohort, not an accounting cash-receipts statement. Lifetime refunds of those orders are included at processing time. Currency groups stay separate. Category attribution uses current assignments and duplicates revenue across directly assigned categories; category totals must not be added together.

For comparisons, create two reports with equivalent definitions. Net merchandise revenue excludes tax/shipping and subtracts line-level refunds; order totals include shipping/tax and all order-level refunds. Unallocated refunds cannot reliably be attributed to individual products.

## SEO audit

Use `sternlabs/seo-page-audit` for one public URL, or start a bounded site job using `sternlabs/seo-crawl-start` and continue with `sternlabs/seo-crawl-next`. Read paginated results with `sternlabs/seo-crawl-get`.

The crawler inspects server-delivered HTML without browser rendering. It does not bypass bot protection. Query-string URLs, offsite redirects and authenticated endpoints are excluded. Audit findings show evidence and review advice; they are not Google ranking guarantees. Full robots rule evaluation and rich-result eligibility validation are not implemented.

## Maintenance and removal

Plugin update tools use WordPress update feeds and filesystem permissions. YITH plugin files are excluded. No database backup or automatic rollback is created. Check premium licensing and staging compatibility before applying updates.

Deactivation keeps settings. Uninstall clears site-local extension options and database transients; external object-cache entries may remain until their normal TTL. Network-wide cleanup is not implemented: remove per-site data before deleting a network installation. Revoke the Google app grant in Google Account settings if removing the plugin without disconnecting first.
