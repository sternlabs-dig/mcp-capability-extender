<?php
/**
 * Plugin Name: MCP Capability Extender
 * Description: Direct WooCommerce reporting, product operations, SEO crawling and Google reporting through WordPress abilities.
 * Version: 0.3.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author: SternLabs
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: sternlabs-commerce-seo
 */
defined('ABSPATH') || exit;
define('SCI_VERSION', '0.3.0');
define('SCI_DIR', __DIR__);
foreach (array('security', 'core', 'catalog', 'reports', 'seo', 'google', 'admin') as $module) {
    require_once SCI_DIR . '/includes/class-' . $module . '.php';
}
\SternLabs\CommerceSEO\Core::boot();
add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
register_deactivation_hook(__FILE__, array('SternLabs\\CommerceSEO\\Core', 'deactivate'));
