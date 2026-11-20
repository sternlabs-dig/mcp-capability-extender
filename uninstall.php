<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
// Site-local cleanup only. Network administrators should uninstall per-site data before deleting a network installation.
foreach (array('sci_settings', 'sci_google_credentials', 'sci_google_tokens', 'sci_changes') as $key) { delete_option($key); }
global $wpdb;
wp_clear_scheduled_hook('sci_security_cleanup');
foreach (array('_transient_sci_', '_transient_timeout_sci_', 'sci_lock_', 'sci_claim_') as $prefix) {
    $like = $wpdb->esc_like($prefix) . '%';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded plugin-owned cleanup needs current expiry data and prepared SQL, not cached results.
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
}
