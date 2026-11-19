<?php
namespace SternLabs\CommerceSEO;
// Test doubles are namespace-local. They never write WordPress data or call Google.
$GLOBALS['sci_test_options'] = array('sci_settings' => array('allow_writes' => true));
$GLOBALS['sci_test_transients'] = array(); $GLOBALS['sci_test_user'] = 42;
$GLOBALS['sci_test_caps'] = true; $GLOBALS['sci_test_abilities'] = array();
function get_option($key, $default = false) { return $GLOBALS['sci_test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = false) { $GLOBALS['sci_test_options'][$key] = $value; return true; }
function add_option($key, $value, $unused = '', $autoload = false) { if (array_key_exists($key, $GLOBALS['sci_test_options'])) { return false; } $GLOBALS['sci_test_options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['sci_test_options'][$key]); return true; }
function set_transient($key, $value, $ttl) { $GLOBALS['sci_test_transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['sci_test_transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['sci_test_transients'][$key]); return true; }
function current_user_can($cap, ...$args) { return $GLOBALS['sci_test_caps']; }
function is_user_logged_in() { return $GLOBALS['sci_test_user'] > 0; }
function get_current_user_id() { return $GLOBALS['sci_test_user']; }
function wp_salt($scheme) { return 'fixture-only-not-production-' . $scheme; }
function wp_generate_password($length, ...$args) { static $n = 0; $n++; return substr(str_repeat(hash('sha256', (string) $n), 2), 0, $length); }
function wp_register_ability($name, $args) { $GLOBALS['sci_test_abilities'][$name] = $args; return true; }
function wp_get_ability($name) { return null; }
function home_url($path = '/') { return 'https://example.test' . $path; }
function wc_get_product($id) { return null; }
function wc_get_product_id_by_sku($sku) { return 0; }
function wp_get_post_terms($id, $taxonomy) { return array((object) array('term_id' => 1, 'name' => 'Suits'), (object) array('term_id' => 2, 'name' => 'Clothing')); }
function expect($condition, $message) { $GLOBALS['sci_test_count'] = ($GLOBALS['sci_test_count'] ?? 0) + 1; if (!$condition) { throw new \RuntimeException('SCI_TEST_FAILED: ' . $message); } }
Core::register();
expect(count($GLOBALS['sci_test_abilities']) >= 30, 'abilities registered');
foreach ($GLOBALS['sci_test_abilities'] as $name => $definition) {
    expect($definition['input_schema']['additionalProperties'] === false, $name . ' rejects unknown fields');
    expect(is_callable($definition['permission_callback']), $name . ' has permission callback');
    expect(is_callable($definition['execute_callback']), $name . ' has execute callback');
    $GLOBALS['sci_test_caps'] = false; expect(call_user_func($definition['permission_callback']) === false, $name . ' denies unauthorized'); $GLOBALS['sci_test_caps'] = true;
}
expect(is_wp_error(Core::date_range(array('start_date' => '2026-02-30', 'end_date' => '2026-03-01'))), 'invalid calendar date rejected');
expect(is_wp_error(Core::date_range(array('start_date' => '2026-11-20', 'end_date' => '2026-11-01'))), 'reversed period rejected');
expect(Core::date_range(array('start_date' => '2024-02-29', 'end_date' => '2024-03-01')) === array('2024-02-29', '2024-03-01'), 'leap date accepted');
$calls = 0; $save = function () use (&$calls) { $calls++; return array('ok' => true); };
$preview = Core::mutate('fixture-update', array(), array('before' => 'A', 'after' => 'B'), $save);
expect(!$preview['applied'] && $calls === 0, 'preview never applies writes');
expect(is_wp_error(Core::mutate('fixture-update', array('dry_run' => false, 'preview_token' => $preview['preview_token']), array('before' => 'changed', 'after' => 'B'), $save)), 'stale record rejected');
$GLOBALS['sci_test_user'] = 99;
expect(is_wp_error(Core::mutate('fixture-update', array('dry_run' => false, 'preview_token' => $preview['preview_token']), array('before' => 'A', 'after' => 'B'), $save)), 'preview bound to user');
$GLOBALS['sci_test_user'] = 42;
$applied = Core::mutate('fixture-update', array('dry_run' => false, 'preview_token' => $preview['preview_token']), array('before' => 'A', 'after' => 'B'), $save);
expect($applied['applied'] && $calls === 1, 'matching preview applies once');
expect(is_wp_error(Core::mutate('fixture-update', array('dry_run' => false, 'preview_token' => $preview['preview_token']), array('before' => 'A', 'after' => 'B'), $save)), 'token replay rejected');
$GLOBALS['sci_test_options']['sci_settings']['allow_writes'] = false;
expect(is_wp_error(Core::mutate('fixture-update', array(), array(), $save)), 'read-only settings block writes');
$GLOBALS['sci_test_options']['sci_settings']['allow_writes'] = true;
expect(Core::lock('test-lock', function () { return is_wp_error(Core::lock('test-lock', function () { return true; })); }), 'concurrent lock rejected');
try { Core::lock('exception-lock', function () { throw new \RuntimeException('expected'); }); } catch (\RuntimeException $e) {}
expect(Core::lock('exception-lock', function () { return true; }) === true, 'exception releases lock');
$payload = array('refresh_token' => 'fixture-token', 'client_secret' => 'fixture-secret'); $encrypted = Google::encrypt($payload);
expect(Google::decrypt($encrypted) === $payload, 'credentials roundtrip');
expect(strpos($encrypted, 'fixture-token') === false, 'no plaintext token');
$raw = base64_decode($encrypted); $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
expect(Google::decrypt(base64_encode($raw)) === array(), 'tampered ciphertext rejected');
expect(!is_wp_error(SEO::safe_url('https://example.test/product/suit/')), 'own public URL accepted');
foreach (array('http://example.test/', 'https://other.test/', 'https://user:pass@example.test/', 'https://example.test/wp-admin/', 'https://example.test/%77p-admin/', 'https://example.test/?action=delete', 'file:///etc/passwd', 'https://example.test:444/') as $url) { expect(is_wp_error(SEO::safe_url($url)), 'unsafe URL rejected: ' . $url); }
$html = '<html><head><title>Fixture Suit</title><meta name="description" content="A fixture description"><meta name="robots" content="noindex"><link rel="canonical" href="https://example.test/suit/"><script type="application/ld+json">{"@type":"Product"}</script></head><body><h1>Fixture Suit</h1><img src="http://example.test/image.jpg"><a href="/shop/">Shop</a></body></html>';
$audit = SEO::parse_html($html, 'https://example.test/suit/');
expect(!is_wp_error($audit), 'HTML parsed');
expect($audit['title'] === 'Fixture Suit', 'title extracted');
expect(in_array('Product', $audit['schema_types'], true), 'schema type extracted');
$codes = array_column($audit['issues'], 'code');
expect(in_array('noindex', $codes, true) && in_array('mixed_content', $codes, true) && in_array('images_missing_alt', $codes, true), 'SEO findings extracted');
expect(in_array('https://example.test/shop/', $audit['internal_links'], true), 'relative internal link resolved');
$invalid_ld = SEO::parse_html('<html><body><script type="application/ld+json">{invalid}</script></body></html>', 'https://example.test/');
expect(in_array('invalid_json_ld', array_column($invalid_ld['issues'], 'code'), true), 'invalid schema JSON detected');
$schema = $GLOBALS['sci_test_abilities']['sternlabs/product-update']['input_schema'];
expect(!is_wp_error(\rest_validate_value_from_schema(array('id' => 1, 'manage_stock' => true, 'attributes' => array(array('id' => 1, 'options' => array('44'), 'variation' => true))), $schema)), 'valid nested product input passes WP schema validation');
expect(is_wp_error(\rest_validate_value_from_schema(array('id' => 1, 'regular_price' => '-10'), $schema)), 'negative price rejected by schema');
expect(is_wp_error(\rest_validate_value_from_schema(array('id' => 1, 'type' => 'simple'), $schema)), 'product type conversion impossible through schema');
expect(is_wp_error(\rest_validate_value_from_schema(array('id' => 1, 'attributes' => array(array('options' => array('44'), 'execute_php' => 'bad'))), $schema)), 'unknown nested attributes rejected');
$variable = new \WC_Product_Variable();
expect(is_wp_error(Catalog::prepare(clone $variable, array('regular_price' => '100'))), 'variable parent price edits blocked');
$edited = Catalog::prepare(clone $variable, array('sku' => 'FIXTURE-SUIT', 'manage_stock' => true, 'stock_quantity' => 8));
expect(!is_wp_error($edited) && $edited->is_type('variable') && $edited->get_stock_quantity('edit') === 8, 'variable type preserved for parent inventory edit');
$simple = new \WC_Product_Simple();
expect(is_wp_error(Catalog::prepare(clone $simple, array('stock_quantity' => 8))), 'quantity rejected when Manage stock is off');
expect(is_wp_error(Catalog::prepare(clone $simple, array('regular_price' => '100', 'sale_price' => '101'))), 'sale above regular rejected');
$variation = new \WC_Product_Variation();
$edited_variation = Catalog::prepare($variation, array('regular_price' => '100', 'sale_price' => '90', 'sku' => 'FIXTURE-44'));
expect(!is_wp_error($edited_variation) && $edited_variation->is_type('variation') && $edited_variation->get_regular_price('edit') === '100', 'variation prices and SKU editable without type conversion');
expect(is_wp_error(Catalog::prepare(new \WC_Product_Grouped(), array('regular_price' => '100'))), 'grouped prices edited through children only');
class FixtureItem {
    public function __construct(public $qty, public $total, public $subtotal) {}
    public function get_product_id() { return 123; } public function get_variation_id() { return 0; }
    public function get_quantity() { return $this->qty; } public function get_total() { return $this->total; } public function get_subtotal() { return $this->subtotal; }
    public function get_name() { return 'Fixture Suit'; } public function get_meta_data() { return array(); }
}
class FixtureOrder {
    public function __construct(public $currency = 'NGN') {}
    public function get_currency() { return $this->currency; } public function get_total() { return 240; } public function get_total_refunded() { return 40; }
    public function get_discount_total() { return 20; } public function get_shipping_total() { return 20; } public function get_total_tax() { return 20; }
    public function get_items($type) { return $type === 'line_item' ? array(10 => new FixtureItem(2, 150, 165), 11 => new FixtureItem(1, 50, 55)) : array(); }
    public function get_qty_refunded_for_item($id) { return $id === 10 ? -1 : 0; } public function get_total_refunded_for_item($id) { return $id === 10 ? 40 : 0; }
    public function get_customer_id() { return 1; } public function get_billing_email() { return 'fixture@example.test'; }
}
$j = array('currencies' => array(), 'products' => array(), 'variations' => array(), 'categories' => array(), 'coupons' => array(), 'customers' => array(), 'orders_processed' => 0);
Reports::accumulate($j, new FixtureOrder());
expect($j['currencies']['NGN']['net_merchandise_ex_tax'] === 160.0, 'net product revenue subtracts line refunds');
expect($j['currencies']['NGN']['net_units'] === 2.0, 'refunded units subtracted');
expect($j['products']['NGN:123']['orders'] === 1, 'repeated product lines counted once per order');
expect($j['categories']['NGN:1']['orders'] === 1 && $j['categories']['NGN:2']['orders'] === 1, 'multi-category attribution once per order');
Reports::accumulate($j, new FixtureOrder('USD'));
expect(count($j['currencies']) === 2 && $j['currencies']['USD']['net_merchandise_ex_tax'] === 160.0, 'currencies not combined');
expect($j['orders_processed'] === 2, 'orders processed count');
// Validator does not return stdout. A thrown exception would fail validation.
echo 'SCI_QA_PASSED assertions=' . $GLOBALS['sci_test_count'];
