<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class Admin {
    public static function boot() {
        add_action('admin_enqueue_scripts', function ($hook) {
            if ($hook === 'tools_page_sternlabs-mcp') {
                wp_enqueue_style('sci-dashboard', plugins_url('assets/dashboard.css', dirname(__DIR__) . '/sternlabs-commerce-seo.php'), array('dashicons'), SCI_VERSION);
                wp_enqueue_script('sci-dashboard', plugins_url('assets/dashboard.js', dirname(__DIR__) . '/sternlabs-commerce-seo.php'), array(), SCI_VERSION, true);
            }
        });
        add_action('admin_menu', function () { add_management_page(__('MCP Capability Extender', 'sternlabs-commerce-seo'), __('MCP Capability Extender', 'sternlabs-commerce-seo'), 'manage_options', 'sternlabs-mcp', array(__CLASS__, 'page')); });
        add_action('admin_post_sci_save', array(__CLASS__, 'save'));
        add_action('admin_post_sci_google_connect', array('SternLabs\\CommerceSEO\\Google', 'authorize'));
        add_action('admin_post_sci_google_callback', array('SternLabs\\CommerceSEO\\Google', 'callback'));
        add_action('admin_post_sci_google_disconnect', array(__CLASS__, 'disconnect'));
        add_action('admin_notices', function () {
            if (current_user_can('manage_options') && !function_exists('wp_register_ability')) { echo '<div class="notice notice-error"><p>' . esc_html__('MCP Capability Extender requires WordPress 6.9+ Abilities API and an MCP adapter to expose tools.', 'sternlabs-commerce-seo') . '</p></div>'; }
        });
    }
    public static function register() {
        Core::ability('plugin-updates', __('List installed plugins and available updates', 'sternlabs-commerce-seo'), __('Reads WordPress plugin update information. Optional refresh contacts update providers. Premium availability depends on licenses.', 'sternlabs-commerce-seo'), Core::schema(array('refresh' => Core::field('boolean', array('default' => false)))), array(__CLASS__, 'updates'), 'update_plugins');
        Core::ability('plugin-update', __('Preview or update one plugin', 'sternlabs-commerce-seo'), __('Preview-token protected WordPress updater. YITH plugins are excluded. Does not promise a backup or automatic rollback. Use a tested backup and staging first.', 'sternlabs-commerce-seo'), Core::schema(array_merge(Core::mutation_fields(), array('plugin_file' => Core::field('string'))), array('plugin_file')), array(__CLASS__, 'update'), 'update_plugins', true);
    }
    public static function page() {
        if (!current_user_can('manage_options')) { return; }
        require __DIR__ . '/dashboard.php';
    }
    public static function status_card($icon, $title, $state, $subtitle, $good) {
        echo '<section class="sci-card sci-status-card"><span class="dashicons dashicons-' . esc_attr($icon) . '" aria-hidden="true"></span><div><h2>' . esc_html($title) . '</h2><span class="sci-pill ' . ($good ? 'sci-good' : 'sci-warn') . '">' . esc_html($state) . '</span><p>' . esc_html($subtitle) . '</p></div></section>';
    }
    public static function connection_row($title, $state) {
        echo '<div class="sci-connection-row"><strong>' . esc_html($title) . '</strong><span class="sci-pill">' . esc_html($state) . '</span></div>';
    }
    public static function activity_result($result) {
        $labels = array('success' => __('Successful', 'sternlabs-commerce-seo'), 'failed' => __('Failed', 'sternlabs-commerce-seo'), 'partial_or_failed' => __('Partial or failed', 'sternlabs-commerce-seo'));
        return $labels[$result] ?? __('Recorded', 'sternlabs-commerce-seo');
    }
    public static function input($name, $label, $value, $type = 'text') {
        echo '<tr><th><label for="sci_' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="sci_' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '" autocomplete="off"></td></tr>';
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Access denied.', 'sternlabs-commerce-seo')); } check_admin_referer('sci_save');
        $s = Core::settings(); $c = Google::credentials();
        $section = sanitize_key(self::posted('settings_section', 'legacy'));
        if (!in_array($section, array('permissions', 'google', 'legacy'), true)) { wp_die(esc_html__('Invalid settings section.', 'sternlabs-commerce-seo')); }
        if ($section === 'permissions') {
            $s['allow_writes'] = !empty($_POST['allow_writes']); update_option('sci_settings', $s, false);
            Core::log('settings-save', array('writes_enabled' => $s['allow_writes']));
            wp_safe_redirect(admin_url('tools.php?page=sternlabs-mcp&saved=1')); exit;
        }
        $id = sanitize_text_field(self::posted('client_id'));
        $secret = trim(self::posted('client_secret')); $key = trim(self::posted('api_key'));
        $credentials_changed = ($c['client_id'] ?? '') !== $id || ($secret !== '' && ($c['client_secret'] ?? '') !== $secret);
        $c['client_id'] = $id; if ($secret !== '') { $c['client_secret'] = $secret; } if ($key !== '') { $c['api_key'] = $key; }
        $property = sanitize_text_field(self::posted('ga4_property')); if ($property !== '' && !preg_match('/^[0-9]+$/', $property)) { wp_die(esc_html__('Use the numeric GA4 property ID.', 'sternlabs-commerce-seo')); }
        $gsc = sanitize_text_field(self::posted('gsc_property'));
        if ($gsc !== '' && !preg_match('~^(?:sc-domain:[a-zA-Z0-9.-]+|https?://[^\s]+)$~', $gsc)) { wp_die(esc_html__('Enter the exact property identifier from Google Search Console.', 'sternlabs-commerce-seo')); }
        if ($section === 'legacy') { $s['allow_writes'] = !empty($_POST['allow_writes']); } $s['ga4_property'] = $property; $s['gsc_property'] = $gsc;
        try { update_option('sci_google_credentials', Google::encrypt($c), false); }
        catch (\Throwable $e) { wp_die(esc_html__('Credentials could not be encrypted. Check PHP OpenSSL support.', 'sternlabs-commerce-seo')); }
        if ($credentials_changed) { delete_option('sci_google_tokens'); }
        update_option('sci_settings', $s, false); Core::log('settings-save', array('writes_enabled' => $s['allow_writes']));
        wp_safe_redirect(admin_url('tools.php?page=sternlabs-mcp&saved=1')); exit;
    }
    public static function disconnect() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Access denied.', 'sternlabs-commerce-seo')); } check_admin_referer('sci_google_disconnect');
        $t = Google::tokens();
        if (!empty($t['refresh_token'])) { wp_remote_post('https://oauth2.googleapis.com/revoke', array('timeout' => 10, 'redirection' => 0, 'body' => array('token' => $t['refresh_token']))); }
        delete_option('sci_google_tokens');
        wp_safe_redirect(admin_url('tools.php?page=sternlabs-mcp')); exit;
    }
    /** Read a nonce-protected admin form value. The caller checks capability and nonce first. */
    public static function posted($name, $default = '') {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Access denied.', 'sternlabs-commerce-seo')); }
        check_admin_referer('sci_save');
        if (!isset($_POST[$name])) { return $default; }
        // Secrets must retain punctuation. Validate scalar type, length and control bytes instead of altering credentials.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below; callers sanitize non-secret fields for their specific use.
        $value = wp_unslash($_POST[$name]);
        if (!is_string($value) || strlen($value) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $value)) { wp_die(esc_html__('Invalid settings field.', 'sternlabs-commerce-seo')); }
        return $value;
    }
    public static function wordpress_org_package($url) {
        if (!is_string($url)) { return false; }
        $p = wp_parse_url($url);
        return $p && ($p['scheme'] ?? '') === 'https' && strtolower($p['host'] ?? '') === 'downloads.wordpress.org'
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['query']) && !isset($p['fragment'])
            && (!isset($p['port']) || (int) $p['port'] === 443)
            && preg_match('~^/plugin/[a-z0-9][a-z0-9._-]*\.zip$~D', $p['path'] ?? '') === 1;
    }
    public static function updates($in) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php'; require_once ABSPATH . 'wp-admin/includes/update.php';
        if (!empty($in['refresh'])) { wp_update_plugins(); }
        $updates = get_site_transient('update_plugins'); $rows = array();
        foreach (get_plugins() as $file => $plugin) {
            $u = $updates->response[$file] ?? null;
            $rows[] = array('plugin_file' => $file, 'name' => $plugin['Name'], 'installed_version' => $plugin['Version'], 'active' => is_plugin_active($file),
                'available_version' => $u ? $u->new_version : null, 'package_available' => $u && !empty($u->package), 'wordpress_org_package' => $u && !empty($u->package) && self::wordpress_org_package($u->package), 'excluded' => stripos($file, 'yith') !== false);
        }
        return array('plugins' => $rows, 'last_checked' => $updates->last_checked ?? null, 'note' => __('Update availability comes from WordPress/provider caches. Premium packages may require an active license.', 'sternlabs-commerce-seo'));
    }
    public static function update($in) {
        return Core::lock('plugin-update', function () use ($in) {
            if (is_multisite() && !is_super_admin()) { return Core::error(__('Multisite updates require a super administrator.', 'sternlabs-commerce-seo')); }
            if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) { return Core::error(__('File modifications are disabled by WordPress configuration.', 'sternlabs-commerce-seo')); }
            $listing = self::updates(array()); $row = null;
            foreach ($listing['plugins'] as $p) { if ($p['plugin_file'] === $in['plugin_file']) { $row = $p; break; } }
            if (!$row || $row['excluded'] || !$row['available_version'] || !$row['package_available']) { return Core::error(__('Plugin missing, excluded (YITH), no update, or update package unavailable.', 'sternlabs-commerce-seo')); }
            if (!$row['wordpress_org_package']) { return Core::error(__('This directory build updates WordPress.org packages only. Use the plugin provider update screen for other sources.', 'sternlabs-commerce-seo'), 'sci_update_source'); }
            return Core::mutate('plugin-update', $in, $row, function () use ($in) {
                require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                $source_guard = function ($reply, $package) {
                    return self::wordpress_org_package($package) ? $reply : Core::error(__('Update package is not from WordPress.org.', 'sternlabs-commerce-seo'), 'sci_update_source');
                };
                add_filter('upgrader_pre_download', $source_guard, 10, 2);
                try { $skin = new \Automatic_Upgrader_Skin(); $upgrader = new \Plugin_Upgrader($skin); $result = $upgrader->upgrade($in['plugin_file']); }
                finally { remove_filter('upgrader_pre_download', $source_guard, 10); }
                if (is_wp_error($result)) { return $result; }
                if (!$result) { return Core::error(__('WordPress did not complete the update. Check filesystem access and updater errors.', 'sternlabs-commerce-seo')); }
                return array('plugin_file' => $in['plugin_file'], 'updated' => true, 'verify' => 'Check the frontend, checkout and plugin version. This extension did not create a backup.');
            });
        });
    }
}
