# Validation record and staging checks

## Repository checks

Run `python3 -m unittest discover -s tests -p 'test_*.py' -v`, `python3 scripts/build_zip.py`, and `node --check assets/dashboard.js` from the repository root. GitHub Actions also parses PHP on 8.1 and 8.3. These jobs are syntax and packaging checks, not the complete functional suite or Plugin Check.

To assemble the isolated functional fixtures, run `python3 scripts/build_regression.py`. On a disposable WordPress 6.9+ / WooCommerce test installation, load the resulting `build/regression.php` with `wp eval-file /absolute/path/to/build/regression.php`. WordPress and WooCommerce supply the APIs and classes; fixture persistence is namespace-local and in memory. A successful run prints `CE030_QA_PASSED assertions=268`; errors stop with an exception. No production credentials are required. These fixtures intentionally test both allowed and denied operations and must not be exposed as public PHP endpoints or installed with the plugin.

## Version 0.2.0 validation

- The connected PHP validator executed an isolated bundle of the updated modules and fixtures successfully. A separate intentional completion marker confirmed **246 assertions** reached completion; an initial throwing probe established that the validator executes code rather than only scans syntax.
- New checks cover combined five-field preview/save/read-back; copy-only rejection of main names, SKUs, status, prices and attributes; strict SEO content fields; duplicate IDs and 20-item caps; all-items-before-writing validation; stale SEO/title previews; role permissions; title/inventory/indexing preservation; user-bound receipts and expiry; partial failures; silently dropped content/SEO writes; explicit clears; HTML sanitisation; literal slashes; variable parent preservation; legacy inventory/SEO compatibility; and compact all-status/category-ID lookups.
- Yoast and Rank Math metadata mappings are tested with controlled provider selection and in-memory metadata. Yoast's builder is doubled to confirm one rebuild per combined save. This is not an end-to-end integration test with both real SEO plugins.
- Fixtures use a separate PHP namespace and replace product/metadata/options/transient persistence in memory. No fixture product, option, credential or order is written to the live website.
- The archive contains the stable plugin folder and entry file needed for an in-place upgrade. Version, display name, PHP source inventory and ZIP integrity are checked while packaging.
- Deployment is separate: direct server-level access was denied by the connection. The updated ZIP must be installed through normal WordPress plugin upload/replace, followed by an activation/status check and a small representative batch.

## Earlier 0.1.0 validation

- All six PHP module files passed the connected WordPress snippet validator after a syntax error in the admin page was corrected.
- A combined test bundle executed in the validator with namespace-local, in-memory WordPress state doubles. The validator was first checked with a harmless throwing probe to establish that code is executed rather than merely scanned.
- The combined bundle passed after adding real in-memory WooCommerce simple, variable, variation and grouped product objects. No object was saved.
- A multi-file bootstrap smoke test passed in the validator after flattening include paths to match its temporary-file layout. The packaged include paths are checked separately. A read-only no-match WooCommerce query also confirmed the product name-search parameter works on the connected store.
- Tests cover every registered ability having permission/execution callbacks and rejecting unauthorized access; strict nested input schemas; invalid dates; preview/no-write behavior; stale/user-bound/replayed preview rejection; read-only mode; lock contention/release; encryption roundtrip and tamper rejection; unsafe URL rejection; HTML SEO extraction; negative-price/type-conversion rejection; variable inventory/type preservation; variation prices; refund math; per-order product/category counting; and separate currencies.
- Test doubles replace persistence and do not write orders, products, options, transients or credentials on the live website. No external Google requests are made by the fixtures.
- The plugin ZIP is checked for the expected root folder, referenced source files, PHP opening tags, absence of test/secret files, and extraction integrity. SHA-256 is recorded in the release manifest.

## Not verified here

- Full plugin upload/activation, real WordPress ability discovery, and calling every endpoint through the deployed MCP adapter.
- End-to-end Google consent, refresh tokens, account/property access, report metric compatibility and API quotas.
- Real plugin upgrades, live product edits, actual refund/order edge cases or performance under a large production store.
- Full parity with the previous Angie Extended by SternLabs source. Neither the saved ZIP nor installed PHP source could be retrieved in the build environment.

Treat this as a staging beta until those checks pass. Server-side validation and mocked tests are useful evidence, not a substitute for integration tests.

## Staging acceptance checks

1. Activate with WooCommerce present and absent. Confirm SEO/status tools still fail gracefully where dependencies are missing.
2. Verify tools are discovered through the existing MCP server and reject an unauthorized WordPress role.
3. Resolve the exact Suits category and compare product counts/descendant behavior with WooCommerce. Do not compare with a text search for 'suit'.
4. Inspect a variable product, including disabled/private variations. Compare parent and variation SKUs and stock inheritance.
5. Preview a price edit, alter the record in WooCommerce, then confirm the old preview is rejected. Generate a fresh preview before applying.
6. On a disposable test product, edit a parent SKU, variation price, Manage stock, stock quantity, category assignment and attribute options; verify frontend/checkout behavior and unchanged product type.
7. Compare a completed historical sales period against manually checked orders using the documented status, date, refund and currency rules. Include a partial refund, full refund, unallocated refund, coupon and multi-category product.
8. Authorize Google, verify property IDs, compare reports with GA4/Search Console using identical dimensions and date/timezone definitions. Test revoked/expired authorization and quota errors.
9. Audit fixtures with noindex, missing alt text, broken URLs, redirects, malformed JSON-LD and intentional canonicals. Verify crawl limits and pagination.
10. Run PageSpeed mobile/desktop and CrUX on representative pages. Treat missing field data as unavailable, not poor.
11. Test a nonessential plugin update on staging with a verified backup. Confirm YITH remains excluded.
12. Verify deactivation preserves settings and uninstall only clears extension-owned site-local data.
