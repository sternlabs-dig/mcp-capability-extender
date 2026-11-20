# External services and data handling

No visitor tracking, advertising or automatic telemetry is installed. Dashboard CSS, JavaScript and branding are bundled locally. This extension never needs your main WordPress password in its source code.

## MCP adapter and AI clients

The site owner separately installs/configures an MCP adapter and authorizes a client using WordPress authentication. The client receives requested product information, sales aggregates, audit results and saved-edit verification. That client's service provider may process/store those responses under its own terms and privacy policy. This plugin does not select or automatically connect an AI provider; the site owner must disclose the provider actually used. Users and clients remain limited by the connected WordPress account's capabilities.

## Google OAuth

An administrator enters their own Google Cloud OAuth client credentials and selects Connect Google. Authorization uses `accounts.google.com` and `oauth2.googleapis.com`; Google receives the client ID, redirect URI, read-only Analytics/Search Console scopes, state and PKCE challenge. The server exchanges the returned code and may refresh tokens while processing an authorized request. Disconnect requests may send the saved refresh token to Google's revocation endpoint. Google client secrets/tokens are encrypted locally using WordPress salts and are not returned through status tools.

Service: https://developers.google.com/identity/protocols/oauth2
Terms: https://developers.google.com/terms
Privacy: https://policies.google.com/privacy
User-data policy: https://developers.google.com/terms/api-services-user-data-policy

## Google Analytics 4

After authorization and GA4 property configuration, an explicit report/property-list request contacts `analyticsadmin.googleapis.com` or `analyticsdata.googleapis.com`. Requests send the authorization token, property/account identifiers, reporting period, selected dimensions/metrics and pagination. Returned analytics aggregates are relayed to the authorized client. Existing GA4 collection is required; this plugin does not create historical traffic or install tracking tags.

Service: https://developers.google.com/analytics/devguides/reporting/data/v1
Terms: https://marketingplatform.google.com/about/analytics/terms/us/
Privacy: https://policies.google.com/privacy

## Google Search Console

Explicit property, performance, sitemap or URL-inspection requests contact `www.googleapis.com` or `searchconsole.googleapis.com`. They send authorization, the selected Search Console property, dates, dimensions, pagination and the selected URL where applicable. Returned performance/index data is relayed to the authorized client. Inspection is read-only and does not request indexing.

Service: https://developers.google.com/webmaster-tools
Terms: https://developers.google.com/terms
Privacy: https://policies.google.com/privacy

## PageSpeed Insights and Chrome UX Report

On an authorized user's explicit performance request, the selected public site URL, strategy/form factor and configured API key are sent to `www.googleapis.com/pagespeedonline/` or `chromeuxreport.googleapis.com`. Google may fetch the public page for Lighthouse analysis. No private/admin URL or application password is forwarded. Existing Google quotas, eligibility and API availability apply; an API key may be required. Results are returned to the requesting client; performance cache entries expire after one hour.

Services: https://developers.google.com/speed/docs/insights/v5/get-started and https://developer.chrome.com/docs/crux/api/
Terms: https://developers.google.com/terms
Privacy: https://policies.google.com/privacy

## WordPress updates and public-page audits

An explicit update-information refresh uses WordPress core's update API and may contact update services registered by other installed plugins. Installing an update through this extension is limited to HTTPS packages at `downloads.wordpress.org/plugin/` and requires the existing write setting, WordPress update permissions and a matching preview. It never silently installs an update. WordPress core owns its ordinary update checks independently of this extension.

Public-page audits fetch the exact site's public origin through the WordPress safe HTTP API. Audit URLs are not sent to an unrelated audit server. Reports may contain public content snippets, link URLs and image alt text.

WordPress privacy: https://wordpress.org/about/privacy/
WordPress terms: https://wordpress.org/about/terms-of-service/

## Local data retention

- Settings and encrypted Google credentials/tokens persist until changed, disconnected or removed by uninstall. Deactivation preserves them.
- Report jobs and crawls expire after 24 hours. Sales jobs use keyed pseudonymous customer hashes to count repeat customers. Hashing is not anonymization; customer contact details are not returned in report output.
- The latest 100 extension activity entries retain acting WordPress user ID, action, time, limited scope and outcome. Product descriptions and credentials are redacted from audit summaries.
- Preview tokens expire after ten minutes; used-token claims are retained temporarily to prevent replay. Bulk receipts expire after one hour; rate buckets expire after two minutes.
- Expired database/cache data may remain physically present until cleanup. The plugin schedules bounded hourly cleanup through WordPress cron. Backups and external AI providers have their own retention.
- Uninstall clears site-local plugin-owned settings, logs and temporary records; it does not delete product content or data already received by Google/the AI client. Multisite data is site-local; administrators must clean each site before network removal.

Saving reporting settings/connect authorization and explicitly requesting the corresponding service operations is the opt-in workflow. Product tools and sales reports can be used without Google. Site owners should review the suggested privacy-policy text under WordPress Settings > Privacy and adapt it to the actual site configuration.
