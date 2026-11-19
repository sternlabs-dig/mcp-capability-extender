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
        add_action('admin_menu', function () { add_management_page('MCP Capability Extender', 'MCP Capability Extender', 'manage_options', 'sternlabs-mcp', array(__CLASS__, 'page')); });
        add_action('admin_post_sci_save', array(__CLASS__, 'save'));
        add_action('admin_post_sci_google_connect', array('SternLabs\\CommerceSEO\\Google', 'authorize'));
        add_action('admin_post_sci_google_callback', array('SternLabs\\CommerceSEO\\Google', 'callback'));
        add_action('admin_post_sci_google_disconnect', array(__CLASS__, 'disconnect'));
        add_action('admin_notices', function () {
            if (current_user_can('manage_options') && !function_exists('wp_register_ability')) { echo '<div class="notice notice-error"><p>MCP Capability Extender requires WordPress 6.9+ Abilities API and an MCP adapter to expose tools.</p></div>'; }
        });
    }
    public static function register() {
        Core::ability('plugin-updates', 'List installed plugins and available updates', 'Reads WordPress plugin update information. Optional refresh contacts update providers. Premium availability depends on licenses.', Core::schema(array('refresh' => Core::field('boolean', array('default' => false)))), array(__CLASS__, 'updates'), 'update_plugins');
        Core::ability('plugin-update', 'Preview or update one plugin', 'Preview-token protected WordPress updater. YITH plugins are excluded. Does not promise a backup or automatic rollback. Use a tested backup and staging first.', Core::schema(array_merge(Core::mutation_fields(), array('plugin_file' => Core::field('string'))), array('plugin_file')), array(__CLASS__, 'update'), 'update_plugins', true);
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
    public static function input($name, $label, $value, $type = 'text') {
        echo '<tr><th><label for="sci_' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="sci_' . esc_attr($name) . '" name="' . esc_attr($name) . '" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '" autocomplete="off"></td></tr>';
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.'); } check_admin_referer('sci_save');
        $s = Core::settings(); $c = Google::credentials();
        $section = sanitize_key(wp_unslash($_POST['settings_section'] ?? 'legacy'));
        if (!in_array($section, array('permissions', 'google', 'legacy'), true)) { wp_die('Invalid settings section.'); }
        if ($section === 'permissions') {
            $s['allow_writes'] = !empty($_POST['allow_writes']); update_option('sci_settings', $s, false);
            Core::log('settings-save', array('writes_enabled' => $s['allow_writes']));
            wp_safe_redirect(admin_url('tools.php?page=sternlabs-mcp&saved=1')); exit;
        }
        foreach (array('client_id', 'client_secret', 'api_key', 'ga4_property', 'gsc_property') as $field) {
            if (isset($_POST[$field]) && (!is_string($_POST[$field]) || strlen($_POST[$field]) > 4096)) { wp_die('Invalid settings field.'); }
        }
        $id = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        $secret = trim(wp_unslash($_POST['client_secret'] ?? '')); $key = trim(wp_unslash($_POST['api_key'] ?? ''));
        $credentials_changed = ($c['client_id'] ?? '') !== $id || ($secret !== '' && ($c['client_secret'] ?? '') !== $secret);
        $c['client_id'] = $id; if ($secret !== '') { $c['client_secret'] = $secret; } if ($key !== '') { $c['api_key'] = $key; }
        $property = sanitize_text_field(wp_unslash($_POST['ga4_property'] ?? '')); if ($property !== '' && !preg_match('/^[0-9]+$/', $property)) { wp_die('Use the numeric GA4 property ID.'); }
        $gsc = sanitize_text_field(wp_unslash($_POST['gsc_property'] ?? ''));
        if ($gsc !== '' && !preg_match('~^(?:sc-domain:[a-zA-Z0-9.-]+|https?://[^\s]+)$~', $gsc)) { wp_die('Enter the exact property identifier from Google Search Console.'); }
        if ($section === 'legacy') { $s['allow_writes'] = !empty($_POST['allow_writes']); } $s['ga4_property'] = $property; $s['gsc_property'] = $gsc;
        try { update_option('sci_google_credentials', Google::encrypt($c), false); }
        catch (\Throwable $e) { wp_die('Credentials could not be encrypted. Check PHP OpenSSL support.'); }
        if ($credentials_changed) { delete_option('sci_google_tokens'); }
        update_option('sci_settings', $s, false); Core::log('settings-save', array('writes_enabled' => $s['allow_writes']));
        wp_safe_redirect(admin_url('tools.php?page=sternlabs-mcp&saved=1')); exit;
    }
    public static function disconnect() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.'); } check_admin_referer('sci_google_disconnect');
        $t = Google::tokens();
        if (!empty($t['refresh_token'])) { wp_remote_post('https://oauth2.googleapis.com/revoke', array('timeout' => 10, 'redirection' => 0, 'body' => array('token' => $t['refresh_token']))); }
        delete_option('sci_google_tokens');
        wp_safe_redirect(admin_url('tools.php?page=sternlabs-mcp')); exit;
    }
    public static function updates($in) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php'; require_once ABSPATH . 'wp-admin/includes/update.php';
        if (!empty($in['refresh'])) { wp_update_plugins(); }
        $updates = get_site_transient('update_plugins'); $rows = array();
        foreach (get_plugins() as $file => $plugin) {
            $u = $updates->response[$file] ?? null;
            $rows[] = array('plugin_file' => $file, 'name' => $plugin['Name'], 'installed_version' => $plugin['Version'], 'active' => is_plugin_active($file),
                'available_version' => $u ? $u->new_version : null, 'package_available' => $u && !empty($u->package), 'excluded' => stripos($file, 'yith') !== false);
        }
        return array('plugins' => $rows, 'last_checked' => $updates->last_checked ?? null, 'note' => 'Update availability comes from WordPress/provider caches. Premium packages may require an active license.');
    }
    public static function update($in) {
        return Core::lock('plugin-update', function () use ($in) {
            if (is_multisite() && !is_super_admin()) { return Core::error('Multisite updates require a super administrator.'); }
            if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) { return Core::error('File modifications are disabled by WordPress configuration.'); }
            $listing = self::updates(array()); $row = null;
            foreach ($listing['plugins'] as $p) { if ($p['plugin_file'] === $in['plugin_file']) { $row = $p; break; } }
            if (!$row || $row['excluded'] || !$row['available_version'] || !$row['package_available']) { return Core::error('Plugin missing, excluded (YITH), no update, or update package unavailable.'); }
            return Core::mutate('plugin-update', $in, $row, function () use ($in) {
                require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                $skin = new \Automatic_Upgrader_Skin(); $upgrader = new \Plugin_Upgrader($skin); $result = $upgrader->upgrade($in['plugin_file']);
                if (is_wp_error($result)) { return $result; }
                if (!$result) { return Core::error('WordPress did not complete the update. Check filesystem access and updater errors.'); }
                return array('plugin_file' => $in['plugin_file'], 'updated' => true, 'verify' => 'Check the frontend, checkout and plugin version. This extension did not create a backup.');
            });
        });
    }
}
