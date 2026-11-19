<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;
$status = Core::status(); $s = Core::settings(); $g = $status['google']; $c = Google::credentials();
$changes = array_slice((array) get_option('sci_changes', array()), 0, 12);
$seo = $status['seo_plugin'] === 'none' ? 'Not detected' : ($status['seo_plugin'] === 'yoast' ? 'Yoast detected' : 'Rank Math detected');
?>
<div class="wrap sci-dashboard">
    <header class="sci-header">
        <div><span class="sci-brand"><img src="<?php echo esc_url(plugins_url('assets/sternlabs-logo.png', dirname(__DIR__) . '/sternlabs-commerce-seo.php')); ?>" alt="Stern Labs" width="140"></span>
        <h1>MCP Capability Extender</h1><p>Manage your connection, permissions and reporting.</p></div>
        <span class="sci-version">v<?php echo esc_html(SCI_VERSION); ?></span>
    </header>
    <?php if (isset($_GET['saved'])) { ?><div class="notice notice-success inline"><p>Settings saved.</p></div><?php } ?>
    <nav class="sci-nav" aria-label="Dashboard sections"><a href="#sci-overview">Overview</a><a href="#sci-connections">Connections</a><a href="#sci-activity">Activity</a><a href="#sci-settings">Settings</a></nav>
    <section id="sci-overview" class="sci-card sci-connection">
        <div><span class="sci-pill sci-good">Plugin active</span><p><?php echo esc_html(wp_parse_url(home_url('/'), PHP_URL_HOST)); ?></p></div>
        <div><strong>WordPress abilities <?php echo function_exists('wp_register_ability') ? 'available' : 'unavailable'; ?></strong><p>External MCP access is verified from your AI client.</p></div>
        <details class="sci-details"><summary>View technical details</summary><pre><?php echo esc_html(wp_json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></details>
    </section>
    <div class="sci-status-grid">
        <?php self::status_card('cart', 'WooCommerce', $status['woocommerce'] ? 'Available' : 'Not detected', 'Products, inventory and sales', $status['woocommerce']); ?>
        <?php self::status_card('search', 'SEO integration', $seo, 'Product SEO fields', $status['seo_plugin'] !== 'none'); ?>
        <?php self::status_card('chart-bar', 'Google reporting', $g['authorized'] ? 'Authorization saved' : 'Setup required', $g['authorized'] ? 'Run a report to verify access' : 'Connect Google to enable reports', $g['authorized']); ?>
    </div>
    <div class="sci-main-grid">
        <section class="sci-card" id="sci-settings">
            <h2><span class="dashicons dashicons-shield"></span> Permissions &amp; security</h2><p>Your existing workflow, with stronger protection.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sci_save"><input type="hidden" name="settings_section" value="permissions">
                <?php wp_nonce_field('sci_save'); ?>
                <label class="sci-switch"><input type="checkbox" name="allow_writes" value="1" <?php checked(!empty($s['allow_writes'])); ?>><span class="sci-toggle" aria-hidden="true"></span><span><strong>Allow changes</strong><small>Authorized users can edit products, SEO and run maintenance.</small></span></label>
                <ul class="sci-checks"><li>WordPress permission checks</li><li>Single-use protected preview tokens</li><li>Input validation and request limits</li><li>Verified saved results for bulk product edits</li></ul>
                <p class="sci-muted">Checks run automatically in the background.</p>
                <button class="button button-primary">Save permissions</button>
            </form>
        </section>
        <section class="sci-card" id="sci-connections">
            <h2><span class="dashicons dashicons-chart-area"></span> Google reporting</h2><p>Connect your own Google account for Analytics and Search Console reports.</p>
            <?php self::connection_row('Google Analytics 4', $g['ga4_property_configured'] ? ($g['authorized'] ? 'Configured · not verified' : 'Authorization required') : 'Property required'); ?>
            <?php self::connection_row('Search Console', $g['search_console_property_configured'] ? ($g['authorized'] ? 'Configured · not verified' : 'Authorization required') : 'Property required'); ?>
            <?php self::connection_row('PageSpeed Insights', $g['performance_api_key_configured'] ? 'API key saved' : 'API key not configured'); ?>
            <form class="sci-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="sci_google_connect"><?php wp_nonce_field('sci_google_connect'); ?><button class="button button-primary"><?php echo $g['authorized'] ? 'Reconnect Google' : 'Connect Google'; ?></button></form>
            <a class="sci-text-link" href="#sci-credentials">Configure credentials</a>
            <?php if ($g['authorized']) { ?><form class="sci-disconnect" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="sci_google_disconnect"><?php wp_nonce_field('sci_google_disconnect'); ?><button class="button">Disconnect Google</button></form><?php } ?>
        </section>
    </div>
    <section class="sci-card" id="sci-activity"><h2><span class="dashicons dashicons-backup"></span> Recent activity</h2><p>Latest actions recorded by this extension. Existing entries may have limited detail.</p>
        <div class="sci-table-scroll"><table class="sci-table"><thead><tr><th>Action</th><th>Scope</th><th>Result</th><th>Time (UTC)</th></tr></thead><tbody>
        <?php if (!$changes) { ?><tr><td colspan="4">No recorded activity yet.</td></tr><?php } ?>
        <?php foreach ($changes as $row) { $summary = is_array($row['summary'] ?? null) ? $row['summary'] : array(); $count = $summary['count'] ?? null; ?><tr><td><?php echo esc_html(ucwords(str_replace('-', ' ', $row['action'] ?? 'Action'))); ?></td><td><?php echo esc_html($count ? $count . ' product(s)' : 'Site operation'); ?><?php if (!empty($summary['product_ids'])) { ?><details><summary>Product IDs</summary><?php echo esc_html(implode(', ', $summary['product_ids'])); ?></details><?php } ?></td><td><span class="sci-pill <?php echo ($row['result'] ?? '') === 'success' ? 'sci-good' : 'sci-warn'; ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $row['result'] ?? 'Recorded'))); ?></span></td><td><?php echo esc_html($row['time'] ?? ''); ?></td></tr><?php } ?>
        </tbody></table></div><p class="sci-muted">Latest 100 extension changes retained. This is not a complete WordPress activity log.</p>
    </section>
    <details class="sci-card sci-credentials" id="sci-credentials"><summary>Google credentials &amp; reporting settings</summary>
        <p>Use your own Google Cloud OAuth client with Analytics Data, Analytics Admin and Search Console APIs enabled. Access is read-only. Existing analytics tracking is required for traffic reports.</p><p>Authorized redirect URI:</p><code class="sci-code"><?php echo esc_html(Google::redirect_uri()); ?></code>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="sci_save"><input type="hidden" name="settings_section" value="google"><?php wp_nonce_field('sci_save'); ?><table class="form-table">
        <?php self::input('client_id', 'Google OAuth client ID', $c['client_id'] ?? ''); self::input('client_secret', 'Google OAuth client secret', '', 'password'); self::input('api_key', 'Performance API key', '', 'password'); self::input('ga4_property', 'Numeric GA4 property ID', $s['ga4_property'] ?? ''); self::input('gsc_property', 'Search Console property', $s['gsc_property'] ?? ''); ?>
        </table><p>Blank secret and API key fields keep saved values. PageSpeed and CrUX require their Google APIs and sufficient quota. Google authorization may need renewal.</p><button class="button button-primary">Save reporting settings</button></form>
    </details>
    <details class="sci-card"><summary>Connection help &amp; data retention</summary><p>Keep your existing MCP adapter server URL and credentials. After updating, reconnect your AI client if its tool list is cached. The dashboard cannot prove an external client is connected.</p><p>Reports and crawls expire after 24 hours, previews after 10 minutes, bulk result receipts and performance caches after one hour. Hourly cleanup removes expired local security data when WordPress cron runs. Google credentials are encrypted using WordPress salts; changing salts requires reconnecting Google.</p><p>Changes are sequential and have no automatic rollback. Maintain tested backups. Disabling writes affects this extension only.</p></details>
    <footer class="sci-footer">MCP Capability Extender <span>Built by Stern Labs</span></footer>
</div>
