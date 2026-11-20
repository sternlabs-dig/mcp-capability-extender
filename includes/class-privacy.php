<?php
/** Privacy guidance for site owners. */
namespace SternLabs\CommerceSEO;
defined('ABSPATH') || exit;

final class Privacy {
    public static function boot() {
        add_action('admin_init', array(__CLASS__, 'suggest_policy'));
    }
    public static function suggest_policy() {
        if (!function_exists('wp_add_privacy_policy_content')) { return; }
        $text = '<h2>' . esc_html__('MCP Capability Extender', 'sternlabs-commerce-seo') . '</h2>';
        $text .= '<p>' . esc_html__('Suggested text: Review and adapt this description to your actual configuration and AI provider before publishing your site privacy policy.', 'sternlabs-commerce-seo') . '</p>';
        $text .= '<p>' . esc_html__('Authorized integrations can request product information, sales aggregates and public-page audit results through the site MCP adapter. The requesting AI client and its provider receive the requested results. Access is governed by WordPress user permissions and the site connection credentials. This extension does not install visitor tracking.', 'sternlabs-commerce-seo') . '</p>';
        $text .= '<p>' . esc_html__('Optional Google connections send OAuth authorization data, selected property identifiers, reporting dates, dimensions and metrics to Google Analytics and Search Console. URL inspection and performance requests send the selected public URL to Google. Requests occur when an authorized user connects an account, disconnects it or requests an operation; token refresh can occur during those requests. Existing tracking, if present on the site, has its own data collection and consent requirements.', 'sternlabs-commerce-seo') . '</p>';
        $text .= '<p>' . esc_html__('Google credentials and tokens remain stored on the site, encrypted using WordPress salts, until removed or the plugin is uninstalled. Report jobs contain pseudonymous customer hashes for repeat-customer counts; no customer contact details are returned by those reports. Reports and crawl results expire after 24 hours. The latest 100 extension activity records contain the acting WordPress user ID, time, action and limited scope. Temporary previews expire after ten minutes; result receipts expire after one hour. WordPress cron and database/cache cleanup affect when expired data is physically removed. Deactivation retains settings; uninstall removes site-local plugin data.', 'sternlabs-commerce-seo') . '</p>';
        $text .= '<p><a href="https://policies.google.com/privacy">' . esc_html__('Google privacy policy', 'sternlabs-commerce-seo') . '</a></p>';
        wp_add_privacy_policy_content('MCP Capability Extender', wp_kses_post($text));
    }
}
