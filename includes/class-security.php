<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class Security {
    public static function boot() {
        add_action('sci_security_cleanup', array(__CLASS__, 'cleanup'));
        add_action('init', function () {
            if (!wp_next_scheduled('sci_security_cleanup')) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'sci_security_cleanup'); }
        });
    }
    public static function validate_size($value, $depth = 0, &$nodes = 0) {
        if (++$nodes > 40000 || $depth > 12) { return Core::error(__('Request structure exceeds the safety limit.', 'sternlabs-commerce-seo'), 'sci_request_limit'); }
        if (is_string($value) && (strlen($value) > 262144 || strpos($value, "\0") !== false)) { return Core::error(__('A request field exceeds the safety limit or contains invalid characters.', 'sternlabs-commerce-seo'), 'sci_request_limit'); }
        if (is_array($value)) { foreach ($value as $child) { $result = self::validate_size($child, $depth + 1, $nodes); if (is_wp_error($result)) { return $result; } } }
        return true;
    }
    public static function gate($input, $schema, $write) {
        $size = self::validate_size($input); if (is_wp_error($size)) { return $size; }
        $encoded = wp_json_encode($input);
        if ($encoded === false || strlen($encoded) > 4 * 1024 * 1024) { return Core::error(__('Request exceeds the safety limit.', 'sternlabs-commerce-seo'), 'sci_request_limit'); }
        $valid = rest_validate_value_from_schema($input, $schema);
        if (is_wp_error($valid)) { return Core::error(__('Request fields do not match the tool schema.', 'sternlabs-commerce-seo'), 'sci_invalid_request'); }
        return self::rate_limit($write);
    }
    public static function rate_limit($write) {
        $bucket = 'sci_rate_' . get_current_user_id() . '_' . ($write ? 'write' : 'read') . '_' . (int) floor(time() / 60);
        return Core::lock($bucket, function () use ($bucket, $write) {
            $count = (int) get_transient($bucket);
            if ($count >= ($write ? 60 : 180)) { return Core::error(__('Request limit reached. Retry after one minute.', 'sternlabs-commerce-seo'), 'sci_rate_limit'); }
            set_transient($bucket, $count + 1, 120); return true;
        });
    }
    public static function claim_preview($token) {
        // Keep the claim after completion: persistent unique option insertion is atomic,
        // including when an object cache is enabled or a worker terminates mid-save.
        return add_option('sci_claim_' . hash('sha256', $token), time() + 20 * MINUTE_IN_SECONDS, '', false);
    }
    public static function message($message) {
        $message = sanitize_text_field($message);
        $message = preg_replace('~\b(?:Basic|Bearer)\s+[A-Za-z0-9+/=_\-.]+~i', '[redacted authorization]', $message);
        return preg_replace('~((?:password|client_secret|refresh_token|access_token|api_key|authorization)\s*[=:]\s*)[^\s,;]+~i', '$1[redacted]', $message);
    }
    public static function summary($value, $key = '') {
        if (preg_match('/password|secret|token|authorization|api_key|description|content/i', $key)) { return '[redacted]'; }
        if (is_array($value)) { $out = array(); foreach ($value as $k => $v) { $out[$k] = self::summary($v, (string) $k); } return $out; }
        return is_string($value) ? self::message($value) : $value;
    }
    public static function cleanup() {
        global $wpdb;
        // Bounded, plugin-owned options only. Also removes expired data in external caches
        // through delete_transient; normal transient TTL remains authoritative there.
        $prefix = $wpdb->esc_like('_transient_timeout_sci_') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded plugin-owned cleanup needs current expiry data and prepared SQL, not cached results.
        $rows = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT 500", $prefix, time()));
        foreach ($rows as $row) { delete_transient(substr($row, strlen('_transient_timeout_'))); }
        foreach (array('sci_claim_' => time(), 'sci_lock_' => time() - HOUR_IN_SECONDS) as $prefix => $expiry) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded plugin-owned cleanup needs current expiry data and prepared SQL, not cached results.
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT 500", $wpdb->esc_like($prefix) . '%', $expiry));
        }
    }
}
