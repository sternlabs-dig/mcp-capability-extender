<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class Google {
    const SCOPES = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';
    public static function register() {
        Core::ability('google-properties', __('List authorized Google properties', 'sternlabs-commerce-seo'), __('Lists GA4 account/property summaries and Search Console sites using direct read-only authorization. Does not depend on Site Kit.', 'sternlabs-commerce-seo'), Core::schema(), array(__CLASS__, 'properties'), 'manage_options');
        Core::ability('ga4-report', __('Read direct GA4 analytics report', 'sternlabs-commerce-seo'), __('Uses the configured GA4 property. Presets avoid incompatible item/session metric combinations. Returns provider metadata, row count and pagination. Requires existing GA4 tracking and authorization; does not install tracking.', 'sternlabs-commerce-seo'), Core::schema(array_merge(Core::date_fields(), array(
            'report' => Core::field('string', array('enum' => array('overview', 'pages', 'landing_pages', 'channels', 'devices', 'countries', 'products', 'events'))),
            'limit' => Core::field('integer', array('minimum' => 1, 'maximum' => 10000, 'default' => 100)), 'offset' => Core::field('integer', array('minimum' => 0, 'default' => 0))
        )), array('report')), array(__CLASS__, 'ga4'), 'manage_options');
        Core::ability('search-console-report', __('Read direct Google search performance', 'sternlabs-commerce-seo'), __('Clicks, impressions, CTR and average position by selected dimensions. Provider may return top rows only and omit anonymized queries. Pagination is not a guarantee of complete data.', 'sternlabs-commerce-seo'), Core::schema(array_merge(Core::date_fields(), array(
            'dimensions' => Core::field('array', array('items' => Core::field('string', array('enum' => array('query', 'page', 'date', 'country', 'device', 'searchAppearance'))), 'maxItems' => 5, 'uniqueItems' => true)),
            'search_type' => Core::field('string', array('enum' => array('web', 'image', 'video', 'news', 'discover', 'googleNews'), 'default' => 'web')),
            'row_limit' => Core::field('integer', array('minimum' => 1, 'maximum' => 25000, 'default' => 1000)), 'start_row' => Core::field('integer', array('minimum' => 0, 'default' => 0))
        ))), array(__CLASS__, 'gsc'), 'manage_options');
        Core::ability('search-console-url', __('Inspect URL Google index status', 'sternlabs-commerce-seo'), __('Uses Google URL Inspection for the configured Search Console property. This reads indexed status, not a live test or indexing request.', 'sternlabs-commerce-seo'), Core::schema(array('url' => Core::field('string', array('format' => 'uri'))), array('url')), array(__CLASS__, 'inspect'), 'manage_options');
        Core::ability('search-console-sitemaps', __('Read Search Console sitemaps', 'sternlabs-commerce-seo'), __('Lists submitted sitemaps and provider status. Read-only.', 'sternlabs-commerce-seo'), Core::schema(), array(__CLASS__, 'sitemaps'), 'manage_options');
        Core::ability('pagespeed', __('Run mobile or desktop Lighthouse audit', 'sternlabs-commerce-seo'), __('Sends one public site URL to Google PageSpeed Insights. May need an API key/quota. Lab results are not field Core Web Vitals. The API key is configured only in WordPress settings.', 'sternlabs-commerce-seo'), Core::schema(array('url' => Core::field('string', array('format' => 'uri')), 'strategy' => Core::field('string', array('enum' => array('mobile', 'desktop'), 'default' => 'mobile'))), array('url')), array(__CLASS__, 'pagespeed'), 'manage_options');
        Core::ability('crux', __('Read real-user Core Web Vitals', 'sternlabs-commerce-seo'), __('Queries CrUX directly. Requires a configured Google API key and sufficient eligible traffic. Missing data is not a failing performance score.', 'sternlabs-commerce-seo'), Core::schema(array('url' => Core::field('string', array('format' => 'uri')), 'form_factor' => Core::field('string', array('enum' => array('PHONE', 'DESKTOP', 'TABLET'), 'default' => 'PHONE'))), array('url')), array(__CLASS__, 'crux'), 'manage_options');
    }
    public static function encrypt($value) {
        if (!function_exists('openssl_encrypt')) { throw new \RuntimeException(esc_html__('PHP OpenSSL is required for encrypted credentials.', 'sternlabs-commerce-seo')); }
        $iv = random_bytes(12); $tag = ''; $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
        $encrypted = openssl_encrypt(wp_json_encode($value), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'sternlabs-google-v1');
        if ($encrypted === false) { throw new \RuntimeException(esc_html__('Credential encryption failed.', 'sternlabs-commerce-seo')); }
        return base64_encode($iv . $tag . $encrypted);
    }
    public static function decrypt($value) {
        if (!$value || !function_exists('openssl_decrypt')) { return array(); }
        $raw = base64_decode($value, true); if ($raw === false || strlen($raw) < 29) { return array(); }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'sternlabs-google-v1');
        $data = $plain === false ? null : json_decode($plain, true); return is_array($data) ? $data : array();
    }
    public static function credentials() { return self::decrypt(get_option('sci_google_credentials', '')); }
    public static function tokens() { return self::decrypt(get_option('sci_google_tokens', '')); }
    public static function status() {
        $c = self::credentials(); $t = self::tokens(); $s = Core::settings();
        return array('oauth_configured' => !empty($c['client_id']) && !empty($c['client_secret']), 'authorized' => !empty($t['refresh_token']),
            'ga4_property_configured' => !empty($s['ga4_property']), 'search_console_property_configured' => !empty($s['gsc_property']),
            'performance_api_key_configured' => !empty($c['api_key']), 'note' => __('Presence of tokens does not prove provider permissions; call a report to verify.', 'sternlabs-commerce-seo'));
    }
    public static function redirect_uri() { return admin_url('admin-post.php?action=sci_google_callback'); }
    public static function authorize() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Access denied.', 'sternlabs-commerce-seo')); }
        check_admin_referer('sci_google_connect'); $c = self::credentials();
        if (empty($c['client_id']) || empty($c['client_secret'])) { wp_die(esc_html__('Save a Google OAuth client ID and secret first.', 'sternlabs-commerce-seo')); }
        $state = wp_generate_password(48, false, false); $verifier = wp_generate_password(64, false, false);
        set_transient('sci_oauth_' . hash('sha256', $state), array('user' => get_current_user_id(), 'verifier' => $verifier), 10 * MINUTE_IN_SECONDS);
        $params = array('client_id' => $c['client_id'], 'redirect_uri' => self::redirect_uri(), 'response_type' => 'code', 'scope' => self::SCOPES,
            'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state, 'include_granted_scopes' => 'true',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256');
        $allow_google = static function ($hosts) { $hosts[] = 'accounts.google.com'; return $hosts; };
        add_filter('allowed_redirect_hosts', $allow_google);
        wp_safe_redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        remove_filter('allowed_redirect_hosts', $allow_google); exit;
    }
    public static function token_request($body) {
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array('timeout' => 20, 'redirection' => 0, 'limit_response_size' => 262144, 'body' => $body));
        if (is_wp_error($response)) { return Core::error(__('Google token request failed. Check outbound HTTPS and OAuth configuration.', 'sternlabs-commerce-seo'), 'sci_google_connection'); }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) !== 200 || empty($data['access_token'])) { return Core::error(__('Google rejected authorization. Verify credentials, redirect URI, enabled APIs and consent; reconnect if authorization expired.', 'sternlabs-commerce-seo'), 'sci_google_auth'); }
        return $data;
    }
    public static function callback() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Access denied.', 'sternlabs-commerce-seo')); }
        // OAuth providers cannot supply a WordPress nonce. The single-use, user-bound state and PKCE verifier below authenticate this callback.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? '')); $key = 'sci_oauth_' . hash('sha256', $state); $saved = get_transient($key); delete_transient($key);
        if (!$saved || (int) $saved['user'] !== get_current_user_id() || empty($_GET['code'])) { wp_die(esc_html__('Google connection was declined, expired, or failed state validation. Start again from MCP Capability Extender settings.', 'sternlabs-commerce-seo')); }
        $c = self::credentials();
        $tokens = self::token_request(array('client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'code' => sanitize_text_field(wp_unslash($_GET['code'])), 'redirect_uri' => self::redirect_uri(), 'grant_type' => 'authorization_code', 'code_verifier' => $saved['verifier']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (is_wp_error($tokens)) { wp_die(esc_html($tokens->get_error_message())); }
        if (empty($tokens['refresh_token'])) { wp_die(esc_html__('Google did not issue offline authorization. Revoke the previous app grant and connect again.', 'sternlabs-commerce-seo')); }
        $tokens['expires_at'] = time() + (int) ($tokens['expires_in'] ?? 3600);
        update_option('sci_google_tokens', self::encrypt($tokens), false);
        wp_safe_redirect(admin_url('tools.php?page=sternlabs-mcp&connected=1')); exit;
    }
    public static function token() {
        $tokens = self::tokens();
        if (!empty($tokens['access_token']) && ($tokens['expires_at'] ?? 0) > time() + 60) { return $tokens['access_token']; }
        return Core::lock('google-token', function () {
            $tokens = self::tokens(); $c = self::credentials();
            if (!empty($tokens['access_token']) && ($tokens['expires_at'] ?? 0) > time() + 60) { return $tokens['access_token']; }
            if (empty($tokens['refresh_token']) || empty($c['client_id']) || empty($c['client_secret'])) { return Core::error(__('Connect Google in Tools > MCP Capability Extender first.', 'sternlabs-commerce-seo'), 'sci_google_not_connected'); }
            $fresh = self::token_request(array('client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'refresh_token' => $tokens['refresh_token'], 'grant_type' => 'refresh_token'));
            if (is_wp_error($fresh)) { return $fresh; }
            $fresh['refresh_token'] = $fresh['refresh_token'] ?? $tokens['refresh_token']; $fresh['expires_at'] = time() + (int) ($fresh['expires_in'] ?? 3600);
            update_option('sci_google_tokens', self::encrypt($fresh), false); return $fresh['access_token'];
        });
    }
    public static function api($url, $body = null) {
        $allowed = array('analyticsdata.googleapis.com', 'analyticsadmin.googleapis.com', 'www.googleapis.com', 'searchconsole.googleapis.com');
        $parts = wp_parse_url($url);
        if (!$parts || !in_array($parts['host'] ?? '', $allowed, true) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) { return Core::error(__('Unapproved Google endpoint.', 'sternlabs-commerce-seo')); }
        $token = self::token(); if (is_wp_error($token)) { return $token; }
        $args = array('method' => $body === null ? 'GET' : 'POST', 'timeout' => 25, 'redirection' => 0, 'limit_response_size' => 5 * 1024 * 1024,
            'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'));
        if ($body !== null) { $args['body'] = wp_json_encode($body); }
        $response = wp_remote_request($url, $args); return self::decode_response($response);
    }
    public static function decode_response($response) {
        if (is_wp_error($response)) { return Core::error(__('Provider request failed. Check outbound HTTPS and retry.', 'sternlabs-commerce-seo'), 'sci_provider_connection'); }
        $status = wp_remote_retrieve_response_code($response); $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300) { return Core::error(__('Provider HTTP ', 'sternlabs-commerce-seo') . $status . '. Check API enablement, property access, quota and connection.', 'sci_provider_error'); }
        return is_array($data) ? $data : Core::error(__('Provider returned invalid or truncated JSON. Reduce report size.', 'sternlabs-commerce-seo'));
    }
    public static function properties() {
        $ga = self::api('https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200');
        $gsc = self::api('https://www.googleapis.com/webmasters/v3/sites');
        return array('ga4' => is_wp_error($ga) ? array('error' => $ga->get_error_message()) : $ga, 'search_console' => is_wp_error($gsc) ? array('error' => $gsc->get_error_message()) : $gsc);
    }
    public static function ga4($in) {
        $range = Core::date_range($in); if (is_wp_error($range)) { return $range; }
        $property = Core::settings()['ga4_property'] ?? ''; if (!preg_match('/^[0-9]+$/', $property)) { return Core::error(__('Configure the numeric GA4 property ID, not the G- measurement ID.', 'sternlabs-commerce-seo')); }
        $presets = array(
            'overview' => array(array(), array('totalUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'engagementRate', 'ecommercePurchases', 'purchaseRevenue')),
            'pages' => array(array('pagePath'), array('screenPageViews', 'totalUsers', 'userEngagementDuration')),
            'landing_pages' => array(array('landingPage'), array('sessions', 'engagedSessions', 'ecommercePurchases', 'purchaseRevenue')),
            'channels' => array(array('sessionDefaultChannelGroup', 'sessionSourceMedium'), array('sessions', 'engagedSessions', 'ecommercePurchases', 'purchaseRevenue')),
            'devices' => array(array('deviceCategory'), array('sessions', 'engagedSessions', 'ecommercePurchases', 'purchaseRevenue')),
            'countries' => array(array('country'), array('sessions', 'engagedSessions', 'ecommercePurchases', 'purchaseRevenue')),
            'products' => array(array('itemId', 'itemName'), array('itemsViewed', 'itemsAddedToCart', 'itemsPurchased', 'itemRevenue')),
            'events' => array(array('eventName'), array('eventCount', 'totalUsers'))
        );
        list($dims, $metrics) = $presets[$in['report']];
        $body = array('dateRanges' => array(array('startDate' => $range[0], 'endDate' => $range[1])), 'metrics' => array_map(function ($name) { return array('name' => $name); }, $metrics),
            'limit' => (string) ($in['limit'] ?? 100), 'offset' => (string) ($in['offset'] ?? 0), 'returnPropertyQuota' => true,
            'orderBys' => array(array('metric' => array('metricName' => $metrics[0]), 'desc' => true)));
        if ($dims) { $body['dimensions'] = array_map(function ($name) { return array('name' => $name); }, $dims); }
        $r = self::api('https://analyticsdata.googleapis.com/v1beta/properties/' . $property . ':runReport', $body);
        return is_wp_error($r) ? $r : array('source' => 'GA4 Data API', 'report' => $in['report'], 'date_range' => $range, 'data' => $r,
            'note' => __('Use response currencyCode/timeZone and metadata. GA4 is not an order ledger; consent, blockers, attribution and refunds can cause differences. Event counts are not a user-level funnel.', 'sternlabs-commerce-seo'));
    }
    public static function gsc_property() {
        $p = Core::settings()['gsc_property'] ?? ''; return $p !== '' ? $p : Core::error(__('Configure the exact Search Console property identifier.', 'sternlabs-commerce-seo'));
    }
    public static function gsc($in) {
        $range = Core::date_range($in); if (is_wp_error($range)) { return $range; } $site = self::gsc_property(); if (is_wp_error($site)) { return $site; }
        $r = self::api('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query', array('startDate' => $range[0], 'endDate' => $range[1],
            'dimensions' => $in['dimensions'] ?? array('query', 'page'), 'type' => $in['search_type'] ?? 'web', 'rowLimit' => $in['row_limit'] ?? 1000, 'startRow' => $in['start_row'] ?? 0, 'dataState' => 'final'));
        return is_wp_error($r) ? $r : array('source' => 'Search Console', 'date_range' => $range, 'data' => $r, 'note' => __('Finalized data only, provider timezone and aggregation. Anonymized queries and provider row limits mean rows may not equal totals.', 'sternlabs-commerce-seo'));
    }
    public static function inspect($in) {
        $url = SEO::safe_url($in['url']); if (is_wp_error($url)) { return $url; } $site = self::gsc_property(); if (is_wp_error($site)) { return $site; }
        $r = self::api('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', array('inspectionUrl' => $url, 'siteUrl' => $site, 'languageCode' => 'en-US'));
        return is_wp_error($r) ? $r : array('inspection' => $r, 'note' => __('Indexed version, not a live crawl test.', 'sternlabs-commerce-seo'));
    }
    public static function sitemaps() {
        $site = self::gsc_property(); if (is_wp_error($site)) { return $site; }
        $r = self::api('https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/sitemaps'); return is_wp_error($r) ? $r : array('sitemaps' => $r);
    }
    public static function pagespeed($in) {
        $url = SEO::safe_url($in['url']); if (is_wp_error($url)) { return $url; }
        $cache_key = 'sci_psi_' . md5($url . ($in['strategy'] ?? 'mobile')); $cached = get_transient($cache_key); if ($cached) { $cached['cached'] = true; return $cached; }
        $args = array('url' => $url, 'strategy' => $in['strategy'] ?? 'mobile', 'category' => 'performance');
        $c = self::credentials(); if (!empty($c['api_key'])) { $args['key'] = $c['api_key']; }
        $response = wp_remote_get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986), array('timeout' => 60, 'redirection' => 0, 'limit_response_size' => 8 * 1024 * 1024));
        $r = self::decode_response($response); if (is_wp_error($r)) { return $r; }
        $lh = $r['lighthouseResult'] ?? array(); $audits = array();
        foreach ($lh['audits'] ?? array() as $id => $audit) {
            if (isset($audit['score']) && $audit['score'] === 1) { continue; }
            $audits[] = array('id' => $id, 'title' => $audit['title'] ?? '', 'score' => $audit['score'] ?? null, 'displayValue' => $audit['displayValue'] ?? '', 'numericValue' => $audit['numericValue'] ?? null, 'numericUnit' => $audit['numericUnit'] ?? null);
        }
        $out = array('url' => $url, 'strategy' => $args['strategy'], 'measured_at' => $r['analysisUTCTimestamp'] ?? gmdate('c'), 'performance' => $lh['categories']['performance']['score'] ?? null,
            'audits' => $audits, 'environment' => $lh['environment'] ?? array(), 'runtime_error' => $lh['runtimeError'] ?? null, 'warnings' => $lh['runWarnings'] ?? array(), 'cached' => false, 'note' => __('Lab performance, not real-user INP. Use sternlabs/crux for field data. Cached for 1 hour.', 'sternlabs-commerce-seo'));
        set_transient($cache_key, $out, HOUR_IN_SECONDS); return $out;
    }
    public static function crux($in) {
        $url = SEO::safe_url($in['url']); if (is_wp_error($url)) { return $url; } $c = self::credentials();
        if (empty($c['api_key'])) { return Core::error(__('Configure a Google API key with Chrome UX Report API enabled.', 'sternlabs-commerce-seo')); }
        $response = wp_remote_post('https://chromeuxreport.googleapis.com/v1/records:queryRecord?key=' . rawurlencode($c['api_key']), array('timeout' => 20, 'redirection' => 0,
            'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode(array('url' => $url, 'formFactor' => $in['form_factor'] ?? 'PHONE', 'metrics' => array('largest_contentful_paint', 'interaction_to_next_paint', 'cumulative_layout_shift')))));
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 404) { return array('available' => false, 'reason' => __('No eligible URL field data returned. This is not a performance failure.', 'sternlabs-commerce-seo')); }
        $r = self::decode_response($response); return is_wp_error($r) ? $r : array('available' => true, 'source' => 'CrUX', 'data' => $r);
    }
}
