<?php
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class Core {
    const PREFIX = 'sternlabs/';
    private static $write_caps = array();
    public static function boot() {
        add_action('wp_abilities_api_categories_init', function () {
            if (function_exists('wp_register_ability_category')) {
                wp_register_ability_category('sternlabs', array('label' => 'MCP Capability Extender', 'description' => __('Commerce, reporting and SEO.', 'sternlabs-commerce-seo')));
            }
        });
        add_action('wp_abilities_api_init', array(__CLASS__, 'register'));
        Admin::boot();
        Security::boot();
        Privacy::boot();
    }
    public static function deactivate() {
        wp_clear_scheduled_hook('sci_job_tick');
        wp_clear_scheduled_hook('sci_security_cleanup');
    }
    public static function settings() { return (array) get_option('sci_settings', array()); }
    public static function error($message, $code = 'sci_invalid') { return new \WP_Error($code, $message); }
    public static function schema($properties = array(), $required = array()) {
        $schema = array('type' => 'object', 'additionalProperties' => false);
        if ($properties) { $schema['properties'] = $properties; }
        if ($required) { $schema['required'] = $required; }
        return $schema;
    }
    public static function field($type, $extra = array()) { return array_merge(array('type' => $type), $extra); }
    public static function page_fields() {
        return array('page' => self::field('integer', array('minimum' => 1, 'default' => 1)), 'per_page' => self::field('integer', array('minimum' => 1, 'maximum' => 100, 'default' => 50)));
    }
    public static function mutation_fields() {
        return array('dry_run' => self::field('boolean', array('default' => true)), 'preview_token' => self::field('string'));
    }
    public static function allowed($cap, $write = false) {
        if (!is_user_logged_in() || !current_user_can($cap)) { return false; }
        return !$write || !empty(self::settings()['allow_writes']);
    }
    public static function ability($slug, $label, $description, $schema, $callback, $cap, $write = false) {
        if (!function_exists('wp_register_ability')) { return; }
        $name = self::PREFIX . $slug;
        if ($write) { self::$write_caps[$slug] = $cap; }
        if (function_exists('wp_get_ability') && wp_get_ability($name)) { return; }
        wp_register_ability($name, array(
            'label' => $label, 'description' => $description, 'category' => 'sternlabs',
            'input_schema' => $schema, 'output_schema' => array('type' => 'object'),
            'permission_callback' => function () use ($cap, $write) { return Core::allowed($cap, $write); },
            'execute_callback' => function ($input = array()) use ($callback, $cap, $write, $schema, $slug) {
                if (!Core::allowed($cap, $write)) { return Core::error(__('Access denied.', 'sternlabs-commerce-seo'), 'sci_access_denied'); }
                try {
                    $guard = Security::gate($input, $schema, $write); if (is_wp_error($guard)) { return $guard; }
                    return call_user_func($callback, (array) $input);
                } catch (\Throwable $e) {
                    Core::log($slug, array('error_code' => 'unexpected_failure'), 'failed');
                    return Core::error(__('Operation failed. Check the server error log or retry with a fresh preview.', 'sternlabs-commerce-seo'), 'sci_operation_failed');
                }
            },
            'meta' => array('public' => true, 'show_in_rest' => true, 'mcp' => array('public' => true, 'type' => 'tool'),
                'annotations' => array('readonly' => !$write, 'destructive' => $write, 'idempotent' => !$write)),
        ));
    }
    public static function register() {
        self::ability('status', __('Connection and feature status', 'sternlabs-commerce-seo'), __('Read feature availability, installed dependencies and Google connection status. Never returns credentials.', 'sternlabs-commerce-seo'), self::schema(), array(__CLASS__, 'status'), 'manage_options');
        self::ability('change-log', __('View extension change log', 'sternlabs-commerce-seo'), __('Latest 100 changes made by this extension only. This is not a full site activity log.', 'sternlabs-commerce-seo'), self::schema(), function () { return array('changes' => get_option('sci_changes', array())); }, 'manage_options');
        Catalog::register(); Reports::register(); SEO::register(); Google::register(); Admin::register();
    }
    public static function status() {
        return array('name' => 'MCP Capability Extender', 'version' => SCI_VERSION, 'site' => home_url('/'), 'timezone' => wp_timezone_string(),
            'woocommerce' => class_exists('WooCommerce'), 'seo_plugin' => defined('WPSEO_VERSION') ? 'yoast' : (defined('RANK_MATH_VERSION') ? 'rank_math' : 'none'),
            'writes_enabled' => !empty(self::settings()['allow_writes']), 'google' => Google::status(),
            'capabilities' => array('category_and_attribute_management', 'variable_products', 'combined_product_copy_and_seo_batches', 'verified_bulk_results', 'bulk_result_recovery', 'multi_status_product_lookup', 'compact_product_lookup', 'sales_cohort_reports', 'html_seo_audits', 'resumable_site_audits', 'google_direct_reports', 'pagespeed_and_crux', 'plugin_updates'),
            'not_implemented' => array('native_visitor_tracking', 'native_frontend_seo_output', 'backlink_provider', 'browser_rendered_crawling', 'scheduled_report_delivery', 'full_rank_math_settings_bridge'),
            'reporting_note' => __('Sales use WooCommerce. Traffic and Google rankings require direct Google authorization. No external WordPress analytics plugin is needed.', 'sternlabs-commerce-seo'));
    }
    public static function log($action, $summary, $result = 'success') {
        $rows = (array) get_option('sci_changes', array());
        array_unshift($rows, array('time' => gmdate('c'), 'user_id' => get_current_user_id(), 'action' => sanitize_text_field($action), 'summary' => Security::summary($summary), 'result' => sanitize_text_field($result)));
        update_option('sci_changes', array_slice($rows, 0, 100), false);
    }
    public static function digest($value) { return hash('sha256', wp_json_encode($value)); }
    public static function mutate($action, $input, $plan, $execute) {
        if (isset(self::$write_caps[$action]) && !self::allowed(self::$write_caps[$action], true)) { return self::error(__('Access denied.', 'sternlabs-commerce-seo'), 'sci_access_denied'); }
        if (empty(self::settings()['allow_writes'])) { return self::error(__('Enable product/SEO/maintenance writes in Tools > MCP Capability Extender first.', 'sternlabs-commerce-seo'), 'sci_read_only'); }
        $fingerprint = self::digest(array($action, $plan));
        if (($input['dry_run'] ?? true) === true) {
            $token = wp_generate_password(40, false, false);
            set_transient('sci_preview_' . hash('sha256', $token), array('user' => get_current_user_id(), 'hash' => $fingerprint), 10 * MINUTE_IN_SECONDS);
            return array('applied' => false, 'plan' => $plan, 'preview_token' => $token, 'expires_in_seconds' => 600,
                'next_step' => __('Review plan, then repeat identical request with dry_run=false and preview_token.', 'sternlabs-commerce-seo'));
        }
        $key = 'sci_preview_' . hash('sha256', (string) ($input['preview_token'] ?? ''));
        $preview = get_transient($key);
        if (!$preview || (int) $preview['user'] !== get_current_user_id() || !hash_equals($preview['hash'], $fingerprint)) {
            return self::error(__('Preview is missing, expired, changed, or the record has changed. Generate a new preview.', 'sternlabs-commerce-seo'), 'sci_stale_preview');
        }
        if (!Security::claim_preview((string) $input['preview_token'])) { return self::error(__('Preview is already in use or was consumed. Generate a new preview.', 'sternlabs-commerce-seo'), 'sci_stale_preview'); }
        delete_transient($key);
        $result = call_user_func($execute);
        $outcome = is_wp_error($result) ? 'failed' : ((isset($result['all_saved']) && !$result['all_saved']) ? 'partial_or_failed' : 'success');
        $ids = array();
        foreach (($plan['items'] ?? array()) as $item) { if (isset($item['id'])) { $ids[] = (int) $item['id']; } }
        if (isset($input['id'])) { $ids[] = (int) $input['id']; }
        if (isset($input['post_id'])) { $ids[] = (int) $input['post_id']; }
        self::log($action, array('plan_hash' => $fingerprint, 'product_ids' => array_values(array_unique($ids)), 'count' => count(array_unique($ids))), $outcome);
        return is_wp_error($result) ? $result : array('applied' => true, 'result' => $result);
    }
    public static function lock($resource, $callback) {
        $key = 'sci_lock_' . md5($resource);
        if (!add_option($key, time(), '', false)) {
            $created = (int) get_option($key);
            if (time() - $created < 300) { return self::error(__('Resource is busy. Retry later.', 'sternlabs-commerce-seo'), 'sci_busy'); }
            delete_option($key);
            if (!add_option($key, time(), '', false)) { return self::error(__('Resource is busy.', 'sternlabs-commerce-seo'), 'sci_busy'); }
        }
        try { return call_user_func($callback); } finally { delete_option($key); }
    }
    public static function date_range($input) {
        $start = $input['start_date'] ?? wp_date('Y-m-d', strtotime('-30 days'));
        $end = $input['end_date'] ?? wp_date('Y-m-d');
        foreach (array($start, $end) as $value) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) { return self::error(__('Dates must be valid YYYY-MM-DD values.', 'sternlabs-commerce-seo')); }
        }
        if ($start > $end) { return self::error(__('start_date must not be after end_date.', 'sternlabs-commerce-seo')); }
        return array($start, $end);
    }
    public static function date_fields() {
        return array('start_date' => self::field('string', array('pattern' => '^\\d{4}-\\d{2}-\\d{2}$')), 'end_date' => self::field('string', array('pattern' => '^\\d{4}-\\d{2}-\\d{2}$')));
    }
}
